<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmailSend;
use App\Models\InvoicePdfVersion;
use App\Models\CompanyBankAccount;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

function invoicePdfFixture(string $email = 'invoice-pdf@example.com'): array
{
    $user = User::create([
        'first_name' => 'PDF', 'last_name' => 'Test', 'email' => $email,
        'username' => str_replace('@', '_', $email), 'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'PDF Company', 'address' => 'PDF 1', 'city' => 'Beograd', 'bank_account' => '160-0000000000000-00', 'currency' => 'RSD', 'vat_enabled' => false,
        'invoice_start_number' => 1, 'payment_due_days' => 14,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'PDF Client', 'email' => 'client@example.com', 'client_type' => 'b2b']);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'PDF-001',
        'issue_date' => '2026-10-17', 'due_date' => '2026-10-31', 'currency' => 'RSD',
        'payment_method' => 'bank_transfer', 'status' => 'draft', 'include_in_kpo' => true,
        'fx_rate_to_rsd' => 1, 'subtotal_original' => 100, 'tax_original' => 0,
        'total_original' => 100, 'subtotal_rsd' => 100, 'tax_rsd' => 0, 'total_rsd' => 100,
        'total' => 100, 'vat_enabled_at_issue' => false,
    ]);
    $invoice->items()->create([
        'name' => 'PDF stavka', 'description' => 'PDF stavka', 'quantity' => 1,
        'price' => 100, 'sub_total' => 100, 'tax_amount' => 0, 'total' => 100,
        'income_category' => 'service', 'tax_percent' => 0, 'position' => 0,
    ]);

    return [$user, $company, $invoice->fresh(['client', 'company', 'items'])];
}

function fakeInvoicePdf(?Closure $matchesHtml = null): void
{
    Storage::fake('local');
    $builder = Mockery::mock(PdfBuilder::class);
    $builder->shouldReceive('format')->andReturnSelf();
    $builder->shouldReceive('withBrowsershot')->andReturnSelf();
    $builder->shouldReceive('disk')->andReturnSelf();
    $builder->shouldReceive('save')->andReturnUsing(function (string $path) use ($builder) {
        Storage::disk('local')->put($path, '%PDF-1.7 test');
        return $builder;
    });
    $render = Pdf::shouldReceive('html');
    if ($matchesHtml) {
        $render->withArgs($matchesHtml);
    }
    $render->andReturn($builder);
}

it('creates one current PDF version and reuses it on repeated downloads', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-reuse@example.com');
    fakeInvoicePdf();

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();

    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->count())->toBe(1);
    $this->assertDatabaseHas('invoice_pdf_versions', ['invoice_id' => $invoice->id, 'version' => 1, 'protected_at' => null]);
    expect($invoice->fresh()->pdf_path)->not->toBeNull();
});

it('does not render an empty account number row for a foreign-currency snapshot', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-foreign-bank@example.com');
    $account = CompanyBankAccount::create([
        'company_id' => $company->id,
        'bank_name' => 'Devizna banka',
        'account_number' => null,
        'iban' => 'DE89370400440532013000',
        'swift_bic' => 'COBADEFFXXX',
        'currency' => 'EUR',
        'account_type' => 'foreign',
        'is_default' => true,
        'is_active' => true,
    ]);
    $invoice->update([
        'currency' => 'EUR',
        'bank_account_id' => $account->id,
        'bank_account_bank_name' => $account->bank_name,
        'bank_account_number' => null,
        'bank_account_iban' => $account->iban,
        'bank_account_swift_bic' => $account->swift_bic,
        'bank_account_currency' => 'EUR',
    ]);

    Storage::fake('local');
    $builder = Mockery::mock(PdfBuilder::class);
    $builder->shouldReceive('format')->andReturnSelf();
    $builder->shouldReceive('withBrowsershot')->andReturnSelf();
    $builder->shouldReceive('disk')->andReturnSelf();
    $builder->shouldReceive('save')->andReturnUsing(function (string $path) use ($builder) {
        Storage::disk('local')->put($path, '%PDF-1.7 test');
        return $builder;
    });
    Pdf::shouldReceive('html')
        ->once()
        ->withArgs(fn (string $html): bool => ! str_contains($html, 'BROJ RAČUNA:'))
        ->andReturn($builder);

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
});

it('protects the exact PDF version after successful email and keeps it after status changes', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-send@example.com');
    fakeInvoicePdf();
    Mail::fake();
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [
        'recipient' => 'accounting@example.com',
        'subject' => 'Posebna tema',
        'message' => 'Molimo pogledajte priloženu fakturu.',
    ], ['Idempotency-Key' => 'pdf-version-send-1'])->assertOk();
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->value('recipient'))->toBe('accounting@example.com');
    Mail::assertSent(\App\Mail\InvoiceMail::class, function ($mail) use ($invoice): bool {
        return $mail->pdfPath === $invoice->fresh()->pdf_path
            && $mail->subjectText === 'Posebna tema'
            && $mail->messageText === 'Molimo pogledajte priloženu fakturu.';
    });

    $version = InvoicePdfVersion::where('invoice_id', $invoice->id)->firstOrFail();
    expect($version->protected_at)->not->toBeNull()->and($version->sent_at)->not->toBeNull();
    $attempt = InvoiceEmailSend::where('invoice_id', $invoice->id)->firstOrFail();
    expect($attempt->status)->toBe('accepted')->and($attempt->invoice_pdf_version_id)->toBe($version->id);

    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['status' => 'draft'])->assertConflict();
    expect($version->fresh()->protected_at)->not->toBeNull();
});

it('generates and protects the PDF on explicit issuance', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-manual-send@example.com');
    fakeInvoicePdf();

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();

    $version = InvoicePdfVersion::where('invoice_id', $invoice->id)->firstOrFail();
    expect($version->protected_at)->not->toBeNull()->and($version->sent_at)->toBeNull();
    expect($invoice->fresh()->status)->toBe('sent');
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('creates a new immutable current version when an invoice with a PDF is edited', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-edit@example.com');
    fakeInvoicePdf();
    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;

    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", [
        'note' => 'Izmenjena napomena',
    ])->assertOk();

    $versions = InvoicePdfVersion::where('invoice_id', $invoice->id)->orderBy('version')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->path)->toBe($oldPath)
        ->and($versions[1]->path)->not->toBe($oldPath);
    Storage::disk('local')->assertExists($oldPath);
});

it('removes an old unprotected PDF when retention permits cleanup', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention-cleanup@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;

    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['note' => 'Nova verzija'])->assertOk();

    Storage::disk('local')->assertMissing($oldPath);
    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('keeps issuance independent of a failed email delivery', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-fail@example.com');
    fakeInvoicePdf();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP failed'));
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], ['Idempotency-Key' => 'pdf-version-fail-1'])->assertServerError();

    expect($invoice->fresh()->issued_at)->not->toBeNull();
    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->whereNotNull('sent_at')->count())->toBe(0);
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->firstOrFail()->status)->toBe('failed');
});

it('keeps protected versions outside retention and scopes version history to the invoice tenant', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/issue")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;
    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf?refresh=1")->assertOk();

    Storage::disk('local')->assertExists($oldPath);
    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->count())->toBe(2);

    $other = User::create([
        'first_name' => 'Other', 'last_name' => 'User', 'email' => 'invoice-pdf-other@example.com',
        'username' => 'invoice-pdf-other', 'password' => 'password',
    ]);
    $otherCompany = $other->companies()->create(['name' => 'Other PDF Company', 'currency' => 'RSD']);
    $other->update(['active_company_id' => $otherCompany->id]);
    $this->actingAs($other)->getJson("/api/invoices/{$invoice->id}/pdf-versions")->assertForbidden();
});

it('fails retention explicitly when an unprotected historical PDF file is missing', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention-failure@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $oldPath = 'invoices/2026/10/'.$invoice->id.'/v1/legacy.pdf';
    InvoicePdfVersion::create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'version' => 1,
        'path' => $oldPath,
        'created_at' => now()->subDay(),
    ]);
    $invoice->update(['pdf_path' => $oldPath]);

    $this->actingAs($user)
        ->getJson("/api/invoices/{$invoice->id}/pdf?refresh=1")
        ->assertServerError();

    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->where('version', 1)->exists())->toBeTrue();
});

it('restores the DB row when filesystem deletion returns false', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention-storage-failure@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;
    $oldVersion = InvoicePdfVersion::where('invoice_id', $invoice->id)->firstOrFail();
    $realDisk = Storage::disk('local');
    $disk = Mockery::mock($realDisk)->makePartial();
    $disk->shouldReceive('delete')->with($oldPath)->andReturnFalse();
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $this->actingAs($user)
        ->putJson("/api/invoices/{$invoice->id}", ['note' => 'Nova verzija'])
        ->assertServerError();

    expect(InvoicePdfVersion::whereKey($oldVersion->id)->exists())->toBeTrue()
        ->and($realDisk->exists($oldPath))->toBeTrue();
});

it('keeps the PDF file and database row when retention database deletion fails', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention-db-failure@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;
    $oldVersion = InvoicePdfVersion::where('invoice_id', $invoice->id)->firstOrFail();
    InvoicePdfVersion::deleting(function (InvoicePdfVersion $version) use ($oldVersion): void {
        if ($version->is($oldVersion)) {
            throw new RuntimeException('Simulated retention database failure');
        }
    });

    $this->actingAs($user)
        ->putJson("/api/invoices/{$invoice->id}", ['note' => 'Nova verzija'])
        ->assertServerError();

    Storage::disk('local')->assertExists($oldPath);
    expect(InvoicePdfVersion::whereKey($oldVersion->id)->exists())->toBeTrue();
});

it('locks the document and preserves the original after profile changes', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-integrity@example.com');
    fakeInvoicePdf();
    Storage::fake('public');
    Storage::disk('public')->put('seller-logo.png', 'original-logo');
    $company->update(['logo_path' => 'seller-logo.png']);
    $account = $company->bankAccounts()->create([
        'bank_name' => 'Original bank', 'account_number' => '160-0000000000000-00',
        'currency' => 'RSD', 'account_type' => 'domestic',
    ]);
    $invoice->update([
        'bank_account_id' => $account->id, 'bank_account_bank_name' => $account->bank_name,
        'bank_account_number' => $account->account_number, 'bank_account_currency' => 'RSD',
    ]);
    $invoice->client->update(['client_type' => 'company', 'postal_code' => '11000']);
    $url = "/api/invoices/{$invoice->id}";
    $this->actingAs($user)->postJson("$url/issue")->assertOk();
    $original = $this->getJson($url)->assertOk()->json('document');
    $path = $invoice->fresh()->pdf_path;
    expect($original['company']['logoUrl'])->toStartWith('data:');
    expect($original['invoice']['bankAccountBankName'])->toBe('Original bank');
    expect($original['client']['postal_code'])->toBe('11000')
        ->and($original['client']['client_type'])->toBe('company')
        ->and($original['qrCode'])->toBeNull();

    $company->update(['name' => 'New seller', 'default_tax_percent' => 25, 'logo_path' => null]);
    Storage::disk('public')->delete('seller-logo.png');
    $account->update(['bank_name' => 'Changed bank', 'account_number' => '260-0000000000000-00']);
    config(['payment' => ['bank_transfer' => 'Changed payment label']]);
    $this->putJson("/api/clients/{$invoice->client_id}", [
        'name' => 'New buyer', 'address' => 'New address', 'country_code' => 'DE',
        'client_type' => 'entrepreneur', 'postal_code' => '01067',
    ])->assertOk();

    $this->postJson("$url/issue")->assertOk();
    expect($invoice->pdfVersions()->count())->toBe(1);
    expect($this->getJson($url)->assertOk()->json('document'))->toBe($original);
    $this->putJson($url, ['due_date' => '2026-12-01'])->assertConflict();
    $this->deleteJson($url)->assertConflict();
    $this->deleteJson("/api/clients/{$invoice->client_id}")->assertConflict();
    $this->deleteJson("/api/companies/{$company->id}")->assertConflict();
    $this->getJson("$url/pdf?refresh=1")->assertOk();
    Storage::disk('local')->assertExists($path);
    expect($invoice->pdfVersions()->count())->toBe(2);
});

it('preserves a historical PDF and declines legacy regeneration', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-integrity-legacy@example.com');
    Storage::fake('local');
    Storage::disk('local')->put('historical.pdf', '%PDF historical');
    $invoice->update(['status' => 'paid', 'pdf_path' => 'historical.pdf']);
    $url = "/api/invoices/{$invoice->id}";

    $this->actingAs($user)->getJson($url)->assertOk()
        ->assertJsonPath('legacy_document', true)->assertJsonPath('document', null);
    $this->getJson("$url/pdf")->assertOk();
    $this->getJson("$url/pdf?refresh=1")->assertConflict();
    expect(Storage::disk('local')->get('historical.pdf'))->toBe('%PDF historical');
    expect($invoice->fresh()->issued_at)->toBeNull();
});

it('defaults and preserves service dates through the invoice API', function () {
    [$user, $company, $invoice] = invoicePdfFixture('service-date-api@example.test');
    $issueDate = $company->businessToday()->addDay()->toDateString();
    $serviceDate = $company->businessToday()->subDay()->toDateString();
    $payload = [
        'client_id' => $invoice->client_id,
        'issue_date' => $issueDate,
        'items' => [['name' => 'Usluga', 'quantity' => 1, 'price' => 100]],
    ];
    $this->actingAs($user)->postJson('/api/invoices', $payload)->assertCreated()
        ->assertJsonPath('service_date', $issueDate.'T00:00:00.000000Z');
    $created = $this->postJson('/api/invoices', $payload + ['service_date' => $serviceDate])
        ->assertCreated()->assertJsonPath('service_date', $serviceDate.'T00:00:00.000000Z');
    $url = '/api/invoices/'.$created->json('id');
    $this->putJson($url, ['issue_date' => $company->businessToday()->addDays(2)->toDateString()])
        ->assertOk()->assertJsonPath('service_date', $serviceDate.'T00:00:00.000000Z');
    $this->putJson($url, ['service_date' => $issueDate])
        ->assertOk()->assertJsonPath('service_date', $issueDate.'T00:00:00.000000Z');
    $this->getJson($url)->assertOk()->assertJsonPath('document.invoice.serviceDate', $issueDate);
    $this->putJson($url, ['service_date' => '2026-02-30'])
        ->assertUnprocessable()->assertJsonValidationErrors('service_date');
});

it('renders the persisted service date independently and locks it on issuance', function () {
    [$user, $company, $invoice] = invoicePdfFixture('service-date-pdf@example.test');
    $invoice->update(['service_date' => '2026-10-09']);
    fakeInvoicePdf(fn (string $html): bool => preg_match('/Datum izdavanja:<\/span>\s*<span[^>]*>17\./', $html)
        && preg_match('/Datum prometa:<\/span>\s*<span[^>]*>09\./', $html));

    $url = "/api/invoices/{$invoice->id}";
    $this->actingAs($user)->postJson("$url/issue")->assertOk();
    $this->putJson($url, ['service_date' => '2026-10-10'])->assertConflict();
});
