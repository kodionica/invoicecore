<?php

use App\Models\KpoEntry;
use App\Models\KpoEntryRevision;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function phaseFiveBContext(string $email): array
{
    $user = User::create([
        'first_name' => 'Phase Five B',
        'last_name' => 'Test',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create(['name' => 'Phase Five B Company', 'user_id' => $user->id, 'vat_enabled' => false]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Phase Five B Client']);

    return [$user, $company, $client];
}

function phaseFiveBInvoice(Company $company, int $clientId, string $number, float $total = 100, string $currency = 'RSD', ?float $fxRate = 1): Invoice
{
    return Invoice::create([
        'company_id' => $company->id,
        'client_id' => $clientId,
        'invoice_number' => $number,
        'issue_date' => '2026-10-16',
        'due_date' => '2026-10-31',
        'currency' => $currency,
        'fx_rate_to_rsd' => $fxRate,
        'total' => $total,
        'total_original' => $currency === 'RSD' ? $total : $total / ($fxRate ?: 1),
        'total_rsd' => $total,
        'vat_enabled_at_issue' => false,
        'status' => 'paid',
        'include_in_kpo' => true,
    ]);
}

function phaseFiveBItem(Invoice $invoice, float $total, ?string $category): void
{
    $invoice->items()->create([
        'name' => 'Phase Five B item',
        'quantity' => 1,
        'price' => $total,
        'sub_total' => $total,
        'total' => $total,
        'tax_amount' => 0,
        'description' => 'Persisted regeneration amount',
        'income_category' => $category,
    ]);
}

it('requires explicit confirmation and updates generated entries with an audit revision', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-generated@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-024', 100);
    phaseFiveBItem($invoice, 100, 'product');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 50,
        'amount_rsd' => 50,
        'adjustment_rsd' => 0,
        'total_rsd' => 50,
    ]);
    $this->actingAs($user);
    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    $fingerprint = $preview->json('preview_fingerprint');

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
    ])->assertUnprocessable()->assertJsonValidationErrors('confirm_regeneration');

    $response = $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
    ])->assertOk()->assertJsonPath('updated.0.kpo_entry_id', $entry->id);

    expect($entry->refresh()->total_rsd)->toBe('100.00')
        ->and($entry->entry_number)->toBe(1)
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->count())->toBe(1)
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->first()->previous_values['total_rsd'])->toBe('50.00')
        ->and(KpoEntryRevision::where('kpo_entry_id', $entry->id)->first()->new_values['total_rsd'])->toBe('100.00')
        ->and($response->json('operation_id'))->toBeString();

    $afterRegeneration = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($afterRegeneration->json('invoices.0.actionable'))->toBeFalse()
        ->and($afterRegeneration->json('actionable_count'))->toBe(0)
        ->and($afterRegeneration->json('unchanged_count'))->toBe(1);
});

it('does not show a mixed invoice as actionable after initial KPO generation', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-initial-generation@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-024A', 300);
    phaseFiveBItem($invoice, 100, 'product');
    phaseFiveBItem($invoice, 200, 'service');
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', [
        'year' => 2026,
        'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2026-10-20']],
    ])->assertOk();

    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();

    expect($preview->json('invoices.0.actionable'))->toBeFalse()
        ->and($preview->json('actionable_count'))->toBe(0)
        ->and($preview->json('unchanged_count'))->toBe(1)
        ->and(collect($preview->json('invoices.0.proposed_entries'))->pluck('action')->all())
            ->toBe(['retain', 'retain']);
});

it('keeps an automatically synchronized invoice unchanged in regeneration preview and accepts a fresh confirmation', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-sync-preview@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-SYNC-1', 100);
    phaseFiveBItem($invoice, 100, 'service');
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', [
        'year' => 2026,
        'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2026-10-20']],
    ])->assertOk();

    $item = $invoice->fresh('items')->items->first();
    $this->putJson('/api/invoices/'.$invoice->id, [
        'items' => [[
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => 2,
            'price' => 100,
            'income_category' => 'service',
        ]],
        'vat_enabled_at_issue' => false,
    ])->assertOk();

    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($preview->json('invoices.0.actionable'))->toBeFalse()
        ->and($preview->json('invoices.0.proposed_entries.0.action'))->toBe('retain')
        ->and($preview->json('actionable_count'))->toBe(0);

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $preview->json('preview_fingerprint'),
        'confirm_regeneration' => true,
    ])->assertOk();
});

it('treats an intentionally soft-deleted obsolete category as informational', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-soft-deleted-preview@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-SOFT-1', 300);
    phaseFiveBItem($invoice, 100, 'product');
    phaseFiveBItem($invoice, 200, 'service');
    $product = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => 2026, 'entry_number' => 1, 'entry_date' => '2026-10-20', 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'product', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    $service = KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $invoice->id, 'year' => 2026, 'entry_number' => 2, 'entry_date' => '2026-10-20', 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 200, 'amount_rsd' => 200, 'adjustment_rsd' => 0, 'total_rsd' => 200]);
    $product->delete();
    $invoice->items()->where('income_category', 'product')->delete();
    $invoice->update(['total' => 200, 'total_original' => 200, 'total_rsd' => 200]);
    $this->actingAs($user);

    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($preview->json('invoices.0.actionable'))->toBeFalse()
        ->and($preview->json('invoices.0.deleted_categories'))->toBe(['product'])
        ->and($preview->json('invoices.0.proposed_entries.0.action'))->toBe('retain');
});

it('uses the same fingerprint snapshot for unchanged and actionable invoices', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-fingerprint-set@example.com');
    $unchanged = phaseFiveBInvoice($company, $client->id, 'PHASE-FP-1', 100);
    phaseFiveBItem($unchanged, 100, 'service');
    $changed = phaseFiveBInvoice($company, $client->id, 'PHASE-FP-2', 200);
    phaseFiveBItem($changed, 200, 'service');
    KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $unchanged->id, 'year' => 2026, 'entry_number' => 1, 'entry_date' => '2026-10-20', 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    KpoEntry::create(['company_id' => $company->id, 'source_invoice_id' => $changed->id, 'year' => 2026, 'entry_number' => 2, 'entry_date' => '2026-10-20', 'source_type' => 'invoice', 'origin' => 'generated', 'income_category' => 'service', 'currency' => 'RSD', 'amount_original' => 100, 'amount_rsd' => 100, 'adjustment_rsd' => 0, 'total_rsd' => 100]);
    $changed->items()->first()->update(['total' => 201]);
    $this->actingAs($user);

    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($preview->json('actionable_count'))->toBe(1);

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$unchanged->id, $changed->id],
        'preview_fingerprint' => $preview->json('preview_fingerprint'),
        'confirm_regeneration' => true,
    ])->assertOk();
});

it('compares the generated base amount while preserving a manual adjustment', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-base-amount@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-024B', 100);
    phaseFiveBItem($invoice, 100, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => '100.00',
        'amount_rsd' => '100.00',
        'adjustment_rsd' => '5.00',
        'total_rsd' => '105.00',
    ]);
    $this->actingAs($user);

    $unchanged = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($unchanged->json('invoices.0.actionable'))->toBeFalse()
        ->and($unchanged->json('invoices.0.proposed_entries.0.action'))->toBe('retain')
        ->and($unchanged->json('invoices.0.amount_differences.0.difference_rsd'))->toBe('0.00');

    $invoice->items()->first()->update(['total' => '100.01']);
    $changed = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    expect($changed->json('invoices.0.actionable'))->toBeTrue()
        ->and($changed->json('invoices.0.proposed_entries.0.action'))->toBe('update')
        ->and($changed->json('invoices.0.amount_differences.0.existing_amount_rsd'))->toBe('100.00')
        ->and($changed->json('invoices.0.amount_differences.0.proposed_amount_rsd'))->toBe('100.01')
        ->and($changed->json('invoices.0.amount_differences.0.difference_rsd'))->toBe('0.01')
        ->and($changed->json('invoices.0.proposed_entries.0.total_rsd'))->toBe('105.01');
});

it('protects manual edits unless manual overwrite is explicitly confirmed', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-manual@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-025', 100);
    phaseFiveBItem($invoice, 100, 'service');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'manual',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 60,
        'amount_rsd' => 60,
        'total_rsd' => 60,
        'manually_edited_at' => now(),
        'manually_edited_by' => $user->id,
    ]);
    $this->actingAs($user);
    $fingerprint = $this->getJson('/api/kpo/regeneration-preview?year=2026')->json('preview_fingerprint');
    $payload = [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
    ];

    $this->postJson('/api/kpo/regenerate', $payload)
        ->assertOk()
        ->assertJsonPath('protected.0.kpo_entry_id', $entry->id);
    expect($entry->refresh()->total_rsd)->toBe('60.00')
        ->and(KpoEntryRevision::count())->toBe(0);

    $fingerprint = $this->getJson('/api/kpo/regeneration-preview?year=2026')->json('preview_fingerprint');
    $this->postJson('/api/kpo/regenerate', $payload + [
        'preview_fingerprint' => $fingerprint,
        'confirm_manual_overwrite' => true,
    ])->assertOk()->assertJsonPath('updated.0.manual_edit_overwritten', true);

    expect($entry->refresh()->total_rsd)->toBe('100.00')
        ->and(KpoEntryRevision::first()->manual_edit_overwritten)->toBeTrue();
});

it('preserves a manual adjustment when an overwrite is explicitly confirmed', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-manual-adjustment@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-025A', 100);
    phaseFiveBItem($invoice, 100, 'service');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'manual',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 60,
        'amount_rsd' => 60,
        'adjustment_rsd' => 7.50,
        'total_rsd' => 67.50,
        'manually_edited_at' => now(),
        'manually_edited_by' => $user->id,
    ]);
    $this->actingAs($user);
    $fingerprint = $this->getJson('/api/kpo/regeneration-preview?year=2026')->json('preview_fingerprint');

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
        'confirm_manual_overwrite' => true,
    ])->assertOk()->assertJsonPath('updated.0.manual_edit_overwritten', true);

    expect($entry->refresh()->amount_rsd)->toBe('100.00')
        ->and($entry->adjustment_rsd)->toBe('7.50')
        ->and($entry->total_rsd)->toBe('107.50');
});

it('creates missing categories, preserves obsolete entries and blocks legacy conflicts', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-compatibility@example.com');
    $missing = phaseFiveBInvoice($company, $client->id, 'PHASE-026', 100);
    phaseFiveBItem($missing, 100, 'product');
    $obsolete = phaseFiveBInvoice($company, $client->id, 'PHASE-027', 100);
    phaseFiveBItem($obsolete, 100, 'product');
    $obsoleteEntry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $obsolete->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $legacy = phaseFiveBInvoice($company, $client->id, 'PHASE-028', 100);
    phaseFiveBItem($legacy, 100, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $legacy->id,
        'year' => 2026,
        'entry_number' => 2,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => null,
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $this->actingAs($user);
    $preview = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    $fingerprint = $preview->json('preview_fingerprint');

    $response = $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$missing->id, $obsolete->id, $legacy->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
    ])->assertOk();

    expect($response->json('created.0.invoice_id'))->toBe($missing->id)
        ->and($response->json('obsolete.0.entry_id'))->toBe($obsoleteEntry->id)
        ->and($response->json('blocked.0.invoice_id'))->toBe($legacy->id)
        ->and(KpoEntry::where('source_invoice_id', $missing->id)->count())->toBe(1)
        ->and($obsoleteEntry->refresh()->exists)->toBeTrue()
        ->and(KpoEntry::where('source_invoice_id', $legacy->id)->count())->toBe(1)
        ->and(DB::table('kpo_counters')->where('company_id', $company->id)->where('year', 2026)->value('next_number'))->toBe(5);
});

it('rejects a stale preview before changing KPO data', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-stale@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-029', 100);
    phaseFiveBItem($invoice, 100, 'product');
    $this->actingAs($user);
    $fingerprint = $this->getJson('/api/kpo/regeneration-preview?year=2026')->json('preview_fingerprint');
    $invoice->items()->first()->update(['income_category' => 'service']);

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('preview_fingerprint');

    expect(KpoEntry::where('source_invoice_id', $invoice->id)->count())->toBe(0);
});

it('rolls back regeneration changes and revisions when a revision write fails', function () {
    [$user, $company, $client] = phaseFiveBContext('phase-fiveb-rollback@example.com');
    $invoice = phaseFiveBInvoice($company, $client->id, 'PHASE-030', 100);
    phaseFiveBItem($invoice, 100, 'product');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 50,
        'amount_rsd' => 50,
        'total_rsd' => 50,
    ]);
    KpoEntryRevision::creating(function (): void {
        throw new RuntimeException('Simulated audit write failure.');
    });
    $this->actingAs($user);
    $fingerprint = $this->getJson('/api/kpo/regeneration-preview?year=2026')->json('preview_fingerprint');

    $this->postJson('/api/kpo/regenerate', [
        'year' => 2026,
        'invoice_ids' => [$invoice->id],
        'preview_fingerprint' => $fingerprint,
        'confirm_regeneration' => true,
    ])->assertStatus(500);

    expect($entry->refresh()->total_rsd)->toBe('50.00')
        ->and(KpoEntryRevision::count())->toBe(0);
    KpoEntryRevision::flushEventListeners();
});
