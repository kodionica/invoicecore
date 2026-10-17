<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\User;
use App\Services\KpoEntryService;
use Illuminate\Support\Facades\DB;

function phaseThreeContext(string $email, bool $vatEnabled = false): array
{
    $user = User::create([
        'first_name' => 'Phase',
        'last_name' => 'Three',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);
    $company = Company::create([
        'name' => 'Phase Three Company',
        'user_id' => $user->id,
        'vat_enabled' => $vatEnabled,
    ]);
    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create(['name' => 'Phase Three Client']);

    return [$user, $company, $client];
}

function phaseThreeInvoice(Company $company, int $clientId, string $number, float $total = 100, string $currency = 'RSD', ?float $fxRate = 1): Invoice
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
        'vat_enabled_at_issue' => (bool) $company->vat_enabled,
        'status' => 'paid',
        'include_in_kpo' => true,
    ]);
}

function phaseThreeItem(Invoice $invoice, float $total, ?string $category): void
{
    $invoice->items()->create([
        'name' => 'Phase Three item',
        'quantity' => 1,
        'price' => $total,
        'sub_total' => $total,
        'total' => $total,
        'tax_amount' => 0,
        'description' => 'Persisted KPO amount',
        'income_category' => $category,
    ]);
}

function phaseThreePayload(Invoice $invoice, string $date = '2026-10-20'): array
{
    return ['year' => 2026, 'entries' => [['invoice_id' => $invoice->id, 'entry_date' => $date]]];
}

it('creates separate product and service entries from a mixed invoice', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-mixed@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-001', 300);
    phaseThreeItem($invoice, 100, 'product');
    phaseThreeItem($invoice, 200, 'service');
    $this->actingAs($user);

    $response = $this->postJson('/api/kpo/generate', phaseThreePayload($invoice));

    $response->assertOk()->assertJsonCount(2, 'created');
    expect(KpoEntry::where('source_invoice_id', $invoice->id)->orderBy('entry_number')->pluck('income_category')->all())
        ->toBe(['product', 'service'])
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->orderBy('entry_number')->pluck('entry_number')->all())
        ->toBe([1, 2]);
    expect(KpoEntry::where('source_invoice_id', $invoice->id)->pluck('origin')->unique()->all())->toBe(['generated']);
});

it('generates known categories for partial invoices and nothing for fully unclassified invoices', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-partial@example.com');
    $partial = phaseThreeInvoice($company, $client->id, 'PHASE-002', 150);
    phaseThreeItem($partial, 100, 'product');
    phaseThreeItem($partial, 50, null);
    $unclassified = phaseThreeInvoice($company, $client->id, 'PHASE-003', 75);
    phaseThreeItem($unclassified, 75, 'unclassified');
    $this->actingAs($user);

    $response = $this->postJson('/api/kpo/generate', phaseThreePayload($partial));
    $response->assertOk()->assertJsonCount(1, 'created')->assertJsonCount(1, 'partial');
    expect($response->json('unresolved.0.amount_rsd'))->toBe('50.00');

    $this->postJson('/api/kpo/generate', phaseThreePayload($unclassified))
        ->assertOk()
        ->assertJsonCount(0, 'created')
        ->assertJsonCount(1, 'partial');
    expect(KpoEntry::where('source_invoice_id', $unclassified->id)->exists())->toBeFalse();
});

it('blocks missing FX data and VAT-policy unsupported invoices', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-blocks@example.com');
    $missingFx = phaseThreeInvoice($company, $client->id, 'PHASE-004', 100, 'EUR', null);
    phaseThreeItem($missingFx, 100, 'service');
    $vatCompany = Company::create(['name' => 'VAT Phase Three', 'user_id' => $user->id, 'vat_enabled' => true]);
    $vatClient = $vatCompany->clients()->create(['name' => 'VAT Client']);
    $vatInvoice = phaseThreeInvoice($vatCompany, $vatClient->id, 'PHASE-005', 100);
    phaseThreeItem($vatInvoice, 100, 'service');
    $this->actingAs($user);
    $user->update(['active_company_id' => $company->id]);

    $this->postJson('/api/kpo/generate', phaseThreePayload($missingFx))
        ->assertOk()
        ->assertJsonPath('financially_blocked.0.invoice_id', $missingFx->id);
    $user->update(['active_company_id' => $vatCompany->id]);
    $this->actingAs($user->fresh());
    $this->postJson('/api/kpo/generate', phaseThreePayload($vatInvoice))
        ->assertOk()
        ->assertJsonPath('vat_policy_blocked.0.invoice_id', $vatInvoice->id);

    expect(KpoEntry::whereIn('source_invoice_id', [$missingFx->id, $vatInvoice->id])->count())->toBe(0);
});

it('skips existing categories and blocks legacy null invoice links', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-legacy@example.com');
    $categorized = phaseThreeInvoice($company, $client->id, 'PHASE-006', 300);
    phaseThreeItem($categorized, 100, 'product');
    phaseThreeItem($categorized, 200, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $categorized->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $legacy = phaseThreeInvoice($company, $client->id, 'PHASE-007', 100);
    phaseThreeItem($legacy, 100, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $legacy->id,
        'year' => 2026,
        'entry_number' => 2,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => null,
        'income_category' => null,
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $this->actingAs($user);

    $response = $this->postJson('/api/kpo/generate', phaseThreePayload($categorized));
    $response->assertOk()->assertJsonPath('skipped.0.income_category', 'product');
    expect(KpoEntry::where('source_invoice_id', $categorized->id)->count())->toBe(2);

    $this->postJson('/api/kpo/generate', phaseThreePayload($legacy))
        ->assertOk()
        ->assertJsonPath('legacy_blocked.0.invoice_id', $legacy->id);
    expect(KpoEntry::where('source_invoice_id', $legacy->id)->count())->toBe(1);
});

it('preserves manually edited category entries and is idempotent', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-idempotent@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-008', 300);
    phaseThreeItem($invoice, 100, 'product');
    phaseThreeItem($invoice, 200, 'service');
    $existing = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'adjustment_rsd' => 25,
        'total_rsd' => 125,
    ]);
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))->assertOk()->assertJsonCount(1, 'created');
    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))->assertOk()->assertJsonCount(0, 'created');
    expect($existing->refresh()->adjustment_rsd)->toBe('25.00')
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->count())->toBe(2);
});

it('keeps preview categories aligned with generation without reserving numbers', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-preview@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-009', 300);
    phaseThreeItem($invoice, 100, 'product');
    phaseThreeItem($invoice, 200, 'service');
    $this->actingAs($user);

    $preview = $this->getJson('/api/kpo/generation-preview?year=2026')->assertOk();
    $preview->assertJsonPath('new.0.income_category', 'product')
        ->assertJsonPath('new.1.income_category', 'service')
        ->assertJsonPath('entries.0.generation_status', 'ready');
    expect(KpoEntry::count())->toBe(0)
        ->and(DB::table('kpo_counters')->count())->toBe(0);

    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))
        ->assertOk()
        ->assertJsonCount(2, 'created');
});

it('blocks invoices without a VAT snapshot even when the company setting is later disabled', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-unknown-vat@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-011', 100);
    $invoice->update(['vat_enabled_at_issue' => null]);
    phaseThreeItem($invoice, 100, 'service');
    $company->update(['vat_enabled' => false]);
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))
        ->assertOk()
        ->assertJsonPath('vat_policy_blocked.0.reason', 'unknown_historical_vat_status');

    expect(KpoEntry::where('source_invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('uses the invoice VAT snapshot after the company setting changes', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-vat-snapshot-generation@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-013', 100);
    phaseThreeItem($invoice, 100, 'service');
    $company->update(['vat_enabled' => true]);
    $this->actingAs($user);

    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))
        ->assertOk()
        ->assertJsonCount(1, 'created');

    expect(KpoEntry::where('source_invoice_id', $invoice->id)->exists())->toBeTrue();

    $vatInvoice = phaseThreeInvoice($company, $client->id, 'PHASE-014', 100);
    $vatInvoice->update(['vat_enabled_at_issue' => true]);
    phaseThreeItem($vatInvoice, 100, 'service');
    $this->postJson('/api/kpo/generate', phaseThreePayload($vatInvoice))
        ->assertOk()
        ->assertJsonPath('vat_policy_blocked.0.invoice_id', $vatInvoice->id);
});

it('keeps fully unclassified invoices visible as unresolved but not actionable preview entries', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-unclassified-preview@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-015', 100);
    phaseThreeItem($invoice, 100, 'unclassified');
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2026')
        ->assertOk()
        ->assertJsonCount(0, 'entries')
        ->assertJsonPath('partial.0.invoice_id', $invoice->id);
});

it('requires confirmation for historical generation when manual or detached entries overlap the year', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-manual-overlap@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-012', 100);
    $invoice->update(['issue_date' => '2025-12-20']);
    phaseThreeItem($invoice, 100, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'year' => 2025,
        'entry_number' => 1,
        'entry_date' => '2025-12-01',
        'source_type' => 'manual',
        'origin' => 'manual',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2025&include_historical=true')
        ->assertOk()
        ->assertJsonPath('manual_overlap_warning.requires_confirmation', true)
        ->assertJsonPath('manual_overlap_warning.count', 1);

    $payload = [
        'year' => 2025,
        'include_historical' => true,
        'entries' => [['invoice_id' => $invoice->id, 'entry_date' => '2025-12-20']],
    ];
    $this->postJson('/api/kpo/generate', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirm_manual_overlap');

    $this->postJson('/api/kpo/generate', $payload + ['confirm_manual_overlap' => true])
        ->assertOk()
        ->assertJsonCount(1, 'created');

    expect(KpoEntry::where('company_id', $company->id)->whereNull('source_invoice_id')->count())->toBe(1);
});

it('rolls back all category entries when an unexpected creation failure occurs', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-rollback@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-010', 300);
    phaseThreeItem($invoice, 100, 'product');
    phaseThreeItem($invoice, 200, 'service');
    KpoEntry::creating(function (KpoEntry $entry): void {
        if ($entry->income_category === 'service') {
            throw new RuntimeException('Simulated category insert failure.');
        }
    });
    $this->actingAs($user);

    expect(fn () => app(KpoEntryService::class)->generateFromInvoices($company, 2026, [
        ['invoice_id' => $invoice->id, 'entry_date' => '2026-10-20'],
    ]))->toThrow(RuntimeException::class);

    expect(KpoEntry::where('source_invoice_id', $invoice->id)->count())->toBe(0);
    KpoEntry::flushEventListeners();
});

it('previews unchanged, changed, missing and obsolete KPO categories without mutation', function () {
    [$user, $company, $client] = phaseThreeContext('phase-fivea-diff@example.com');
    $changed = phaseThreeInvoice($company, $client->id, 'PHASE-016', 300);
    phaseThreeItem($changed, 100, 'product');
    phaseThreeItem($changed, 200, 'service');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $changed->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'product',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $changed->id,
        'year' => 2026,
        'entry_number' => 2,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 150,
        'amount_rsd' => 150,
        'total_rsd' => 150,
    ]);
    $missing = phaseThreeInvoice($company, $client->id, 'PHASE-017', 100);
    phaseThreeItem($missing, 100, 'product');
    $obsolete = phaseThreeInvoice($company, $client->id, 'PHASE-018', 100);
    phaseThreeItem($obsolete, 100, 'product');
    KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $obsolete->id,
        'year' => 2026,
        'entry_number' => 3,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => 'service',
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $beforeEntries = KpoEntry::query()->where('company_id', $company->id)->get()->toJson();
    $beforeCounters = DB::table('kpo_counters')->where('company_id', $company->id)->get()->toJson();
    $beforeInvoices = Invoice::query()->where('company_id', $company->id)->get()->toJson();
    $this->actingAs($user);

    $response = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();

    $response->assertJsonPath('read_only', true)
        ->assertJsonPath('invoices.0.proposed_entries.0.action', 'retain')
        ->assertJsonPath('invoices.0.proposed_entries.1.action', 'update')
        ->assertJsonPath('invoices.0.amount_differences.1.difference_rsd', '50.00');
    expect($response->json('invoices'))->toHaveCount(3);
    expect(collect($response->json('invoices'))->firstWhere('invoice_id', $missing->id)['missing_categories'])
        ->toBe(['product']);
    expect(collect($response->json('invoices'))->firstWhere('invoice_id', $obsolete->id)['obsolete_categories'][0]['income_category'])
        ->toBe('service');
    expect(KpoEntry::query()->where('company_id', $company->id)->get()->toJson())->toBe($beforeEntries)
        ->and(DB::table('kpo_counters')->where('company_id', $company->id)->get()->toJson())->toBe($beforeCounters)
        ->and(Invoice::query()->where('company_id', $company->id)->get()->toJson())->toBe($beforeInvoices);
});

it('reports manual edits and legacy category conflicts explicitly', function () {
    [$user, $company, $client] = phaseThreeContext('phase-fivea-legacy@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-019', 100);
    phaseThreeItem($invoice, 100, 'service');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
        'year' => 2026,
        'entry_number' => 1,
        'entry_date' => '2026-10-20',
        'source_type' => 'invoice',
        'origin' => 'generated',
        'income_category' => null,
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'total_rsd' => 100,
    ]);
    $entry->update(['manually_edited_at' => now(), 'manually_edited_by' => $user->id]);
    $this->actingAs($user);

    $response = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();

    $response->assertJsonPath('invoices.0.status', 'blocked')
        ->assertJsonPath('invoices.0.legacy_classification_conflicts.0.reason', 'legacy_null_income_category_requires_resolution')
        ->assertJsonPath('invoices.0.existing_entries.0.manually_edited', true);
    expect(collect($response->json('invoices.0.proposed_actions'))->pluck('action')->all())
        ->toContain('resolve_legacy_category');
});

it('reports partial, financial and VAT blocking states without generating entries', function () {
    [$user, $company, $client] = phaseThreeContext('phase-fivea-blocks@example.com');
    $partial = phaseThreeInvoice($company, $client->id, 'PHASE-020', 150);
    phaseThreeItem($partial, 100, 'product');
    phaseThreeItem($partial, 50, null);
    $missingFx = phaseThreeInvoice($company, $client->id, 'PHASE-021', 100, 'EUR', null);
    phaseThreeItem($missingFx, 100, 'service');
    $vat = phaseThreeInvoice($company, $client->id, 'PHASE-022', 100);
    $vat->update(['vat_enabled_at_issue' => true]);
    phaseThreeItem($vat, 100, 'service');
    $otherUser = User::create([
        'first_name' => 'Other',
        'last_name' => 'Company',
        'email' => 'phase-fivea-other@example.com',
        'username' => 'phase-fivea-other',
        'password' => 'password',
    ]);
    $otherCompany = Company::create(['name' => 'Other Company', 'user_id' => $otherUser->id]);
    $otherClient = $otherCompany->clients()->create(['name' => 'Other Client']);
    $otherInvoice = phaseThreeInvoice($otherCompany, $otherClient->id, 'PHASE-023', 100);
    phaseThreeItem($otherInvoice, 100, 'service');
    $this->actingAs($user);

    $response = $this->getJson('/api/kpo/regeneration-preview?year=2026')->assertOk();
    $reports = collect($response->json('invoices'));

    expect($reports->pluck('invoice_id')->all())->not->toContain($otherInvoice->id)
        ->and($reports->firstWhere('invoice_id', $partial->id)['status'])->toBe('partial')
        ->and($reports->firstWhere('invoice_id', $partial->id)['unresolved']['amount_rsd'])->toBe('50.00')
        ->and($reports->firstWhere('invoice_id', $missingFx->id)['blocking_reasons'])->toContain('missing_persisted_fx_rate')
        ->and($reports->firstWhere('invoice_id', $vat->id)['blocking_reasons'])->toContain('vat_policy_not_approved')
        ->and(KpoEntry::count())->toBe(0);
});

it('does not recreate a soft-deleted category entry during generation', function () {
    [$user, $company, $client] = phaseThreeContext('phase-three-soft-deleted@example.com');
    $invoice = phaseThreeInvoice($company, $client->id, 'PHASE-SOFT-001', 100);
    phaseThreeItem($invoice, 100, 'service');
    $entry = KpoEntry::create([
        'company_id' => $company->id,
        'source_invoice_id' => $invoice->id,
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
    $entry->delete();
    $this->actingAs($user);

    $this->getJson('/api/kpo/generation-preview?year=2026')
        ->assertOk()
        ->assertJsonCount(0, 'entries');
    $this->postJson('/api/kpo/generate', phaseThreePayload($invoice))
        ->assertOk()
        ->assertJsonPath('skipped.0.income_category', 'service');

    expect(KpoEntry::query()->count())->toBe(0)
        ->and(KpoEntry::withTrashed()->whereKey($entry->id)->count())->toBe(1);
});
