<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\KpoEntryRevision;
use App\Models\User;

function softDeleteKpoContext(string $email): array
{
    $user = User::create([
        'first_name' => 'KPO',
        'last_name' => 'Audit',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create(['name' => 'KPO Soft Delete', 'user_id' => $user->id]);
    $user->update(['active_company_id' => $company->id]);

    return [$user, $company];
}

it('validates category selection and replaces adjustments while recording audit events', function () {
    [$user, $company] = softDeleteKpoContext('kpo-audit-update@example.com');
    $this->actingAs($user);

    $entry = $this->postJson('/api/kpo', [
        'source_type' => 'manual',
        'entry_date' => '2026-10-15',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'adjustment_rsd' => 10,
    ])->assertCreated()->json();

    $this->putJson('/api/kpo/'.$entry['id'], [
        'income_category' => 'service',
        'adjustment_rsd' => -5,
    ])->assertOk()->assertJsonPath('total_rsd', '95.00');

    $this->putJson('/api/kpo/'.$entry['id'], ['income_category' => 'other'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('income_category');

    expect(KpoEntry::findOrFail($entry['id'])->income_category)->toBe('service')
        ->and(KpoEntry::findOrFail($entry['id'])->adjustment_rsd)->toBe('-5.00')
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry['id'])->orderBy('id')->pluck('action_type')->all())
        ->toEqual(['created', 'manually_adjusted']);
});

it('soft deletes manual entries, keeps history available and excludes them from active lists', function () {
    [$user, $company] = softDeleteKpoContext('kpo-soft-delete@example.com');
    $this->actingAs($user);

    $entry = $this->postJson('/api/kpo', [
        'source_type' => 'manual',
        'entry_date' => '2026-10-15',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
    ])->assertCreated()->json();

    $this->deleteJson('/api/kpo/'.$entry['id'])->assertOk();

    $this->assertSoftDeleted('kpo_entries', ['id' => $entry['id']]);
    $this->getJson('/api/kpo?year=2026')->assertOk()->assertJsonCount(0, 'entries');
    $this->getJson('/api/kpo/'.$entry['id'].'/history')
        ->assertOk()
        ->assertJsonPath('revisions.0.action_type', 'deleted');
});

it('does not allow deleting generated or cross-company entries', function () {
    [$user, $company] = softDeleteKpoContext('kpo-delete-owner@example.com');
    $generated = KpoEntry::create([
        'company_id' => $company->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-15',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    [$otherUser] = softDeleteKpoContext('kpo-delete-other@example.com');

    $this->actingAs($user);
    $this->deleteJson('/api/kpo/'.$generated->id)->assertForbidden();
    $this->actingAs($otherUser);
    $this->getJson('/api/kpo/'.$generated->id.'/history')->assertForbidden();
    $this->deleteJson('/api/kpo/'.$generated->id)->assertForbidden();

    expect(KpoEntry::findOrFail($generated->id)->deleted_at)->toBeNull();
});

it('requires an obsolete invoice-generated entry and records controlled removal', function () {
    [$user, $company] = softDeleteKpoContext('kpo-generated-removal@example.com');
    $client = $company->clients()->create(['name' => 'Removal client', 'client_type' => 'b2b']);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'REMOVE-1',
        'issue_date' => '2026-10-15', 'due_date' => '2026-10-30', 'currency' => 'RSD',
        'status' => 'draft', 'include_in_kpo' => true, 'vat_enabled_at_issue' => false,
        'fx_rate_to_rsd' => 1, 'subtotal_original' => 100, 'tax_original' => 0, 'total_original' => 100,
        'subtotal_rsd' => 100, 'tax_rsd' => 0, 'total_rsd' => 100, 'total' => 100,
    ]);
    $invoice->items()->create(['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'sub_total' => 100, 'tax_amount' => 0, 'total' => 100, 'income_category' => 'service']);
    $entry = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => 2026, 'entry_number' => 3, 'entry_date' => '2026-10-15', 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);

    $this->actingAs($user)->postJson('/api/kpo/'.$entry->id.'/remove-generated', ['confirm' => true, 'reason' => 'Faktura isključena iz KPO'])->assertUnprocessable();

    $invoice->update(['include_in_kpo' => false]);
    $this->actingAs($user)->postJson('/api/kpo/'.$entry->id.'/remove-generated', ['confirm' => true, 'reason' => 'Faktura isključena iz KPO'])->assertOk();

    expect(KpoEntry::withTrashed()->findOrFail($entry->id)->trashed())->toBeTrue()
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->latest()->value('action_type'))->toBe('deleted');
});
