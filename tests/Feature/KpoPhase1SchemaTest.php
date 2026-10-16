<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\User;
use App\Services\KpoEntryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

function phaseOneKpoContext(string $email): array
{
    $user = User::create([
        'first_name' => 'KPO',
        'last_name' => 'Phase One',
        'email' => $email,
        'username' => str_replace('@', '_', $email),
        'password' => 'password',
    ]);

    $company = Company::create([
        'name' => 'KPO Phase One Company',
        'currency' => 'RSD',
        'vat_enabled' => false,
        'invoice_start_number' => 1,
        'payment_due_days' => 15,
        'user_id' => $user->id,
    ]);

    $user->update(['active_company_id' => $company->id]);
    $client = $company->clients()->create([
        'name' => 'KPO Client',
        'country' => 'Srbija',
        'client_type' => 'b2b',
    ]);

    return [$user, $company, $client];
}

function phaseOneInvoice(Company $company, Client $client, string $number): Invoice
{
    return Invoice::create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'invoice_number' => $number,
        'issue_date' => '2026-10-16',
        'due_date' => '2026-10-31',
        'currency' => 'RSD',
        'total' => 100,
        'subtotal_original' => 100,
        'tax_original' => 0,
        'total_original' => 100,
        'subtotal_rsd' => 100,
        'tax_rsd' => 0,
        'total_rsd' => 100,
        'status' => 'draft',
        'include_in_kpo' => true,
    ]);
}

function phaseOneKpoAttributes(Company $company, ?int $invoiceId, int $number, ?string $category = null): array
{
    return [
        'company_id' => $company->id,
        'source_invoice_id' => $invoiceId,
        'year' => 2026,
        'entry_number' => $number,
        'entry_date' => '2026-10-16',
        'source_type' => $invoiceId ? 'invoice' : 'manual',
        'origin' => $invoiceId ? 'generated' : 'manual',
        'income_category' => $category,
        'currency' => 'RSD',
        'amount_original' => 100,
        'amount_rsd' => 100,
        'adjustment_rsd' => 0,
        'total_rsd' => 100,
        'description' => 'Phase one test entry',
    ];
}

it('exposes the phase one schema and preserves legacy nullable fields', function () {
    expect(Schema::hasColumn('kpo_entries', 'income_category'))->toBeTrue()
        ->and(Schema::hasColumn('kpo_entries', 'origin'))->toBeTrue()
        ->and(Schema::hasColumn('kpo_entries', 'manually_edited_at'))->toBeTrue()
        ->and(Schema::hasColumn('kpo_entries', 'manually_edited_by'))->toBeTrue();

    [$user, $company] = phaseOneKpoContext('kpo-schema@example.com');
    $entry = KpoEntry::create(phaseOneKpoAttributes($company, null, 1));

    expect($entry->refresh()->income_category)->toBeNull()
        ->and($entry->origin)->toBe('manual')
        ->and($entry->manually_edited_at)->toBeNull()
        ->and($entry->manually_edited_by)->toBeNull();
});

it('allows one product and one service entry for the same invoice but rejects a duplicate category', function () {
    [$user, $company, $client] = phaseOneKpoContext('kpo-category-unique@example.com');
    $invoice = phaseOneInvoice($company, $client, '2026-10-category');

    KpoEntry::create(phaseOneKpoAttributes($company, $invoice->id, 1, 'product'));
    KpoEntry::create(phaseOneKpoAttributes($company, $invoice->id, 2, 'service'));

    expect(fn () => KpoEntry::create(phaseOneKpoAttributes($company, $invoice->id, 3, 'product')))
        ->toThrow(QueryException::class);
});

it('keeps entry numbers unique per company and year', function () {
    [$user, $company] = phaseOneKpoContext('kpo-number-unique@example.com');
    KpoEntry::create(phaseOneKpoAttributes($company, null, 1));

    expect(fn () => KpoEntry::create(phaseOneKpoAttributes($company, null, 1, 'product')))
        ->toThrow(QueryException::class);
});

it('records provenance for new manual and generated entries and records later edits', function () {
    [$user, $company, $client] = phaseOneKpoContext('kpo-provenance@example.com');
    $this->actingAs($user);
    $service = app(KpoEntryService::class);

    $manual = $service->create($company, [
        'source_type' => 'manual',
        'entry_date' => '2026-10-16',
        'currency' => 'RSD',
        'amount_original' => 50,
        'amount_rsd' => 50,
    ]);
    expect($manual->origin)->toBe('manual');

    $invoice = phaseOneInvoice($company, $client, '2026-10-provenance');
    $generated = $service->create($company, [
        'source_type' => 'invoice',
        'invoice_id' => $invoice->id,
        'entry_date' => '2026-10-16',
    ]);
    expect($generated->origin)->toBe('generated')
        ->and($generated->manually_edited_at)->toBeNull();

    $service->update($generated, ['adjustment_rsd' => 5]);
    expect($generated->refresh()->manually_edited_at)->not->toBeNull()
        ->and($generated->manually_edited_by)->toBe($user->id);
});

it('refuses rollback when category rows cannot be represented by the legacy unique key', function () {
    [$user, $company, $client] = phaseOneKpoContext('kpo-rollback@example.com');
    $invoice = phaseOneInvoice($company, $client, '2026-10-rollback');
    KpoEntry::create(phaseOneKpoAttributes($company, $invoice->id, 1, 'product'));
    KpoEntry::create(phaseOneKpoAttributes($company, $invoice->id, 2, 'service'));

    $migration = require database_path('migrations/2026_10_16_000001_prepare_kpo_category_and_provenance.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('kpo_entries', 'income_category'))->toBeTrue()
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->count())->toBe(2)
        ->and(KpoEntry::where('source_invoice_id', $invoice->id)->pluck('income_category')->all())
        ->toBe(['product', 'service']);
});

it('resumes safely when the new columns exist but the old unique index remains', function () {
    $migration = require database_path('migrations/2026_10_16_000001_prepare_kpo_category_and_provenance.php');

    Schema::table('kpo_entries', static function ($table): void {
        $table->dropUnique('kpo_entries_company_source_category_unique');
        $table->dropIndex('kpo_entries_source_invoice_id_index');
        $table->unique('source_invoice_id', 'kpo_entries_source_invoice_id_unique');
    });

    $migration->up();

    $indexes = collect(Schema::getIndexes('kpo_entries'))->pluck('name')->all();
    expect($indexes)->toContain('kpo_entries_source_invoice_id_index')
        ->toContain('kpo_entries_company_source_category_unique')
        ->not->toContain('kpo_entries_source_invoice_id_unique');
});
