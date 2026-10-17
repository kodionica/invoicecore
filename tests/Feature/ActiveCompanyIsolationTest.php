<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

function activeCompanyIsolationContext(string $email): array
{
    $user = User::create([
        'first_name' => 'Active',
        'last_name' => 'Company',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $firstCompany = $user->companies()->create([
        'name' => 'Active Company One',
        'currency' => 'RSD',
        'vat_enabled' => false,
    ]);
    $secondCompany = $user->companies()->create([
        'name' => 'Active Company Two',
        'currency' => 'RSD',
        'vat_enabled' => false,
    ]);
    $user->update(['active_company_id' => $firstCompany->id]);

    $client = $secondCompany->clients()->create(['name' => 'Inactive Client']);
    $invoice = Invoice::create([
        'company_id' => $secondCompany->id,
        'client_id' => $client->id,
        'invoice_number' => 'INACTIVE-001',
        'issue_date' => now(),
        'due_date' => now()->addDays(15),
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'total' => 0,
        'subtotal_original' => 0,
        'tax_original' => 0,
        'total_original' => 0,
        'subtotal_rsd' => 0,
        'tax_rsd' => 0,
        'total_rsd' => 0,
        'status' => 'draft',
        'include_in_kpo' => false,
        'vat_enabled_at_issue' => false,
    ]);

    return [$user, $firstCompany, $secondCompany, $client, $invoice];
}

it('rejects invoice and document access for an owned but inactive company', function () {
    [$user, $firstCompany, , $client, $invoice] = activeCompanyIsolationContext('active-company-invoice@example.test');
    $this->actingAs($user);

    $this->getJson("/api/invoices/{$invoice->id}")->assertForbidden();
    $this->getJson("/api/invoices/{$invoice->id}/history")->assertForbidden();
    $this->getJson("/api/invoices/{$invoice->id}/pdf-versions")->assertForbidden();
    $this->getJson("/api/invoices/{$invoice->id}/email-history")->assertForbidden();
    $this->get("/api/invoices/{$invoice->id}/pdf")->assertForbidden();
    $this->get("/api/invoices/{$invoice->id}/pdf-versions/1/download")->assertForbidden();
    $this->postJson("/api/invoices/{$invoice->id}/email", ['recipient' => 'client@example.test'])->assertForbidden();
    $this->putJson("/api/invoices/{$invoice->id}", ['note' => 'Neovlašćena izmena'])->assertForbidden();
    $this->deleteJson("/api/invoices/{$invoice->id}")->assertForbidden();

    $this->getJson("/api/clients/{$client->id}")->assertForbidden();
    $this->putJson("/api/clients/{$client->id}", ['name' => 'Neovlašćen klijent'])->assertForbidden();
    $this->deleteJson("/api/clients/{$client->id}")->assertForbidden();

    expect($user->fresh()->active_company_id)->toBe($firstCompany->id);
});

it('allows access after the user explicitly switches the active company', function () {
    [$user, , $secondCompany, $client, $invoice] = activeCompanyIsolationContext('active-company-switch@example.test');
    $this->actingAs($user);

    $this->postJson('/api/companies/switch', ['company_id' => $secondCompany->id])
        ->assertOk()
        ->assertJsonPath('active_company_id', $secondCompany->id);

    $this->getJson("/api/invoices/{$invoice->id}")->assertOk();
    $this->getJson("/api/clients/{$client->id}")->assertOk();
});

it('rejects a different user even when the invoice company is active for its owner', function () {
    [$owner, , , , $invoice] = activeCompanyIsolationContext('active-company-owner@example.test');
    $other = User::create([
        'first_name' => 'Other',
        'last_name' => 'User',
        'email' => 'active-company-other@example.test',
        'username' => 'active-company-other',
        'password' => 'password',
    ]);
    $this->actingAs($other);

    $this->getJson("/api/invoices/{$invoice->id}")->assertForbidden();

    expect($owner->fresh()->active_company_id)->not->toBeNull();
});
