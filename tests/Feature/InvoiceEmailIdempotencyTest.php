<?php

use App\Models\Invoice;
use App\Models\InvoiceEmailSend;
use App\Models\InvoicePdfVersion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

function emailIdempotencyFixture(string $email): array
{
    $user = User::create([
        'first_name' => 'Email', 'last_name' => 'Test', 'email' => $email,
        'username' => str_replace('@', '_', $email), 'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'Email Test Company', 'address' => 'Adresa 1', 'city' => 'Beograd',
        'bank_account' => '160-0000000000000-00',
        'currency' => 'RSD', 'vat_enabled' => false, 'invoice_start_number' => 1,
        'payment_due_days' => 14,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Email Client', 'email' => 'client@example.test', 'client_type' => 'b2b']);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'EMAIL-001',
        'issue_date' => '2026-10-17', 'due_date' => '2026-10-31', 'currency' => 'RSD',
        'payment_method' => 'bank_transfer', 'status' => 'draft', 'include_in_kpo' => false,
        'fx_rate_to_rsd' => 1, 'subtotal_original' => 100, 'tax_original' => 0,
        'total_original' => 100, 'subtotal_rsd' => 100, 'tax_rsd' => 0, 'total_rsd' => 100,
        'total' => 100, 'vat_enabled_at_issue' => false,
    ]);
    $invoice->items()->create([
        'name' => 'Email stavka', 'description' => 'Email stavka', 'quantity' => 1,
        'price' => 100, 'sub_total' => 100, 'tax_amount' => 0, 'total' => 100,
        'income_category' => 'service',
    ]);

    return [$user, $invoice->fresh()];
}

function fakeEmailIdempotencyPdf(): void
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

it('does not send twice when the same idempotency key is replayed', function () {
    [$user, $invoice] = emailIdempotencyFixture('email-idempotency-replay@example.test');
    fakeEmailIdempotencyPdf();
    Mail::fake();

    $headers = ['Idempotency-Key' => 'email-operation-replay'];
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], $headers)->assertOk();
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], $headers)
        ->assertOk()
        ->assertJson(['idempotent_replay' => true, 'delivery_status' => 'accepted']);

    Mail::assertSent(\App\Mail\InvoiceMail::class, 1);
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('does not retry a failed operation with the same key but allows a new operation', function () {
    [$user, $invoice] = emailIdempotencyFixture('email-idempotency-failure@example.test');
    fakeEmailIdempotencyPdf();
    Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('SMTP failed'));

    $headers = ['Idempotency-Key' => 'email-operation-failure'];
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], $headers)->assertStatus(502);
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], $headers)
        ->assertStatus(409)
        ->assertJson(['delivery_status' => 'failed']);
    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], ['Idempotency-Key' => 'email-operation-new'])
        ->assertStatus(502);

    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->count())->toBe(2);
});

it('reports an unknown outcome when the post-transport database update fails', function () {
    [$user, $invoice] = emailIdempotencyFixture('email-idempotency-unknown@example.test');
    fakeEmailIdempotencyPdf();
    Mail::fake();
    $failAcceptedUpdate = true;
    InvoiceEmailSend::updated(function (InvoiceEmailSend $attempt) use (&$failAcceptedUpdate): void {
        if ($failAcceptedUpdate && $attempt->status === 'accepted') {
            $failAcceptedUpdate = false;
            throw new RuntimeException('Simulated post-transport database failure');
        }
    });

    $response = $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email", [], ['Idempotency-Key' => 'email-operation-unknown']);

    $response->assertStatus(500)->assertJson(['delivery_status' => 'unknown']);
    expect(InvoiceEmailSend::where('invoice_id', $invoice->id)->value('status'))->toBe('unknown')
        ->and($invoice->fresh()->status)->toBe('draft')
        ->and(InvoicePdfVersion::where('invoice_id', $invoice->id)->whereNotNull('sent_at')->count())->toBe(0);
    Mail::assertSent(\App\Mail\InvoiceMail::class, 1);
});

it('requires an idempotency key for email sending', function () {
    [$user, $invoice] = emailIdempotencyFixture('email-idempotency-required@example.test');

    $this->actingAs($user)->postJson("/api/invoices/{$invoice->id}/email")
        ->assertStatus(422)
        ->assertJsonValidationErrors('idempotency_key');
});
