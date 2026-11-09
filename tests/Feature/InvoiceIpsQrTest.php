<?php

use App\Models\Invoice;
use App\Models\User;
use App\Services\IpsQrService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Zxing\QrReader;

function ipsInvoiceFixture(array $changes = []): array
{
    $user = User::factory()->create(['username' => Str::random(24)]);
    $company = $user->companies()->create([
        'name' => 'Čigra doo', 'country' => 'RS', 'currency' => 'RSD', 'vat_enabled' => false,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create([
        'name' => 'Petar Petrović', 'country' => 'Srbija', 'country_code' => 'RS', 'client_type' => 'individual',
    ]);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id,
        'invoice_number' => '2026-11-001', 'issue_date' => '2026-11-09', 'service_date' => '2026-11-09',
        'due_date' => '2026-11-23', 'currency' => 'RSD', 'payment_method' => 'bank_transfer',
        'status' => 'draft', 'include_in_kpo' => false, 'vat_enabled_at_issue' => false,
        'total' => '1234.56', 'total_original' => '1234.56', 'subtotal_original' => '1234.56',
        'tax_original' => 0, 'total_rsd' => '1234.56', 'subtotal_rsd' => '1234.56', 'tax_rsd' => 0,
        'fx_rate_to_rsd' => 1, 'bank_account_number' => '840-955845-10', 'bank_account_currency' => 'RSD',
        ...$changes,
    ]);
    $invoice->forceFill(['document_settings' => [
        'locale' => 'sr-Latn', 'bank_account_type' => 'domestic',
        'ips_payment_details' => ['SF' => '221', 'RO' => '002026-11-001', 'S' => 'Usluga'],
    ]])->save();
    $invoice->items()->create([
        'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => '1234.56',
        'sub_total' => '1234.56', 'total' => '1234.56', 'tax_amount' => 0,
        'tax_percent' => 0, 'position' => 0, 'income_category' => 'service',
    ]);

    return [$user, $company, $client, $invoice];
}

function mockIpsInvoicePdf(Closure $check): void
{
    Storage::fake('local');
    $builder = Mockery::mock(PdfBuilder::class);
    $builder->shouldReceive('format')->andReturnSelf();
    $builder->shouldReceive('withBrowsershot')->andReturnSelf();
    $builder->shouldReceive('disk')->andReturnSelf();
    $builder->shouldReceive('save')->andReturnUsing(function ($path) use ($builder) {
        Storage::disk('local')->put($path, '%PDF test');

        return $builder;
    });
    Pdf::shouldReceive('html')->withArgs($check)->andReturn($builder);
}

function decodeInvoiceQr(string $base64): string
{
    return (new QrReader(base64_decode($base64, true), QrReader::SOURCE_TYPE_BLOB, false))->text();
}

it('uses the local IPS service for the screen and the SSR PDF document without HTTP requests', function () {
    Http::preventStrayRequests();
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $url = "/api/invoices/{$invoice->id}";
    $document = $this->actingAs($user)->getJson($url)->assertOk()->json('document');
    $expected = 'K:PR|V:01|C:1|R:840000000095584510|N:Čigra doo|I:RSD1234,56|P:Petar Petrović|SF:221|S:Usluga|RO:002026-11-001';

    expect($document['qrCodeError'])->toBeNull()
        ->and($document['qrPayload'])->toBe($expected)
        ->and(decodeInvoiceQr($document['qrCode']))->toBe($expected);
    mockIpsInvoicePdf(function (string $html) use ($expected): bool {
        expect($html)->not->toContain('nbs.rs/QRcode');
        expect(preg_match('/src="data:image\/png;base64,([^"]+)" alt="NBS IPS QR"/', $html, $match))->toBe(1);
        expect(decodeInvoiceQr($match[1]))->toBe($expected);

        return true;
    });
    $this->getJson("$url/pdf")->assertOk();
    Http::assertNothingSent();
});

it('stores one QR on issuance and keeps its data after bank, company and client changes', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    mockIpsInvoicePdf(fn (string $html): bool => str_contains($html, 'alt="NBS IPS QR"'));
    $spy = Mockery::mock(IpsQrService::class, [app(\App\Services\QrCodeService::class)])->makePartial();
    $spy->shouldReceive('render')->once()->passthru();
    app()->instance(IpsQrService::class, $spy);
    $url = "/api/invoices/{$invoice->id}";

    $this->actingAs($user)->postJson("$url/issue")->assertOk();
    $stored = $invoice->fresh()->document_settings;
    expect(decodeInvoiceQr($stored['qr_code']))->toBe($stored['qr_payload']);
    $original = $this->getJson($url)->assertOk()->json('document');
    $company->update(['name' => 'Changed seller', 'bank_account' => '165-55-74', 'country' => 'DE']);
    $client->update(['name' => 'Changed buyer', 'country_code' => 'DE']);
    config(['client-type' => []]);

    expect($this->getJson($url)->assertOk()->json('document'))->toBe($original);
    $this->getJson("$url/pdf?refresh=1")->assertOk();
    $this->putJson($url, ['ips_payment_details' => ['SF' => '253']])->assertConflict();
});

it('preserves a saved legacy QR verbatim and reuses the existing PDF bytes', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $saved = base64_encode(app(\App\Services\QrCodeService::class)->png('Preserved historical QR'));
    $settings = $invoice->document_settings;
    $invoice->forceFill([
        'issued_at' => now(), 'status' => 'sent',
        'seller_snapshot' => ['id' => $company->id, 'name' => 'Original seller', 'country' => 'RS'],
        'buyer_snapshot' => ['id' => $client->id, 'name' => 'Original buyer', 'client_type' => 'individual', 'country_code' => 'RS'],
        'document_settings' => [...$settings, 'meta' => ['client_types' => [], 'payment_methods' => []], 'qr_code' => $saved],
    ])->save();
    Storage::fake('local');
    Storage::disk('local')->put('old-invoice.pdf', '%PDF historical bytes');
    $invoice->update(['pdf_path' => 'old-invoice.pdf']);
    app()->instance(IpsQrService::class, Mockery::mock(IpsQrService::class));
    $url = "/api/invoices/{$invoice->id}";

    $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('document.qrCode', $saved);
    $this->getJson("$url/pdf")->assertOk();
    expect(Storage::disk('local')->get('old-invoice.pdf'))->toBe('%PDF historical bytes');
    expect($invoice->fresh()->document_settings['qr_code'])->toBe($saved);
});

it('uses saved metadata to regenerate a missing old QR locally without changing the invoice', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $invoice->forceFill([
        'issued_at' => now(), 'status' => 'sent',
        'seller_snapshot' => ['id' => $company->id, 'name' => 'Original seller', 'country' => 'RS'],
        'buyer_snapshot' => ['id' => $client->id, 'name' => 'Original buyer', 'client_type' => 'individual', 'country_code' => 'RS'],
        'document_settings' => ['locale' => 'sr-Latn', 'qr_code' => null, 'meta' => [
            'client_types' => [['value' => 'individual', 'code' => '221']], 'payment_methods' => [],
        ]],
    ])->save();
    $before = $invoice->fresh()->getRawOriginal();
    $company->update(['name' => 'Changed seller', 'bank_account' => '165-55-74']);
    $document = $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}")->assertOk()->json('document');

    expect(decodeInvoiceQr($document['qrCode']))->toContain('N:Original seller|I:RSD1234,56')
        ->and($invoice->fresh()->getRawOriginal())->toBe($before);
});

it('explains missing or invalid bank data and excludes foreign-currency and foreign payments', function ($changes, $companyChanges, $clientChanges, $expected) {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture($changes);
    $company->update($companyChanges);
    $client->update($clientChanges);
    $document = $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}")->assertOk()->json('document');

    expect($document['qrCode'])->toBeNull()->and($document['qrCodeError'])->toContain($expected);
})->with([
    'no account' => [['bank_account_number' => null], [], [], 'račun primaoca'],
    'bad account' => [['bank_account_number' => '840-955845-11'], [], [], 'kontrolni broj'],
    'foreign invoice' => [['currency' => 'EUR', 'fx_rate_to_rsd' => 117.25, 'total_rsd' => '144752.16'], [], [], 'stranoj valuti'],
    'foreign bank currency' => [['bank_account_currency' => 'EUR'], [], [], 'stranoj valuti'],
    'foreign IBAN' => [['bank_account_iban' => 'DE89370400440532013000'], [], [], 'nije domaći'],
    'foreign buyer' => [[], [], ['country_code' => 'DE'], 'domaćeg klijenta'],
    'foreign seller' => [[], ['country' => 'DE'], [], 'domaćeg izdavaoca'],
]);

it('uses each selected account snapshot and rejects an explicitly foreign RSD account', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $account = $company->bankAccounts()->create([
        'bank_name' => 'Second bank', 'account_number' => '165-55-74', 'currency' => 'RSD',
        'account_type' => 'domestic', 'is_active' => true,
    ]);
    $url = "/api/invoices/{$invoice->id}";
    $this->actingAs($user)->putJson($url, ['bank_account_id' => $account->id])->assertOk();
    $account->update(['account_number' => '840-955845-10']);
    $document = $this->getJson($url)->assertOk()->json('document');
    expect(decodeInvoiceQr($document['qrCode']))->toContain('R:165000000000005574|');

    $account->update(['account_type' => 'foreign']);
    $settings = $invoice->fresh()->document_settings;
    unset($settings['bank_account_type']);
    $invoice->forceFill(['document_settings' => $settings])->save();
    $document = $this->getJson($url)->assertOk()->json('document');
    expect($document['qrCode'])->toBeNull()->and($document['qrCodeError'])->toContain('nije domaći');
    $this->putJson($url, ['bank_account_id' => $account->id])->assertOk();
    $document = $this->getJson($url)->assertOk()->json('document');
    expect($document['qrCode'])->toBeNull()->and($document['qrCodeError'])->toContain('nije domaći');
});

it('accepts explicit payment data and rejects invalid models without inferring a payment code', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $url = "/api/invoices/{$invoice->id}";
    $this->actingAs($user)->putJson($url, ['ips_payment_details' => ['SF' => '253', 'RO' => '9714123412', 'S' => 'Druga svrha']])->assertOk();
    $document = $this->getJson($url)->assertOk()->json('document');
    expect(decodeInvoiceQr($document['qrCode']))->toContain('|SF:253|S:Druga svrha|RO:9714123412');
    $this->putJson($url, ['ips_payment_details' => ['SF' => '221', 'RO' => '9715123412']])
        ->assertUnprocessable()->assertJsonValidationErrors('ips_payment_details.RO');
    $this->putJson($url, ['ips_payment_details' => ['SF' => '299']])
        ->assertUnprocessable()->assertJsonValidationErrors('ips_payment_details.SF');
    $this->putJson($url, ['ips_payment_details' => []])->assertOk();
    $this->getJson($url)->assertOk()->assertJsonPath('document.qrCode', null)
        ->assertJsonPath('document.qrCodeError', 'Unesite šifru plaćanja.');
});

it('protects the invoice QR payload across users and inactive companies', function () {
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    $other = User::factory()->create(['username' => Str::random(24)]);
    $otherCompany = $other->companies()->create(['name' => 'Other']);
    $other->update(['active_company_id' => $otherCompany->id]);
    $this->actingAs($other)->getJson("/api/invoices/{$invoice->id}")->assertForbidden();
    $sibling = $user->companies()->create(['name' => 'Sibling']);
    $user->update(['active_company_id' => $sibling->id]);
    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}")->assertForbidden();
});

it('embeds the local QR image into a real invoice PDF', function () {
    Http::preventStrayRequests();
    [$user, $company, $client, $invoice] = ipsInvoiceFixture();
    Storage::fake('local');

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $pdf = Storage::disk('local')->get($invoice->fresh()->pdf_path);

    expect($pdf)->toStartWith('%PDF-')->toContain('/Subtype /Image');
    Http::assertNothingSent();
});

it('persists user payment instructions when a new invoice is created', function () {
    [$user, $company, $client] = ipsInvoiceFixture();
    $account = $company->bankAccounts()->create([
        'bank_name' => 'Invoice bank', 'account_number' => '165-55-74', 'currency' => 'RSD',
        'account_type' => 'domestic', 'is_active' => true,
    ]);
    $details = ['SF' => '221', 'RO' => '9714123412', 'S' => 'Nova faktura'];
    $data = [
        'client_id' => $client->id, 'invoice_number' => 'NEW-QR',
        'issue_date' => $company->businessToday()->toDateString(), 'currency' => 'RSD',
        'bank_account_id' => $account->id, 'ips_payment_details' => $details,
        'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => '12.34']],
    ];
    $response = $this->actingAs($user)->postJson('/api/invoices', $data)->assertCreated()
        ->assertJsonPath('ips_payment_details.SF', '221');
    $id = $response->json('id');
    $this->getJson("/api/invoices/$id")->assertOk()
        ->assertJsonPath('document.qrPayload', 'K:PR|V:01|C:1|R:165000000000005574|N:Čigra doo|I:RSD12,34|P:Petar Petrović|SF:221|S:Nova faktura|RO:9714123412');
    $data['invoice_number'] = 'NEW-INVALID-QR';
    $data['ips_payment_details']['RO'] = '9715123412';
    $this->postJson('/api/invoices', $data)->assertUnprocessable()->assertJsonValidationErrors('ips_payment_details.RO');
});
