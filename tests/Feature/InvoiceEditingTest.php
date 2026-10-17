<?php

use App\Models\Invoice;
use App\Models\InvoiceRevision;
use App\Models\KpoEntry;
use App\Models\KpoEntryRevision;
use App\Models\User;

function editableInvoiceFixture(string $email = 'invoice-edit@example.com'): array
{
    $user = User::create([
        'first_name' => 'Edit',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = $user->companies()->create([
        'name' => 'Edit Test Company',
        'currency' => 'RSD',
        'vat_enabled' => false,
        'invoice_start_number' => 1,
        'payment_due_days' => 15,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Edit Client', 'country' => 'Srbija', 'client_type' => 'b2b']);

    return [$user, $company, $client];
}

it('edits an existing invoice, recalculates persisted totals and records history', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-edit-basic@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'payment_method' => 'bank_transfer',
        'vat_enabled_at_issue' => false,
        'include_in_kpo' => true,
        'items' => [[
            'name' => 'Stara stavka',
            'description' => 'Stara stavka',
            'quantity' => 1,
            'price' => 100,
            'income_category' => 'unclassified',
        ]],
    ])->assertCreated();

    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $item = $invoice->items->first();

    $this->putJson("/api/invoices/{$invoice->id}", [
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-001',
        'issue_date' => $invoice->issue_date->toDateString(),
        'due_date' => $invoice->due_date->toDateString(),
        'currency' => 'RSD',
        'payment_method' => 'bank_transfer',
        'vat_enabled_at_issue' => false,
        'include_in_kpo' => true,
        'items' => [[
            'id' => $item->id,
            'name' => 'Nova stavka',
            'description' => 'Nova stavka',
            'quantity' => 2,
            'price' => 125,
            'income_category' => 'service',
        ]],
    ])->assertOk()->assertJsonPath('items.0.income_category', 'service');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'total_original' => 250.00,
        'total_rsd' => 250.00,
        'vat_enabled_at_issue' => false,
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'id' => $item->id,
        'quantity' => 2,
        'price' => 125.00,
        'total' => 250.00,
        'income_category' => 'service',
    ]);
    expect(InvoiceRevision::where('invoice_id', $invoice->id)->where('action_type', 'created')->count())->toBe(1);
    expect(InvoiceRevision::where('invoice_id', $invoice->id)->where('action_type', 'updated')->count())->toBe(1);
});

it('requires explicit VAT resolution before editing a legacy invoice', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-edit-legacy@example.com');
    $this->actingAs($user);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'LEGACY-001',
        'issue_date' => now()->subDay(),
        'due_date' => now()->addDays(14),
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
        'vat_enabled_at_issue' => null,
    ]);

    $this->putJson("/api/invoices/{$invoice->id}", ['note' => 'Pokušaj izmene'])
        ->assertOk();

    $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => false,
        'note' => 'Istorijski status potvrđen',
    ])->assertOk();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'vat_enabled_at_issue' => false,
        'note' => 'Istorijski status potvrđen',
    ]);
});

it('safely synchronizes a generated KPO amount and preserves its adjustment', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-edit-kpo@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-KPO-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $item = $invoice->items->first();
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => $invoice->issue_date->year,
        'entry_number' => 1,
        'entry_date' => $invoice->issue_date,
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_type' => 'usluga',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'adjustment_rsd' => 5,
        'total_rsd' => 105,
    ]);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'items' => [['id' => $item->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 150, 'income_category' => 'service']],
        'vat_enabled_at_issue' => false,
    ])->assertOk();

    $entry->refresh();
    expect((string) $entry->amount_rsd)->toBe('150.00')
        ->and((string) $entry->adjustment_rsd)->toBe('5.00')
        ->and((string) $entry->total_rsd)->toBe('155.00');
    expect(KpoEntryRevision::where('kpo_entry_id', $entry->id)->where('action_type', 'regenerated')->count())->toBe(1);
});

it('rolls back invoice changes when a manual KPO entry would be overwritten', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-edit-kpo-manual@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-KPO-MANUAL-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $item = $invoice->items->first();
    $entry = KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id,
        'year' => $invoice->issue_date->year, 'entry_number' => 1, 'entry_date' => $invoice->issue_date,
        'source_type' => 'invoice', 'origin' => 'generated', 'income_type' => 'usluga', 'income_category' => 'service',
        'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100,
        'manually_edited_at' => now(), 'manually_edited_by' => $user->id,
    ]);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'items' => [['id' => $item->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service']],
        'vat_enabled_at_issue' => false,
    ])->assertStatus(409)
        ->assertJsonPath('conflict.reason', 'manual_edit')
        ->assertJsonPath('requires_confirmation', true);

    expect((string) $invoice->fresh()->items->first()->price)->toBe('100.00')
        ->and((string) $entry->fresh()->amount_rsd)->toBe('100.00')
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->count())->toBe(0);
});

it('does not allow another company to edit an invoice', function () {
    [$owner, $company, $client] = editableInvoiceFixture('invoice-edit-owner@example.com');
    $this->actingAs($owner);
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'TENANT-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'subtotal_original' => 0,
        'tax_original' => 0,
        'total_original' => 0,
        'subtotal_rsd' => 0,
        'tax_rsd' => 0,
        'total_rsd' => 0,
        'total' => 0,
        'status' => 'draft',
        'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);

    [$otherUser] = editableInvoiceFixture('invoice-edit-other@example.com');
    $this->actingAs($otherUser);
    $this->putJson("/api/invoices/{$invoice->id}", ['note' => 'neovlašćeno'])->assertForbidden();
});

it('returns invoice audit history without exposing other company history', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-history@example.com');
    $this->actingAs($user);
    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => 'HISTORY-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'fx_rate_to_rsd' => 1,
        'subtotal_original' => 0,
        'tax_original' => 0,
        'total_original' => 0,
        'subtotal_rsd' => 0,
        'tax_rsd' => 0,
        'total_rsd' => 0,
        'total' => 0,
        'status' => 'draft',
        'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);

    $this->getJson("/api/invoices/{$invoice->id}/history")
        ->assertOk()
        ->assertJsonStructure(['invoice', 'revisions']);
});

it('accepts both explicit VAT values and makes a newly eligible invoice available to KPO', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-edit-kpo-eligible@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'EDIT-KPO-ELIGIBLE-001',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'include_in_kpo' => true,
        'items' => [['name' => 'Stavka', 'description' => 'Stavka', 'quantity' => 1, 'price' => 100, 'income_category' => 'unclassified']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $item = $invoice->items->first();

    $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => true,
        'confirm_vat_status_change' => true,
    ])->assertOk()->assertJsonPath('vat_enabled_at_issue', true)
        ->assertJsonPath('tax_original', '20.00')
        ->assertJsonPath('total_original', '120.00');
    $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => false,
        'confirm_vat_status_change' => true,
        'items' => [['id' => $item->id, 'name' => 'Stavka', 'description' => 'Stavka', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertOk()->assertJsonPath('vat_enabled_at_issue', false)
        ->assertJsonPath('tax_original', '0.00')
        ->assertJsonPath('total_original', '100.00');

    $preview = $this->getJson('/api/kpo/generation-preview?year='.now()->year)->assertOk();
    expect($preview->json('entries'))->toHaveCount(1);
    expect($preview->json('entries.0.id'))->toBe($invoice->id);

    $this->postJson('/api/kpo/generate', [
        'year' => now()->year,
        'entries' => [[
            'invoice_id' => $invoice->id,
            'entry_date' => $invoice->issue_date->toDateString(),
        ]],
    ])->assertOk()->assertJsonCount(1, 'created');
});

it('allows a legacy invoice to retain its normalized number without changing the stored value', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-number-legacy@example.com');
    $this->actingAs($user);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => ' LEGACY-001 ',
        'issue_date' => now()->subDay(),
        'due_date' => now()->addDays(14),
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

    $this->putJson("/api/invoices/{$invoice->id}", [
        'invoice_number' => 'LEGACY-001',
        'vat_enabled_at_issue' => false,
    ])->assertOk();

    expect($invoice->fresh()->invoice_number)->toBe(' LEGACY-001 ');
});

it('allows a new invoice to retain its number and change it to a free normalized number', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-number-new@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => ' NEW-001 ',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Stavka', 'description' => 'Stavka', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::findOrFail($created->json('id'));

    $this->putJson("/api/invoices/{$invoice->id}", [
        'invoice_number' => 'NEW-001',
        'vat_enabled_at_issue' => false,
    ])->assertOk();
    expect($invoice->fresh()->invoice_number)->toBe('NEW-001');

    $this->putJson("/api/invoices/{$invoice->id}", [
        'invoice_number' => ' FREE-001 ',
        'vat_enabled_at_issue' => false,
    ])->assertOk();
    expect($invoice->fresh()->invoice_number)->toBe('FREE-001');
});

it('rejects a number used by another invoice in the same company as validation', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-number-duplicate@example.com');
    $this->actingAs($user);

    $first = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'NUMBER-001',
        'issue_date' => now(), 'due_date' => now()->addDays(15), 'currency' => 'RSD', 'fx_rate_to_rsd' => 1,
        'total' => 0, 'subtotal_original' => 0, 'tax_original' => 0, 'total_original' => 0,
        'subtotal_rsd' => 0, 'tax_rsd' => 0, 'total_rsd' => 0, 'status' => 'draft', 'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);
    $second = $first->replicate();
    $second->invoice_number = 'NUMBER-002';
    $second->save();

    $this->putJson("/api/invoices/{$second->id}", [
        'invoice_number' => ' NUMBER-001 ',
        'vat_enabled_at_issue' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('invoice_number')
        ->assertJsonPath('errors.invoice_number.0', 'Broj fakture već postoji u aktivnoj firmi.');

    expect($second->fresh()->invoice_number)->toBe('NUMBER-002');
});

it('does not allow another company to change an invoice number', function () {
    [$owner, $company, $client] = editableInvoiceFixture('invoice-number-owner@example.com');
    $this->actingAs($owner);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'TENANT-NUMBER-001',
        'issue_date' => now(), 'due_date' => now()->addDays(15), 'currency' => 'RSD', 'fx_rate_to_rsd' => 1,
        'total' => 0, 'subtotal_original' => 0, 'tax_original' => 0, 'total_original' => 0,
        'subtotal_rsd' => 0, 'tax_rsd' => 0, 'total_rsd' => 0, 'status' => 'draft', 'include_in_kpo' => true,
        'vat_enabled_at_issue' => false,
    ]);

    [$otherUser] = editableInvoiceFixture('invoice-number-other@example.com');
    $this->actingAs($otherUser);
    $this->putJson("/api/invoices/{$invoice->id}", [
        'invoice_number' => 'TENANT-NUMBER-002',
        'vat_enabled_at_issue' => false,
    ])->assertForbidden();

    expect($invoice->fresh()->invoice_number)->toBe('TENANT-NUMBER-001');
});

it('returns a structured category conflict and rolls back the invoice edit', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-category-conflict@example.com');
    $this->actingAs($user);

    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'KPO-CONFLICT-CATEGORY',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 100, 'income_category' => 'product']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $item = $invoice->items->first();
    KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'generated', 'income_category' => 'product', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100,
    ]);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'items' => [['id' => $item->id, 'name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
        'vat_enabled_at_issue' => false,
    ])->assertStatus(409)
        ->assertJsonPath('conflict.reason', 'category_change')
        ->assertJsonPath('conflict.proposed_entries.0.income_category', 'service')
        ->assertJsonPath('conflict.requires_regeneration', false);

    expect($item->fresh()->income_category)->toBe('product');
});

it('confirms structural removal of the last product and retains the service KPO entry', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-structural-remove-product@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-STRUCT-1',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(), 'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [
            ['name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 100, 'income_category' => 'product'],
            ['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service'],
        ],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $product = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year, 'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'product', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    $service = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year, 'entry_number' => 2, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 200, 'amount_rsd' => 200, 'adjustment_rsd' => 0, 'total_rsd' => 200]);
    $items = [['id' => $invoice->items->where('income_category', 'service')->first()->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service']];

    $conflict = $this->putJson("/api/invoices/{$invoice->id}", ['items' => $items, 'vat_enabled_at_issue' => false])->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", ['items' => $items, 'vat_enabled_at_issue' => false, 'kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $conflict->json('preview_fingerprint')]])->assertOk();

    expect(KpoEntry::withTrashed()->findOrFail($product->id)->trashed())->toBeTrue()
        ->and($service->fresh()->entry_number)->toBe(2)
        ->and($invoice->fresh()->items)->toHaveCount(1)
        ->and(KpoEntryRevision::where('kpo_entry_id', $product->id)->latest()->value('new_values'))->toHaveKey('removal_reason');
});

it('confirms a product to service category change without renumbering the retained history', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-category-switch@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-STRUCT-2', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(), 'currency' => 'RSD', 'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Stavka', 'description' => 'Stavka', 'quantity' => 1, 'price' => 100, 'income_category' => 'product']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $entry = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year, 'entry_number' => 7, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'product', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    $payload = ['items' => [['id' => $invoice->items->first()->id, 'name' => 'Stavka', 'description' => 'Stavka', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']], 'vat_enabled_at_issue' => false];
    $conflict = $this->putJson("/api/invoices/{$invoice->id}", $payload)->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", $payload + ['kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $conflict->json('preview_fingerprint')]])->assertOk();

    expect(KpoEntry::withTrashed()->findOrFail($entry->id)->trashed())->toBeTrue()
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->where('income_category', 'service')->count())->toBe(1)
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->where('income_category', 'service')->value('entry_number'))->not->toBe(7);
});

it('creates only the missing KPO category after explicit confirmation', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-add-category@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-STRUCT-3', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(), 'currency' => 'RSD', 'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year, 'entry_number' => 9, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    $payload = ['items' => [
        ['id' => $invoice->items->first()->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service'],
        ['name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 50, 'income_category' => 'product'],
    ], 'vat_enabled_at_issue' => false];
    $conflict = $this->putJson("/api/invoices/{$invoice->id}", $payload)->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", $payload + ['kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $conflict->json('preview_fingerprint')]])->assertOk();

    expect(KpoEntry::where('source_invoice_id', $invoice->id)->where('income_category', 'service')->count())->toBe(1)
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->where('income_category', 'product')->count())->toBe(1);
});

it('saves a no-op edit without creating a KPO synchronization revision', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-no-op@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-NO-OP',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15), 'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $entry = KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 5, 'total_rsd' => 105,
    ]);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'items' => [['id' => $invoice->items->first()->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
        'vat_enabled_at_issue' => false,
    ])->assertOk();

    expect($entry->refresh()->total_rsd)->toBe('105.00')
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->count())->toBe(0);
});

it('requires confirmation before excluding an invoice with KPO entries and then retains its history', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-exclusion@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-CONFLICT-EXCLUDE',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15), 'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $entry = KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100,
    ]);

    $conflict = $this->putJson("/api/invoices/{$invoice->id}", [
        'include_in_kpo' => false, 'vat_enabled_at_issue' => false,
    ])->assertStatus(409);
    $fingerprint = $conflict->json('preview_fingerprint');

    $this->putJson("/api/invoices/{$invoice->id}", [
        'include_in_kpo' => false, 'vat_enabled_at_issue' => false,
        'kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $fingerprint],
    ])->assertOk();

    expect($invoice->fresh()->include_in_kpo)->toBeFalse()
        ->and(KpoEntry::withTrashed()->findOrFail($entry->id)->trashed())->toBeTrue()
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->latest()->value('action_type'))->toBe('deleted');
});

it('requires confirmation before making an invoice VAT-ineligible for automatic KPO generation', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-vat-conflict@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-CONFLICT-VAT',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15), 'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100,
    ]);

    $conflict = $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => true,
        'confirm_vat_status_change' => true,
    ])->assertStatus(409);

    $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => true,
        'confirm_vat_status_change' => true,
        'kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $conflict->json('preview_fingerprint')],
    ])->assertOk();

    expect($invoice->fresh()->vat_enabled_at_issue)->toBeTrue();
});

it('uses the requested VAT snapshot for invoice calculations', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-vat-calculation-snapshot@example.com');
    $company->update(['vat_enabled' => true]);
    $this->actingAs($user);

    $disabled = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'VAT-CALC-OFF',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga bez PDV-a', 'description' => 'Usluga bez PDV-a', 'quantity' => 1, 'price' => 100]],
    ])->assertCreated();

    expect($disabled->json('tax_original'))->toBe('0.00')
        ->and($disabled->json('total_original'))->toBe('100.00');

    $company->update(['vat_enabled' => false]);
    $enabled = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'VAT-CALC-ON',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => true,
        'items' => [['name' => 'Usluga sa PDV-om', 'description' => 'Usluga sa PDV-om', 'quantity' => 1, 'price' => 100]],
    ])->assertCreated();

    expect($enabled->json('tax_original'))->toBe('20.00')
        ->and($enabled->json('total_original'))->toBe('120.00');

    $bothDisabled = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'VAT-CALC-BOTH-OFF',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Bez PDV-a', 'description' => 'Bez PDV-a', 'quantity' => 1, 'price' => 50]],
    ])->assertCreated();
    expect($bothDisabled->json('tax_original'))->toBe('0.00')->and($bothDisabled->json('total_original'))->toBe('50.00');

    $company->update(['vat_enabled' => true]);
    $bothEnabled = $this->postJson('/api/invoices', [
        'client_id' => $client->id,
        'invoice_number' => 'VAT-CALC-BOTH-ON',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD',
        'vat_enabled_at_issue' => true,
        'items' => [['name' => 'Sa PDV-om', 'description' => 'Sa PDV-om', 'quantity' => 1, 'price' => 50]],
    ])->assertCreated();
    expect($bothEnabled->json('tax_original'))->toBe('10.00')->and($bothEnabled->json('total_original'))->toBe('60.00');
});

it('soft-deletes all linked KPO categories when a confirmed VAT change makes the invoice ineligible', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-vat-kpo-structural@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'VAT-KPO-STRUCT',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(),
        'currency' => 'RSD', 'vat_enabled_at_issue' => false,
        'items' => [
            ['name' => 'Proizvod', 'description' => 'Proizvod', 'quantity' => 1, 'price' => 100, 'income_category' => 'product'],
            ['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service'],
        ],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $entries = collect(['product' => 100, 'service' => 200])->map(fn (int $amount, string $category) => KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => $category === 'product' ? 11 : 12, 'entry_date' => now()->toDateString(),
        'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => $category, 'currency' => 'RSD',
        'amount_original' => $amount, 'amount_rsd' => $amount, 'adjustment_rsd' => 0, 'total_rsd' => $amount,
    ]));
    $items = $invoice->items->where('income_category', 'service')->map(fn ($item) => [
        'id' => $item->id, 'name' => $item->name, 'description' => $item->description,
        'quantity' => 1, 'price' => 200, 'income_category' => 'service',
    ])->values()->all();

    $conflict = $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => true, 'confirm_vat_status_change' => true, 'items' => $items,
    ])->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", [
        'vat_enabled_at_issue' => true, 'confirm_vat_status_change' => true, 'items' => $items,
        'kpo_confirmation' => ['confirmed' => true, 'preview_fingerprint' => $conflict->json('preview_fingerprint')],
    ])->assertOk();

    foreach ($entries as $entry) {
        expect(KpoEntry::withTrashed()->findOrFail($entry->id)->trashed())->toBeTrue();
    }
});

it('requires confirmation and atomically soft-deletes linked KPO entries when deleting an invoice', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-delete-kpo@example.com');
    $this->actingAs($user);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'DELETE-KPO-001',
        'issue_date' => now(), 'due_date' => now()->addDays(15), 'currency' => 'RSD',
        'fx_rate_to_rsd' => 1, 'subtotal_original' => 100, 'tax_original' => 0,
        'total_original' => 100, 'subtotal_rsd' => 100, 'tax_rsd' => 0, 'total_rsd' => 100,
        'total' => 100, 'status' => 'draft', 'include_in_kpo' => true, 'vat_enabled_at_issue' => false,
    ]);
    $entry = KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 5, 'total_rsd' => 105,
    ]);
    KpoEntryRevision::create([
        'kpo_entry_id' => $entry->id, 'acting_user_id' => $user->id, 'action_type' => 'created',
        'new_values' => ['total_rsd' => '105.00'],
    ]);

    $this->deleteJson("/api/invoices/{$invoice->id}")
        ->assertStatus(409)
        ->assertJsonPath('requires_confirmation', true);
    expect(Invoice::find($invoice->id))->not->toBeNull()
        ->and($entry->fresh()->trashed())->toBeFalse();

    $this->deleteJson("/api/invoices/{$invoice->id}", ['confirm_kpo_removal' => true])
        ->assertNoContent();

    expect(Invoice::find($invoice->id))->toBeNull()
        ->and(KpoEntry::withTrashed()->findOrFail($entry->id)->trashed())->toBeTrue()
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->whereNull('deleted_at')->count())->toBe(0);
    $deletionRevision = KpoEntryRevision::where('kpo_entry_id', $entry->id)->latest()->firstOrFail();
    expect($deletionRevision->new_values['removal_reason'])->toBe('source_invoice_deleted');
});

it('supports explicit manual KPO overwrite and rejects a stale confirmation', function () {
    [$user, $company, $client] = editableInvoiceFixture('invoice-kpo-manual-confirmation@example.com');
    $this->actingAs($user);
    $created = $this->postJson('/api/invoices', [
        'client_id' => $client->id, 'invoice_number' => 'KPO-CONFLICT-MANUAL',
        'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(15), 'currency' => 'RSD',
        'vat_enabled_at_issue' => false,
        'items' => [['name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 100, 'income_category' => 'service']],
    ])->assertCreated();
    $invoice = Invoice::with('items')->findOrFail($created->json('id'));
    $entry = KpoEntry::create([
        'company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => now()->year,
        'entry_number' => 1, 'entry_date' => now()->toDateString(), 'source_type' => 'invoice',
        'origin' => 'manual', 'income_category' => 'service', 'currency' => 'RSD',
        'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 5, 'total_rsd' => 105,
        'manually_edited_at' => now(), 'manually_edited_by' => $user->id,
    ]);

    $payload = [
        'items' => [['id' => $invoice->items->first()->id, 'name' => 'Usluga', 'description' => 'Usluga', 'quantity' => 1, 'price' => 200, 'income_category' => 'service']],
        'vat_enabled_at_issue' => false,
    ];
    $conflict = $this->putJson("/api/invoices/{$invoice->id}", $payload)->assertStatus(409);
    $entry->update(['description' => 'Promena pre potvrde']);

    $this->putJson("/api/invoices/{$invoice->id}", $payload + [
        'kpo_confirmation' => [
            'confirmed' => true,
            'preview_fingerprint' => $conflict->json('preview_fingerprint'),
            'confirm_manual_overwrite' => true,
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('kpo_confirmation.preview_fingerprint');

    $freshConflict = $this->putJson("/api/invoices/{$invoice->id}", $payload)->assertStatus(409);
    $this->putJson("/api/invoices/{$invoice->id}", $payload + [
        'kpo_confirmation' => [
            'confirmed' => true,
            'preview_fingerprint' => $freshConflict->json('preview_fingerprint'),
            'confirm_manual_overwrite' => true,
        ],
    ])->assertOk();

    expect($entry->refresh()->amount_rsd)->toBe('200.00')
        ->and($entry->adjustment_rsd)->toBe('5.00')
        ->and($entry->total_rsd)->toBe('205.00')
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->latest()->first()->manual_edit_overwritten)->toBeTrue();
});
