<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEmailSend;
use App\Models\InvoicePdfVersion;
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
        'income_category' => 'service',
    ]);

    return [$user, $company, $invoice->fresh(['client', 'company', 'items'])];
}

function fakeInvoicePdf(): void
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
    Pdf::shouldReceive('html')->andReturn($builder);
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

it('protects the exact PDF version after successful email and keeps it after status changes', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-send@example.com');
    fakeInvoicePdf();
    Mail::fake();

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [
        'recipient' => 'accounting@example.com',
        'subject' => 'Posebna tema',
        'message' => 'Molimo pogledajte priloženu fakturu.',
    ])->assertOk();
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

    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['status' => 'draft'])->assertOk();
    expect($version->fresh()->protected_at)->not->toBeNull();
});

it('generates and protects the current PDF when the user manually marks an invoice sent', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-manual-send@example.com');
    fakeInvoicePdf();

    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['status' => 'sent'])->assertOk();

    $version = InvoicePdfVersion::where('invoice_id', $invoice->id)->firstOrFail();
    expect($version->protected_at)->not->toBeNull()->and($version->sent_at)->not->toBeNull();
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

it('does not mark a PDF or invoice as sent when mail delivery fails', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-fail@example.com');
    fakeInvoicePdf();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP failed'));

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email")->assertServerError();

    expect($invoice->fresh()->status)->toBe('draft');
    expect(InvoicePdfVersion::where('invoice_id', $invoice->id)->whereNotNull('sent_at')->count())->toBe(0);
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->firstOrFail()->status)->toBe('failed');
});

it('keeps protected versions outside retention and scopes version history to the invoice tenant', function () {
    [$user, $company, $invoice] = invoicePdfFixture('invoice-pdf-retention@example.com');
    $company->update(['other_settings' => ['invoice_pdf' => ['max_unprotected_versions' => 0]]]);
    fakeInvoicePdf();

    $this->actingAs($user)->getJson("/api/invoices/{$invoice->id}/pdf")->assertOk();
    $oldPath = $invoice->fresh()->pdf_path;
    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['status' => 'sent'])->assertOk();
    $this->actingAs($user)->putJson("/api/invoices/{$invoice->id}", ['note' => 'Nova verzija'])->assertOk();

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
