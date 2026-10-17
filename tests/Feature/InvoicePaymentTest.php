<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceRevision;
use App\Models\User;

function paymentFixture(string $email = 'invoice-payment@example.com'): array
{
    $user = User::create([
        'first_name' => 'Payment',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => 'Payment Company',
        'user_id' => $user->id,
        'vat_enabled' => false,
        'currency' => 'RSD',
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Payment Client']);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'PAY-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'subtotal_original' => 100,
        'tax_original' => 0,
        'total_original' => 100,
        'subtotal_rsd' => 100,
        'tax_rsd' => 0,
        'total_rsd' => 100,
        'total' => 100,
        'status' => 'draft',
        'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);

    return [$user, $company, $client, $invoice];
}

it('marks an invoice paid with a date and records payment changes in audit history', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-mark@example.com');
    $this->actingAs($user);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'status' => 'paid',
        'paid_at' => '2026-10-12',
        'payment_note' => 'Uplata po izvodu 12/10',
    ])->assertOk()->assertJsonPath('paid_at', '2026-10-12T00:00:00.000000Z')->assertJsonPath('payment_note', 'Uplata po izvodu 12/10');

    $saved = Invoice::findOrFail($invoice->id);
    expect($saved->status)->toBe('paid');
    expect($saved->paid_at->toDateString())->toBe('2026-10-12');
    expect($saved->payment_note)->toBe('Uplata po izvodu 12/10');
    expect(InvoiceRevision::where('invoice_id', $invoice->id)->where('action_type', 'updated')->count())->toBe(1);
});

it('rejects a new paid status without a payment date', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-required@example.com');
    $this->actingAs($user);

    $this->putJson("/api/invoices/{$invoice->id}", ['status' => 'paid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('paid_at');
});

it('updates payment details and clears the active payment date when leaving paid status', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-edit@example.com');
    $invoice->update(['status' => 'paid', 'paid_at' => '2026-10-10', 'payment_note' => 'Prva napomena']);
    $this->actingAs($user);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'status' => 'paid',
        'paid_at' => '2026-10-11',
        'payment_note' => null,
    ])->assertOk();

    $this->putJson("/api/invoices/{$invoice->id}", ['status' => 'draft'])
        ->assertOk();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => 'draft',
        'paid_at' => null,
        'payment_note' => null,
    ]);
    expect(InvoiceRevision::where('invoice_id', $invoice->id)->where('action_type', 'updated')->count())->toBe(2);
});

it('allows a legacy paid invoice without a date to be completed through the payment update', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-legacy@example.com');
    $invoice->update(['status' => 'paid', 'paid_at' => null]);
    $this->actingAs($user);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'status' => 'paid',
        'paid_at' => '2026-10-09',
    ])->assertOk();

    expect(Invoice::findOrFail($invoice->id)->paid_at->toDateString())->toBe('2026-10-09');
});

it('uses paid_at as the KPO generation date suggestion', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-kpo@example.com');
    $invoice->update(['paid_at' => '2026-10-08']);
    $invoice->items()->create([
        'name' => 'Usluga',
        'description' => 'Usluga',
        'quantity' => 1,
        'price' => 100,
        'sub_total' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'income_category' => 'service',
    ]);
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2026')
        ->assertOk()
        ->assertJsonPath('entries.0.suggested_entry_date', '2026-10-08');
});

it('does not allow another company to change payment data', function () {
    [$user, $company, $client, $invoice] = paymentFixture('invoice-payment-owner@example.com');
    [$otherUser] = paymentFixture('invoice-payment-other@example.com');
    $this->actingAs($otherUser);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'status' => 'paid',
        'paid_at' => '2026-10-07',
    ])->assertForbidden();
});
