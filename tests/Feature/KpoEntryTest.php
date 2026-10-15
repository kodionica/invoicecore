<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

function createKpoContext(string $email): array
{
    $user = User::create([
        'first_name' => 'KPO',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create(['name' => 'KPO Company', 'user_id' => $user->id]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'KPO Client']);

    return [$user, $company, $client];
}

it('rejects incomplete KPO source data', function () {
    [$user] = createKpoContext('kpo-validation@example.com');
    $this->actingAs($user);

    $this->postJson('/api/kpo', [
        'source_type' => 'manual',
        'entry_date' => '2026-10-15',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'currency',
        'amount_original',
        'amount_rsd',
    ]);

    $this->postJson('/api/kpo', [
        'source_type' => 'invoice',
        'entry_date' => '2026-10-15',
    ])->assertUnprocessable()->assertJsonValidationErrors('invoice_id');
});

it('validates manual KPO data and calculates the server total', function () {
    [$user, $company] = createKpoContext('kpo-manual@example.com');
    $this->actingAs($user);

    $response = $this->postJson('/api/kpo', [
        'source_type' => 'manual',
        'entry_date' => '2026-10-15',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'adjustment_rsd' => 12.50,
    ]);

    $response->assertCreated()->assertJsonPath('entry_number', 1)->assertJsonPath('total_rsd', '112.50');
    $this->assertDatabaseHas('kpo_entries', [
        'company_id' => $company->id,
        'year' => 2026,
        'entry_number' => 1,
        'total_rsd' => 112.50,
    ]);
});

it('creates an invoice source entry from stored invoice snapshots without using invoice status', function () {
    [$user, $company, $client] = createKpoContext('kpo-invoice@example.com');
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-001',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-15',
        'currency' => 'EUR',
        'total' => 100,
        'total_original' => 100,
        'total_rsd' => 11725,
        'status' => 'draft',
    ]);
    $this->actingAs($user);

    $response = $this->postJson('/api/kpo', [
        'source_type' => 'invoice',
        'invoice_id' => $invoice->id,
        'entry_date' => '2026-10-20',
    ]);

    $response->assertCreated()->assertJsonPath('amount_rsd', '11725.00');
    $this->assertDatabaseHas('kpo_entries', [
        'source_invoice_id' => $invoice->id,
        'total_rsd' => 11725.00,
    ]);
});

it('prevents duplicate entries for the same invoice and preserves correction on update', function () {
    [$user, $company, $client] = createKpoContext('kpo-duplicate@example.com');
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-002',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-15',
        'currency' => 'RSD',
        'total' => 200,
        'total_original' => 200,
        'total_rsd' => 200,
        'status' => 'paid',
    ]);
    $this->actingAs($user);

    $payload = ['source_type' => 'invoice', 'invoice_id' => $invoice->id, 'entry_date' => '2026-10-20', 'adjustment_rsd' => -10];
    $this->postJson('/api/kpo', $payload)->assertCreated();
    $this->postJson('/api/kpo', $payload)->assertStatus(422);

    $entry = KpoEntry::firstOrFail();
    $this->putJson("/api/kpo/{$entry->id}", ['adjustment_rsd' => -25])
        ->assertOk()
        ->assertJsonPath('total_rsd', '175.00');
});

it('prevents another tenant from reading or updating a KPO entry', function () {
    [$owner, $company] = createKpoContext('kpo-owner@example.com');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-15',
        'source_type' => 'manual',
        'currency' => 'RSD',
        'amount_original' => 10,
        'amount_rsd' => 10,
        'total_rsd' => 10,
    ]);
    [$otherUser] = createKpoContext('kpo-other@example.com');
    $this->actingAs($otherUser);

    $this->getJson("/api/kpo/{$entry->id}")->assertForbidden();
    $this->putJson("/api/kpo/{$entry->id}", ['adjustment_rsd' => 5])->assertForbidden();
});

it('does not allow an unauthorized active company to be used for KPO list or create', function () {
    [$owner, $company] = createKpoContext('kpo-company-owner@example.com');
    [$otherUser] = createKpoContext('kpo-company-other@example.com');
    $otherUser->update(['active_company_id' => $company->id]);
    $this->actingAs($otherUser);

    $this->getJson('/api/kpo')->assertForbidden();
    $this->postJson('/api/kpo', [
        'source_type' => 'manual',
        'entry_date' => '2026-10-15',
        'currency' => 'RSD',
        'amount_original' => 10,
        'amount_rsd' => 10,
    ])->assertForbidden();
});

it('previews only missing invoices for the selected year and can include historical invoices explicitly', function () {
    [$user, $company, $client] = createKpoContext('kpo-preview@example.com');
    $currentInvoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-003',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-15',
        'currency' => 'RSD',
        'total' => 100,
        'total_original' => 100,
        'total_rsd' => 100,
        'status' => 'paid',
    ]);
    $historicalInvoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2025-12-001',
        'issue_date' => '2025-12-20',
        'due_date' => '2026-01-03',
        'currency' => 'RSD',
        'total' => 200,
        'total_original' => 200,
        'total_rsd' => 200,
        'status' => 'paid',
    ]);
    Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-ignored',
        'issue_date' => '2026-10-21',
        'due_date' => '2026-11-05',
        'currency' => 'RSD',
        'total' => 50,
        'total_original' => 50,
        'total_rsd' => 50,
        'status' => 'paid',
        'include_in_kpo' => false,
    ]);
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $currentInvoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-02',
        'source_type' => 'invoice',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2026')
        ->assertOk()
        ->assertJsonPath('year', 2026)
        ->assertJsonCount(0, 'entries');

    $this->getJson('/api/kpo/generation-preview?year=2026&include_historical=true')
        ->assertOk()
        ->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.id', $historicalInvoice->id);
});

it('generates missing invoice entries idempotently and preserves manual corrections', function () {
    [$user, $company, $client] = createKpoContext('kpo-generate@example.com');
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-004',
        'issue_date' => '2026-10-03',
        'due_date' => '2026-10-17',
        'currency' => 'RSD',
        'total' => 300,
        'total_original' => 300,
        'total_rsd' => 300,
        'status' => 'paid',
    ]);
    $payload = ['year' => 2026, 'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2026-10-04']]];
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', $payload)->assertOk()->assertJsonCount(1, 'created')->assertJsonCount(0, 'skipped');
    $entry = KpoEntry::query()->where('source_invoice_id', $invoice->id)->firstOrFail();
    expect($entry->income_type)->toBe('usluga');
    $this->putJson("/api/kpo/{$entry->id}", ['adjustment_rsd' => 25])->assertOk();

    $this->postJson('/api/kpo/generate', $payload)->assertOk()->assertJsonCount(0, 'created')->assertJsonCount(1, 'skipped');
    expect((float) $entry->refresh()->adjustment_rsd)->toBe(25.0);
    expect(KpoEntry::query()->where('source_invoice_id', $invoice->id)->count())->toBe(1);
});

it('does not infer the year for a historical invoice without an explicit matching entry date', function () {
    [$user, $company, $client] = createKpoContext('kpo-historical@example.com');
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2025-12-002',
        'issue_date' => '2025-12-20',
        'due_date' => '2026-01-03',
        'currency' => 'RSD',
        'total' => 400,
        'total_original' => 400,
        'total_rsd' => 400,
        'status' => 'paid',
    ]);
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2026')->assertJsonCount(0, 'entries');
    $this->getJson('/api/kpo/generation-preview?year=2026&include_historical=true')->assertJsonCount(1, 'entries');
    $this->postJson('/api/kpo/generate', [
        'year' => 2026,
        'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2025-12-20']],
    ])->assertUnprocessable()->assertJsonValidationErrors('entries.0.entry_date');
    expect(KpoEntry::query()->where('source_invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('does not allow an entry update to change its numbering year', function () {
    [$user, $company] = createKpoContext('kpo-year@example.com');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-12-31',
        'source_type' => 'manual',
        'currency' => 'RSD',
        'amount_original' => 10,
        'amount_rsd' => 10,
        'total_rsd' => 10,
    ]);
    $this->actingAs($user);

    $this->putJson("/api/kpo/{$entry->id}", ['entry_date' => '2027-01-01'])->assertStatus(422);
});

it('does not generate an invoice from another tenant', function () {
    [$owner, $company, $client] = createKpoContext('kpo-generation-owner@example.com');
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => '2026-10-005',
        'issue_date' => '2026-10-05',
        'due_date' => '2026-10-19',
        'currency' => 'RSD',
        'total' => 500,
        'total_original' => 500,
        'total_rsd' => 500,
        'status' => 'paid',
    ]);
    [$otherUser] = createKpoContext('kpo-generation-other@example.com');
    $this->actingAs($otherUser);

    $this->postJson('/api/kpo/generate', [
        'year' => 2026,
        'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2026-10-06']],
    ])->assertOk()->assertJsonCount(0, 'created')->assertJsonCount(1, 'failed');
    expect(KpoEntry::query()->where('source_invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('generates a server-side KPO PDF from filtered database entries', function () {
    [$user, $company] = createKpoContext('kpo-pdf@example.com');
    KpoEntry::create([
        'company_id' => $company->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-15',
        'source_type' => 'manual',
        'income_type' => 'usluga',
        'currency' => 'RSD',
        'amount_original' => 1234.5,
        'amount_rsd' => 1234.5,
        'adjustment_rsd' => 10,
        'total_rsd' => 1244.5,
        'description' => 'Usluga sa srpskim slovima: ž, š, đ, č, ć i dugim opisom koji mora da se prelomi u PDF dokumentu bez izlaska iz tabele.',
    ]);
    Storage::fake('local');
    $pdfBuilder = Mockery::mock(PdfBuilder::class);
    $pdfBuilder->shouldReceive('format')->once()->andReturnSelf();
    $pdfBuilder->shouldReceive('withBrowsershot')->once()->andReturnSelf();
    $pdfBuilder->shouldReceive('disk')->once()->andReturnSelf();
    $pdfBuilder->shouldReceive('save')->once()->andReturnUsing(function (string $path) use ($pdfBuilder) {
        Storage::disk('local')->put($path, '%PDF-1.7 test');

        return $pdfBuilder;
    });
    Pdf::shouldReceive('html')->once()->andReturn($pdfBuilder);
    $this->actingAs($user);

    $response = $this->get('/api/kpo/pdf?year=2026');

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    Storage::disk('local')->assertExists("kpo/2026/kpo-evidencija-{$company->id}-2026.pdf");
});

it('validates KPO PDF periods and protects the route from another tenant', function () {
    [$owner, $company] = createKpoContext('kpo-pdf-owner@example.com');
    [$otherUser] = createKpoContext('kpo-pdf-other@example.com');
    $otherUser->update(['active_company_id' => $company->id]);

    $this->actingAs($owner);
    $this->getJson('/api/kpo/pdf?from=2026-12-31&to=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('to');

    $this->actingAs($otherUser);
    $this->get('/api/kpo/pdf?year=2026')->assertForbidden();
});
