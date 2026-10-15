<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\User;

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
