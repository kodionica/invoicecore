<?php

namespace App\Services;

use App\Exceptions\KpoInvoiceConflictException;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use App\Models\KpoEntryRevision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class KpoEntryService
{
    public function __construct(private readonly KpoAmountCalculationService $amountCalculator)
    {
    }

    public function create(Company $company, array $data): KpoEntry
    {
        return DB::transaction(function () use ($company, $data): KpoEntry {
            $invoice = null;
            if (($data['source_type'] ?? 'manual') === 'invoice') {
                $invoice = Invoice::query()
                    ->whereKey($data['invoice_id'])
                    ->where('company_id', $company->id)
                    ->firstOrFail();

                if (KpoEntry::withTrashed()->where('source_invoice_id', $invoice->id)->exists()) {
                    abort(422, 'Za izabranu fakturu KPO zapis već postoji.');
                }

                if (! $invoice->include_in_kpo) {
                    abort(422, 'Izabrana faktura je isključena iz KPO evidencije.');
                }
            }

            $entryDate = Carbon::parse($data['entry_date']);
            $entryNumber = $this->nextNumber($company->id, (int) $entryDate->year);
            $amountOriginal = $invoice ? (float) ($invoice->total_original ?? $invoice->total) : (float) $data['amount_original'];
            $amountRsd = $invoice ? (float) ($invoice->total_rsd ?? $invoice->total) : (float) $data['amount_rsd'];
            $adjustment = (float) ($data['adjustment_rsd'] ?? 0);

            $entry = KpoEntry::create([
                'company_id' => $company->id,
                'source_invoice_id' => $invoice?->id,
                'year' => $entryDate->year,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate->toDateString(),
                'source_type' => $data['source_type'],
                'origin' => $data['origin'] ?? (($data['source_type'] ?? 'manual') === 'invoice' ? 'generated' : 'manual'),
                'income_type' => $data['income_type'] ?? ($invoice ? 'usluga' : null),
                'income_category' => $data['income_category'] ?? null,
                'currency' => $invoice?->currency ?? strtoupper($data['currency'] ?? 'RSD'),
                'amount_original' => round($amountOriginal, 2),
                'amount_rsd' => round($amountRsd, 2),
                'adjustment_rsd' => round($adjustment, 2),
                'total_rsd' => round($amountRsd + $adjustment, 2),
                'description' => $data['description'] ?? null,
            ]);

            KpoEntryRevision::create([
                'kpo_entry_id' => $entry->id,
                'acting_user_id' => auth()->id(),
                'action_type' => 'created',
                'previous_values' => null,
                'new_values' => $this->revisionValues($entry),
                'manual_edit_overwritten' => false,
            ]);

            return $entry;
        });
    }

    public function update(KpoEntry $entry, array $data): KpoEntry
    {
        return DB::transaction(function () use ($entry, $data): KpoEntry {
            $previous = $this->revisionValues($entry);
            $previousAdjustment = (string) $entry->adjustment_rsd;
            $entry->fill($data);

            if ($entry->source_type === 'invoice') {
                $invoice = $entry->sourceInvoice()->firstOrFail();
                $entry->amount_original = round((float) ($invoice->total_original ?? $invoice->total), 2);
                $entry->amount_rsd = round((float) ($invoice->total_rsd ?? $invoice->total), 2);
                $entry->currency = $invoice->currency;
            }

            if ($entry->entry_date->year !== $entry->year) {
                abort(422, 'Datum KPO zapisa ne može promeniti poslovnu godinu bez novog numerisanja.');
            }

            $entry->year = $entry->entry_date->year;
            $entry->total_rsd = round((float) $entry->amount_rsd + (float) ($entry->adjustment_rsd ?? 0), 2);

            $nextValues = $this->revisionValues($entry);
            if ($previous === $nextValues) {
                return $entry->refresh();
            }

            $entry->manually_edited_at = now();
            $entry->manually_edited_by = auth()->id();
            $entry->save();

            KpoEntryRevision::create([
                'kpo_entry_id' => $entry->id,
                'acting_user_id' => auth()->id(),
                'action_type' => $previousAdjustment !== (string) $entry->adjustment_rsd ? 'manually_adjusted' : 'updated',
                'previous_values' => $previous,
                'new_values' => $this->revisionValues($entry),
                'manual_edit_overwritten' => false,
            ]);

            return $entry->refresh();
        });
    }

    /**
     * Update only safe, generated invoice-linked KPO snapshots after an invoice edit.
     * Category changes, eligibility changes and manual edits require the explicit
     * regeneration flow instead of being silently resolved here.
     *
     * @return array{status: string, updated: array<int, int>}
     */
    public function synchronizeAfterInvoiceUpdate(
        Invoice $invoice,
        bool $allowManualOverwrite = false,
        bool $allowEligibilityConflict = false,
        bool $allowStructuralChanges = false
    ): array
    {
        $invoice->load('items');
        $entries = KpoEntry::withTrashed()
            ->where('company_id', $invoice->company_id)
            ->where('source_invoice_id', $invoice->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($entries->isEmpty()) {
            return ['status' => 'unlinked', 'updated' => []];
        }

        if (! $invoice->include_in_kpo) {
            if (! $allowEligibilityConflict) {
                throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'kpo_exclusion'));
            }

            $removed = [];
            foreach ($entries as $entry) {
                if ($entry->trashed()) {
                    continue;
                }
                $this->assertCanRemoveEntry($entry, $allowManualOverwrite);
                $this->softDeleteInvoiceEntry($entry, 'invoice_excluded_from_kpo');
                $removed[] = $entry->id;
            }

            return ['status' => 'removed', 'updated' => [], 'removed' => $removed];
        }

        if ($entries->contains(fn (KpoEntry $entry): bool => $entry->income_category === null)) {
            throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'legacy_category'));
        }

        if ($invoice->vat_enabled_at_issue === null || (bool) $invoice->vat_enabled_at_issue) {
            if (! $allowEligibilityConflict) {
                throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'vat_ineligibility'));
            }

            $removed = [];
            foreach ($entries as $entry) {
                if ($entry->trashed()) {
                    continue;
                }
                $this->assertCanRemoveEntry($entry, $allowManualOverwrite);
                $this->softDeleteInvoiceEntry($entry, 'invoice_vat_ineligible');
                $removed[] = $entry->id;
            }

            return ['status' => 'removed', 'updated' => [], 'removed' => $removed];
        }

        $calculation = $this->amountCalculator->calculate($invoice);
        if (($calculation['generation_readiness']['status'] ?? null) === 'blocked') {
            throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'financial_block'));
        }

        $expectedCategories = collect(['product', 'service'])
            ->filter(fn (string $category): bool => ($calculation['categories'][$category]['amount_rsd'] ?? '0.00') !== '0.00')
            ->values();
        $existingCategories = $entries->pluck('income_category')->filter()->unique()->values();

        $missingCategories = $expectedCategories->diff($existingCategories)->values();
        $deletedExpectedCategories = $entries
            ->filter(fn (KpoEntry $entry): bool => $entry->trashed() && $expectedCategories->contains($entry->income_category))
            ->pluck('income_category')
            ->unique();
        if ($deletedExpectedCategories->isNotEmpty()) {
            throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'deleted_entry'));
        }
        $obsoleteEntries = $entries->filter(fn (KpoEntry $entry): bool => ! $entry->trashed()
            && in_array($entry->income_category, ['product', 'service'], true)
            && ! $expectedCategories->contains($entry->income_category));

        if (($missingCategories->isNotEmpty() || $obsoleteEntries->isNotEmpty()) && ! $allowStructuralChanges) {
            throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'category_change'));
        }

        foreach ($obsoleteEntries as $entry) {
            $this->assertCanRemoveEntry($entry, $allowManualOverwrite);
            $this->softDeleteInvoiceEntry($entry, 'category_removed_from_invoice');
        }

        foreach ($missingCategories as $category) {
            $deletedMatch = $entries->first(fn (KpoEntry $entry): bool => $entry->trashed()
                && $entry->income_category === $category);
            if ($deletedMatch) {
                throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'deleted_entry'));
            }
            $amounts = $calculation['categories'][$category];
            $this->createInvoiceCategoryEntry($invoice, $category, $amounts);
        }

        $updated = [];
        foreach ($entries as $entry) {
            if ($entry->trashed()) {
                continue;
            }

            $amounts = $calculation['categories'][$entry->income_category];
            $nextTotal = $this->decimalSum($amounts['amount_rsd'], $entry->adjustment_rsd);
            $changed = (string) $entry->amount_original !== (string) $amounts['amount_original']
                || (string) $entry->amount_rsd !== (string) $amounts['amount_rsd']
                || (string) $entry->total_rsd !== $nextTotal;

            if (! $changed) {
                continue;
            }

            $manuallyEdited = $this->isManuallyEdited($entry);
            if ($manuallyEdited && ! $allowManualOverwrite) {
                throw new KpoInvoiceConflictException($this->invoiceEditConflict($invoice, 'manual_edit'));
            }

            $previous = $this->revisionValues($entry);
            $entry->update([
                'amount_original' => $amounts['amount_original'],
                'amount_rsd' => $amounts['amount_rsd'],
                'total_rsd' => $nextTotal,
                'currency' => $invoice->currency,
            ]);
            KpoEntryRevision::create([
                'kpo_entry_id' => $entry->id,
                'acting_user_id' => auth()->id(),
                'action_type' => 'regenerated',
                'previous_values' => $previous,
                'new_values' => $this->revisionValues($entry->refresh()),
                'manual_edit_overwritten' => $manuallyEdited,
            ]);
            $updated[] = $entry->id;
        }

        return ['status' => ($updated === [] && $missingCategories->isEmpty() && $obsoleteEntries->isEmpty()) ? 'unchanged' : 'updated', 'updated' => $updated];
    }

    private function assertCanRemoveEntry(KpoEntry $entry, bool $allowManualOverwrite): void
    {
        if ($this->isManuallyEdited($entry) && ! $allowManualOverwrite) {
            throw new KpoInvoiceConflictException($this->invoiceEditConflict($entry->sourceInvoice()->firstOrFail(), 'manual_edit'));
        }
    }

    private function softDeleteInvoiceEntry(KpoEntry $entry, string $reason): void
    {
        $previous = $this->revisionValues($entry);
        $entry->delete();
        KpoEntryRevision::create([
            'kpo_entry_id' => $entry->id,
            'acting_user_id' => auth()->id(),
            'action_type' => 'deleted',
            'previous_values' => $previous,
            'new_values' => ['removal_reason' => $reason],
            'manual_edit_overwritten' => $this->isManuallyEdited($entry),
        ]);
    }

    private function createInvoiceCategoryEntry(Invoice $invoice, string $category, array $amounts): KpoEntry
    {
        $entryDate = $invoice->issue_date;
        $entry = KpoEntry::create([
            'company_id' => $invoice->company_id,
            'source_invoice_id' => $invoice->id,
            'year' => $entryDate->year,
            'entry_number' => $this->nextNumber($invoice->company_id, (int) $entryDate->year),
            'entry_date' => $entryDate->toDateString(),
            'source_type' => 'invoice',
            'origin' => 'generated',
            'income_type' => $category === 'product' ? 'proizvod' : 'usluga',
            'income_category' => $category,
            'currency' => $invoice->currency,
            'amount_original' => $amounts['amount_original'],
            'amount_rsd' => $amounts['amount_rsd'],
            'adjustment_rsd' => '0.00',
            'total_rsd' => $amounts['amount_rsd'],
            'description' => 'Faktura '.$invoice->invoice_number.' - '.($category === 'product' ? 'proizvodi' : 'usluge'),
        ]);
        KpoEntryRevision::create([
            'kpo_entry_id' => $entry->id,
            'acting_user_id' => auth()->id(),
            'action_type' => 'created',
            'previous_values' => null,
            'new_values' => $this->revisionValues($entry),
            'manual_edit_overwritten' => false,
        ]);

        return $entry;
    }

    public function delete(KpoEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            if ($entry->source_type !== 'manual') {
                throw ValidationException::withMessages([
                    'entry' => 'Automatski KPO zapisi se ne mogu obrisati ovom radnjom.',
                ]);
            }

            if ($entry->trashed()) {
                return;
            }

            $previous = $this->revisionValues($entry);
            $entry->delete();
            KpoEntryRevision::create([
                'kpo_entry_id' => $entry->id,
                'acting_user_id' => auth()->id(),
                'action_type' => 'deleted',
                'previous_values' => $previous,
                'new_values' => null,
                'manual_edit_overwritten' => false,
            ]);
        });
    }

    public function removeGeneratedEntry(KpoEntry $entry, string $reason): void
    {
        DB::transaction(function () use ($entry, $reason): void {
            $entry = KpoEntry::query()->withTrashed()->lockForUpdate()->findOrFail($entry->id);
            if ($entry->source_type !== 'invoice' || $entry->trashed()) {
                throw ValidationException::withMessages(['entry' => 'Samo aktivni invoice KPO zapis može biti kontrolisano uklonjen.']);
            }

            $invoice = $entry->sourceInvoice()->with('items')->firstOrFail();
            $calculation = $this->amountCalculator->calculate($invoice);
            $categoryAmount = $calculation['categories'][$entry->income_category]['amount_rsd'] ?? '0.00';
            $stillEligible = $invoice->include_in_kpo
                && $invoice->vat_enabled_at_issue === false
                && ($calculation['generation_readiness']['status'] ?? null) !== 'blocked'
                && $categoryAmount !== '0.00';

            if ($stillEligible) {
                throw ValidationException::withMessages([
                    'entry' => 'KPO zapis ne može biti uklonjen dok faktura i dalje sadrži prihode ove kategorije. Prvo izmenite fakturu.',
                ]);
            }

            $this->softDeleteInvoiceEntry($entry, $reason);
        });
    }

    /**
     * Soft-delete all active invoice-linked entries while the caller owns the
     * surrounding transaction. This keeps the source deletion atomic and
     * preserves each entry's number, amounts and audit history.
     *
     * @return array<int, int>
     */
    public function softDeleteEntriesForInvoice(Invoice $invoice, string $reason): array
    {
        $entries = KpoEntry::query()
            ->where('company_id', $invoice->company_id)
            ->where('source_invoice_id', $invoice->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $deleted = [];
        foreach ($entries as $entry) {
            $this->softDeleteInvoiceEntry($entry, $reason);
            $deleted[] = $entry->id;
        }

        return $deleted;
    }

    public function previewFromInvoices(Company $company, int $year, bool $includeHistorical = false): array
    {
        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->where('include_in_kpo', true)
            ->with(['items', 'kpoEntries' => static fn ($query) => $query->withTrashed()])
            ->orderBy('issue_date')
            ->orderBy('invoice_number');

        if (! $includeHistorical) {
            $query->whereYear('issue_date', $year);
        }

        $invoices = $query->get();
        $report = $this->emptyGenerationReport();
        $candidateInvoices = collect();
        $manualOverlapWarning = $includeHistorical
            ? $this->manualOverlapWarning($company, $year)
            : null;

        foreach ($invoices as $invoice) {
            $eligibility = $this->generationEligibility($company, $invoice);
            $invoice->setAttribute('suggested_entry_date', $invoice->issue_date?->toDateString());
            $invoice->setAttribute('generation_status', $eligibility['status']);
            $invoice->setAttribute('generation_categories', $eligibility['new_categories']);
            $invoice->setAttribute('existing_categories', $eligibility['skipped_categories']);
            $invoice->setAttribute('unresolved_amount_rsd', $eligibility['calculation']['unresolved_amount_rsd']);
            $invoice->setAttribute('rounding_residual_rsd', $eligibility['calculation']['category_rounding_residual_rsd']);
            $invoice->setAttribute('reconciliation_status', $eligibility['calculation']['reconciliation_status']);
            $invoice->setAttribute('has_unallocated_rounding_residual', $eligibility['calculation']['has_unallocated_rounding_residual']);

            foreach ($eligibility['new_categories'] as $category) {
                $report['new'][] = $this->categoryReportItem($invoice, $category);
            }
            foreach ($eligibility['skipped_categories'] as $category) {
                $report['skipped'][] = $this->categoryReportItem($invoice, $category);
            }

            if ($eligibility['status'] === 'legacy_blocked') {
                $report['legacy_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
            } elseif ($eligibility['status'] === 'financially_blocked') {
                $report['financially_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
            } elseif ($eligibility['status'] === 'vat_policy_blocked') {
                $report['vat_policy_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
            } elseif ($eligibility['status'] === 'partial') {
                $report['partial'][] = $this->invoiceReportItem($invoice, 'unclassified_items');
            }

            if (! in_array($eligibility['status'], ['legacy_blocked', 'financially_blocked', 'vat_policy_blocked'], true)
                && $eligibility['new_categories'] !== []) {
                $candidateInvoices->push($invoice);
            }

            $categoryResidual = $eligibility['calculation']['category_rounding_residual_rsd'];
            $snapshotResidual = $eligibility['calculation']['snapshot_discrepancy_rsd'];
            if (($categoryResidual !== null && $categoryResidual !== '0.00')
                || ($snapshotResidual !== null && $snapshotResidual !== '0.00')) {
                $report['rounding_residuals'][] = [
                    'invoice_id' => $invoice->id,
                    'category_rounding_residual_rsd' => $categoryResidual,
                    'snapshot_discrepancy_rsd' => $snapshotResidual,
                    'allocated' => false,
                ];
            }
        }

        return [
            'year' => $year,
            'include_historical' => $includeHistorical,
            'date_rule' => 'issue_date_suggested_manual_override',
            'manual_overlap_warning' => $manualOverlapWarning,
            'entries' => $candidateInvoices->values(),
            ...$report,
        ];
    }

    /**
     * Build a read-only comparison between persisted invoice KPO entries and
     * the current category calculation. This deliberately does not reuse the
     * append-only generation report or reserve entry numbers.
     *
     * @return array<string, mixed>
     */
    public function regenerationPreviewFromInvoices(
        Company $company,
        int $year,
        bool $includeHistorical = false,
        ?array $invoiceIds = null
    ): array
    {
        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->where('include_in_kpo', true)
            ->with(['items', 'kpoEntries' => static fn ($query) => $query->withTrashed()])
            ->orderBy('issue_date')
            ->orderBy('invoice_number');

        if (! $includeHistorical) {
            $query->whereYear('issue_date', $year);
        }
        if ($invoiceIds !== null) {
            $query->whereIn('id', array_map('intval', $invoiceIds));
        }

        $invoices = $query->get();
        $reports = $invoices
            ->map(fn (Invoice $invoice): array => $this->regenerationInvoiceReport($invoice))
            ->values();
        $actionableReports = $reports->filter(
            static fn (array $report): bool => (bool) ($report['actionable'] ?? true)
        )->values();
        $attentionReports = $reports->filter(
            static fn (array $report): bool => ! ($report['actionable'] ?? false) && (bool) ($report['requires_attention'] ?? false)
        )->values();
        $unchangedReports = $reports->filter(
            static fn (array $report): bool => ! ($report['actionable'] ?? false) && ! ($report['requires_attention'] ?? false)
        )->values();

        return [
            'year' => $year,
            'include_historical' => $includeHistorical,
            'read_only' => true,
            'invoices' => $reports->all(),
            'actionable_invoices' => $actionableReports->all(),
            'actionable_count' => $actionableReports->count(),
            'attention_count' => $attentionReports->count(),
            'unchanged_count' => $unchangedReports->count(),
            'preview_fingerprint' => $this->regenerationFingerprint($company, $year, $includeHistorical, $invoices),
        ];
    }

    /**
     * Execute an explicitly confirmed regeneration against the current locked
     * state. Amounts are always recalculated server-side.
     *
     * @param array<int, int> $invoiceIds
     * @return array<string, mixed>
     */
    public function regenerateFromInvoices(
        Company $company,
        int $year,
        array $invoiceIds,
        string $previewFingerprint,
        bool $confirmManualOverwrite = false,
        bool $includeHistorical = false
    ): array {
        $invoiceIds = collect($invoiceIds)->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values()->all();
        $operationId = (string) Str::uuid();

        return DB::transaction(function () use (
            $company,
            $year,
            $invoiceIds,
            $previewFingerprint,
            $confirmManualOverwrite,
            $includeHistorical,
            $operationId
        ): array {
            $query = Invoice::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $invoiceIds)
                    ->with('items')
                ->orderBy('id')
                ->lockForUpdate();
            if (! $includeHistorical) {
                $query->whereYear('issue_date', $year);
            }
            $invoices = $query->get();

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Jedna ili više faktura nije dostupna u aktivnoj firmi.',
                ]);
            }

            foreach ($invoices as $invoice) {
                $invoice->setRelation('kpoEntries', $invoice->kpoEntries()
                    ->withTrashed()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get());
            }

            $currentFingerprint = $this->regenerationFingerprint($company, $year, $includeHistorical, $invoices);
            if (! hash_equals($previewFingerprint, $currentFingerprint)) {
                throw ValidationException::withMessages([
                    'preview_fingerprint' => 'Preview je zastareo; podaci fakture ili KPO zapisi su se promenili.',
                ]);
            }

            $result = [
                'operation_id' => $operationId,
                'created' => [],
                'updated' => [],
                'unchanged' => [],
                'protected' => [],
                'blocked' => [],
                'obsolete' => [],
                'partial' => [],
                'unresolved' => [],
                'rounding_residuals' => [],
            ];

            foreach ($invoices as $invoice) {
                $report = $this->regenerationInvoiceReport($invoice);
                if ($report['blocking_reasons'] !== []) {
                    $result['blocked'][] = [
                        'invoice_id' => $invoice->id,
                        'reasons' => $report['blocking_reasons'],
                    ];
                    continue;
                }
                if ($report['status'] === 'partial') {
                    $result['partial'][] = ['invoice_id' => $invoice->id];
                    $result['unresolved'][] = [
                        'invoice_id' => $invoice->id,
                        ...$report['unresolved'],
                    ];
                }
                if ($report['rounding']['has_unallocated_rounding_residual']) {
                    $result['rounding_residuals'][] = [
                        'invoice_id' => $invoice->id,
                        ...$report['rounding'],
                    ];
                }
                foreach ($report['obsolete_categories'] as $obsolete) {
                    $result['obsolete'][] = ['invoice_id' => $invoice->id, ...$obsolete];
                }

                foreach ($report['proposed_entries'] as $proposal) {
                    $existing = $invoice->kpoEntries->firstWhere('id', $proposal['existing_entry_id']);
                    if (! $existing) {
                        $entry = $this->createRegeneratedEntry($company, $invoice, $proposal, $operationId);
                        $result['created'][] = [
                            'invoice_id' => $invoice->id,
                            'kpo_entry_id' => $entry->id,
                            'income_category' => $proposal['income_category'],
                            'entry_number' => $entry->entry_number,
                        ];
                        continue;
                    }

                    $difference = $this->decimalDifference($proposal['amount_rsd'], $existing->amount_rsd);
                    if ($difference === '0.00') {
                        $result['unchanged'][] = ['invoice_id' => $invoice->id, 'kpo_entry_id' => $existing->id];
                        continue;
                    }
                    $manuallyEdited = $this->isManuallyEdited($existing);
                    if ($manuallyEdited && ! $confirmManualOverwrite) {
                        $result['protected'][] = [
                            'invoice_id' => $invoice->id,
                            'kpo_entry_id' => $existing->id,
                            'income_category' => $proposal['income_category'],
                            'reason' => 'manual_overwrite_confirmation_required',
                        ];
                        continue;
                    }

                    $previous = $this->revisionValues($existing);
                    $existing->update([
                        'amount_original' => $proposal['amount_original'],
                        'amount_rsd' => $proposal['amount_rsd'],
                        'total_rsd' => $this->decimalSum($proposal['amount_rsd'], $existing->adjustment_rsd),
                    ]);
                    $existing->refresh();
                    KpoEntryRevision::create([
                        'kpo_entry_id' => $existing->id,
                        'acting_user_id' => auth()->id(),
                        'action_type' => 'regenerated',
                        'previous_values' => $previous,
                        'new_values' => $this->revisionValues($existing),
                        'manual_edit_overwritten' => $manuallyEdited,
                        'operation_id' => $operationId,
                    ]);
                    $result['updated'][] = [
                        'invoice_id' => $invoice->id,
                        'kpo_entry_id' => $existing->id,
                        'income_category' => $proposal['income_category'],
                        'manual_edit_overwritten' => $manuallyEdited,
                    ];
                }
            }

            return $result;
        }, 3);
    }

    /** @param array<string, mixed> $proposal */
    private function createRegeneratedEntry(Company $company, Invoice $invoice, array $proposal, string $operationId): KpoEntry
    {
        $entryDate = $invoice->issue_date;
        $entryNumber = $this->nextNumber($company->id, (int) $entryDate->year);
        $entry = KpoEntry::create([
            'company_id' => $company->id,
            'source_invoice_id' => $invoice->id,
            'year' => $entryDate->year,
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate->toDateString(),
            'source_type' => 'invoice',
            'origin' => 'generated',
            'income_type' => $proposal['income_category'] === 'product' ? 'proizvod' : 'usluga',
            'income_category' => $proposal['income_category'],
            'currency' => $invoice->currency,
            'amount_original' => $proposal['amount_original'],
            'amount_rsd' => $proposal['amount_rsd'],
            'adjustment_rsd' => '0.00',
            'total_rsd' => $proposal['total_rsd'],
            'description' => 'Faktura '.$invoice->invoice_number.' - '.($proposal['income_category'] === 'product' ? 'proizvodi' : 'usluge'),
        ]);
        KpoEntryRevision::create([
            'kpo_entry_id' => $entry->id,
            'acting_user_id' => auth()->id(),
            'action_type' => 'regenerated',
            'previous_values' => null,
            'new_values' => $this->revisionValues($entry),
            'manual_edit_overwritten' => false,
            'operation_id' => $operationId,
        ]);

        return $entry;
    }

    /** @return array<string, mixed> */
    public function invoiceEditConflict(Invoice $invoice, string $reason): array
    {
        $report = $this->regenerationInvoiceReport($invoice);
        if ($reason === 'kpo_exclusion') {
            $report['obsolete_categories'] = collect($report['existing_entries'])
                ->filter(static fn (array $entry): bool => empty($entry['deleted_at']))
                ->map(static fn (array $entry): array => [
                    'entry_id' => $entry['id'],
                    'entry_number' => $entry['entry_number'],
                    'income_category' => $entry['income_category'],
                    'reason' => 'invoice_excluded_from_kpo',
                    'total_rsd' => $entry['total_rsd'],
                    'manually_edited' => (bool) ($entry['manually_edited'] ?? false),
                ])->values()->all();
        }
        $requiresManualOverwrite = $reason === 'manual_edit'
            || collect($report['existing_entries'])->contains(static fn (array $entry): bool => (bool) ($entry['manually_edited'] ?? false));
        $requiresRegeneration = in_array($reason, [
            'legacy_category',
            'financial_block',
            'deleted_entry',
        ], true);

        return [
            'reason' => $reason,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'existing_entries' => $report['existing_entries'],
            'proposed_entries' => $report['proposed_entries'],
            'amount_differences' => $report['amount_differences'],
            'obsolete_categories' => $report['obsolete_categories'],
            'warnings' => array_values(array_unique(array_merge(
                $report['blocking_reasons'],
                $reason === 'kpo_exclusion' ? ['invoice_will_be_excluded_from_future_kpo_generation'] : [],
                $reason === 'vat_ineligibility' ? ['invoice_will_be_ineligible_for_automatic_kpo_generation'] : [],
            ))),
            'requires_manual_overwrite' => $requiresManualOverwrite,
            'requires_regeneration' => $requiresRegeneration,
        ];
    }

    public function invoiceEditConfirmationFingerprint(Invoice $invoice, array $data): string
    {
        $invoice->load('items');
        $invoice->setRelation('kpoEntries', $invoice->kpoEntries()
            ->withTrashed()
            ->orderBy('id')
            ->get());
        $base = $this->regenerationFingerprint(
            $invoice->company,
            (int) ($invoice->issue_date?->year ?? now()->year),
            false,
            collect([$invoice])
        );

        return hash('sha256', $base.'|'.json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function regenerationFingerprint(Company $company, int $year, bool $includeHistorical, $invoices): string
    {
        $payload = [
            'company_id' => $company->id,
            'year' => $year,
            'include_historical' => $includeHistorical,
            'invoices' => $invoices->sortBy('id')->map(static function (Invoice $invoice): array {
                return [
                    'id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'issue_date' => $invoice->issue_date?->toDateString(),
                    'include_in_kpo' => (bool) $invoice->include_in_kpo,
                    'total_original' => self::canonicalDecimal($invoice->total_original),
                    'total_rsd' => self::canonicalDecimal($invoice->total_rsd),
                    'fx_rate_to_rsd' => self::canonicalDecimal($invoice->fx_rate_to_rsd, 6),
                    'vat_enabled_at_issue' => $invoice->vat_enabled_at_issue,
                    'items' => $invoice->items->sortBy('id')->map(static fn ($item): array => [
                        'id' => $item->id,
                        'total' => self::canonicalDecimal($item->total),
                        'income_category' => $item->income_category,
                    ])->values()->all(),
                    'kpo_entries' => $invoice->kpoEntries->sortBy('id')->map(static fn (KpoEntry $entry): array => [
                        'id' => $entry->id,
                        'company_id' => $entry->company_id,
                        'source_invoice_id' => $entry->source_invoice_id,
                        'year' => $entry->year,
                        'entry_number' => $entry->entry_number,
                        'entry_date' => $entry->entry_date?->toDateString(),
                        'source_type' => $entry->source_type,
                        'currency' => $entry->currency,
                        'description' => $entry->description,
                        'income_category' => $entry->income_category,
                        'amount_original' => self::canonicalDecimal($entry->amount_original),
                        'amount_rsd' => self::canonicalDecimal($entry->amount_rsd),
                        'adjustment_rsd' => self::canonicalDecimal($entry->adjustment_rsd),
                        'total_rsd' => self::canonicalDecimal($entry->total_rsd),
                        'origin' => $entry->origin,
                        'manually_edited_by' => $entry->manually_edited_by,
                        'deleted_at' => $entry->deleted_at?->toISOString(),
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function canonicalDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');

        return ($negative ? '-' : '').((int) $whole).'.'.$fraction;
    }

    /** @return array<string, mixed> */
    private function revisionValues(KpoEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'income_category' => $entry->income_category,
            'amount_original' => $entry->amount_original,
            'amount_rsd' => $entry->amount_rsd,
            'adjustment_rsd' => $entry->adjustment_rsd,
            'total_rsd' => $entry->total_rsd,
            'entry_date' => $entry->entry_date?->toDateString(),
            'description' => $entry->description,
        ];
    }

    /** @return array<string, mixed> */
    private function regenerationInvoiceReport(Invoice $invoice): array
    {
        $calculation = $this->amountCalculator->calculate($invoice);
        $existingEntries = $invoice->kpoEntries->sortBy([
            ['entry_date', 'asc'],
            ['entry_number', 'asc'],
        ])->values();
        $existingByCategory = $existingEntries
            ->filter(fn (KpoEntry $entry): bool => ! $entry->trashed()
                && in_array($entry->income_category, ['product', 'service'], true))
            ->keyBy('income_category');
        $deletedCategories = $existingEntries
            ->filter(fn (KpoEntry $entry): bool => $entry->trashed()
                && in_array($entry->income_category, ['product', 'service'], true))
            ->pluck('income_category')
            ->unique()
            ->values()
            ->all();
        $proposedCategories = collect(['product', 'service'])
            ->filter(fn (string $category): bool => ($calculation['categories'][$category]['amount_rsd'] ?? '0.00') !== '0.00')
            ->values();

        $legacyConflicts = $existingEntries
            ->filter(fn (KpoEntry $entry): bool => $entry->income_category === null)
            ->map(fn (KpoEntry $entry): array => [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'reason' => 'legacy_null_income_category_requires_resolution',
                'total_rsd' => $entry->total_rsd,
            ])->values()->all();
        $unknownExistingCategories = $existingEntries
            ->filter(fn (KpoEntry $entry): bool => $entry->income_category !== null
                && ! in_array($entry->income_category, ['product', 'service'], true))
            ->map(fn (KpoEntry $entry): array => [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'income_category' => $entry->income_category,
                'reason' => 'unknown_existing_income_category',
                'total_rsd' => $entry->total_rsd,
            ])->values()->all();

        $missingCategories = $proposedCategories
            ->reject(fn (string $category): bool => $existingByCategory->has($category))
            ->values()->all();
        $obsoleteCategories = $existingEntries
            ->filter(fn (KpoEntry $entry): bool => ! $entry->trashed()
                && in_array($entry->income_category, ['product', 'service'], true)
                && ! $proposedCategories->contains($entry->income_category))
            ->map(fn (KpoEntry $entry): array => [
                'entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'income_category' => $entry->income_category,
                'reason' => 'category_no_longer_present',
                'total_rsd' => $entry->total_rsd,
                'manually_edited' => $this->isManuallyEdited($entry),
            ])->values()->all();

        $proposedEntries = [];
        $amountDifferences = [];
        $proposedActions = [];
        foreach ($proposedCategories as $category) {
            $amounts = $calculation['categories'][$category];
            $existing = $existingByCategory->get($category);
            $proposedTotal = $existing
                ? $this->decimalSum($amounts['amount_rsd'], $existing->adjustment_rsd)
                : $amounts['amount_rsd'];
            $difference = $existing
                ? $this->decimalDifference($proposedTotal, $existing->total_rsd)
                : $amounts['amount_rsd'];
            $action = ! $existing ? 'create' : ($difference === '0.00' ? 'retain' : 'update');

            $proposedEntries[] = [
                'income_category' => $category,
                'amount_original' => $amounts['amount_original'],
                'amount_rsd' => $amounts['amount_rsd'],
                'total_rsd' => $proposedTotal,
                'existing_entry_id' => $existing?->id,
                'action' => $action,
                'manually_edited' => $existing ? $this->isManuallyEdited($existing) : false,
            ];
            $amountDifferences[] = [
                'income_category' => $category,
                'existing_entry_id' => $existing?->id,
                'existing_amount_rsd' => $existing?->amount_rsd,
                'proposed_amount_rsd' => $amounts['amount_rsd'],
                'existing_total_rsd' => $existing?->total_rsd,
                'proposed_total_rsd' => $proposedTotal,
                'difference_rsd' => $difference,
                'manually_edited' => $existing ? $this->isManuallyEdited($existing) : false,
            ];
            $proposedActions[] = [
                'action' => $action,
                'income_category' => $category,
                'entry_id' => $existing?->id,
                'requires_confirmation' => $action !== 'retain' || ($existing && $this->isManuallyEdited($existing)),
            ];
        }

        foreach ($obsoleteCategories as $obsolete) {
            $proposedActions[] = [
                'action' => 'review_obsolete',
                'income_category' => $obsolete['income_category'],
                'entry_id' => $obsolete['entry_id'],
                'requires_confirmation' => true,
            ];
        }
        foreach ($legacyConflicts as $legacy) {
            $proposedActions[] = [
                'action' => 'resolve_legacy_category',
                'income_category' => null,
                'entry_id' => $legacy['entry_id'],
                'requires_confirmation' => true,
            ];
        }

        $blockingReasons = [];
        if ($legacyConflicts !== []) {
            $blockingReasons[] = 'legacy_null_income_category_requires_resolution';
        }
        if ($unknownExistingCategories !== []) {
            $blockingReasons[] = 'unknown_existing_income_category';
        }
        $deletedExpectedCategories = array_values(array_intersect($deletedCategories, $proposedCategories->all()));
        if ($deletedExpectedCategories !== []) {
            $blockingReasons[] = 'deleted_category_requires_resolution';
        }
        if (($calculation['generation_readiness']['status'] ?? null) === 'blocked') {
            $blockingReasons = array_merge($blockingReasons, $calculation['generation_readiness']['reasons'] ?? ['financial_inconsistency']);
        }
        if ($invoice->vat_enabled_at_issue === null) {
            $blockingReasons[] = 'unknown_historical_vat_status';
        } elseif ((bool) $invoice->vat_enabled_at_issue) {
            $blockingReasons[] = 'vat_policy_not_approved';
        }
        $blockingReasons = array_values(array_unique($blockingReasons));
        $status = $blockingReasons !== []
            ? 'blocked'
            : (($calculation['generation_readiness']['status'] ?? null) === 'partial' ? 'partial' : 'ready');
        $hasActionableChanges = collect($proposedEntries)->contains(
            static fn (array $proposal): bool => $proposal['action'] !== 'retain'
        )
            || $missingCategories !== []
            || $obsoleteCategories !== []
            || $deletedExpectedCategories !== [];
        $requiresAttention = $status !== 'ready'
            || $legacyConflicts !== []
            || $unknownExistingCategories !== []
            || $calculation['unresolved_amount_rsd'] !== '0.00'
            || (bool) $calculation['has_unallocated_rounding_residual'];

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'status' => $status,
            'actionable' => $hasActionableChanges,
            'requires_attention' => $requiresAttention,
            'blocking_reasons' => $blockingReasons,
            'existing_entries' => $existingEntries->map(fn (KpoEntry $entry): array => $this->regenerationEntry($entry))->all(),
            'proposed_entries' => $proposedEntries,
            'amount_differences' => $amountDifferences,
            'legacy_classification_conflicts' => $legacyConflicts,
            'deleted_categories' => $deletedCategories,
            'deleted_expected_categories' => $deletedExpectedCategories,
            'missing_categories' => $missingCategories,
            'obsolete_categories' => $obsoleteCategories,
            'unresolved' => [
                'amount_original' => $calculation['unresolved_amount_original'],
                'amount_rsd' => $calculation['unresolved_amount_rsd'],
            ],
            'rounding' => [
                'category_residual_rsd' => $calculation['category_rounding_residual_rsd'],
                'snapshot_discrepancy_rsd' => $calculation['snapshot_discrepancy_rsd'],
                'reconciliation_status' => $calculation['reconciliation_status'],
                'has_unallocated_rounding_residual' => $calculation['has_unallocated_rounding_residual'],
            ],
            'proposed_actions' => $proposedActions,
        ];
    }

    /** @return array<string, mixed> */
    private function regenerationEntry(KpoEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => $entry->entry_date?->toDateString(),
            'income_category' => $entry->income_category,
            'income_type' => $entry->income_type,
            'amount_original' => $entry->amount_original,
            'amount_rsd' => $entry->amount_rsd,
            'adjustment_rsd' => $entry->adjustment_rsd,
            'total_rsd' => $entry->total_rsd,
            'origin' => $entry->origin,
            'manually_edited_at' => $entry->manually_edited_at?->toISOString(),
            'manually_edited_by' => $entry->manually_edited_by,
            'manually_edited' => $this->isManuallyEdited($entry),
            'deleted_at' => $entry->deleted_at?->toISOString(),
        ];
    }

    private function isManuallyEdited(KpoEntry $entry): bool
    {
        return $entry->origin !== 'generated'
            || $entry->manually_edited_at !== null
            || $entry->manually_edited_by !== null;
    }

    private function decimalDifference(string $proposed, mixed $existing): string
    {
        $proposedCents = $this->decimalToCents($proposed);
        $existingCents = $this->decimalToCents($existing);

        return number_format(($proposedCents - $existingCents) / 100, 2, '.', '');
    }

    private function decimalSum(mixed $left, mixed $right): string
    {
        return number_format(
            ($this->decimalToCents($left) + $this->decimalToCents($right)) / 100,
            2,
            '.',
            ''
        );
    }

    private function decimalToCents(mixed $value): int
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    public function generateFromInvoices(
        Company $company,
        int $year,
        array $entries,
        bool $confirmManualOverlap = false,
        bool $includeHistorical = false
    ): array
    {
        $entries = collect($entries)
            ->unique(fn (array $entry): int => (int) $entry['invoice_id'])
            ->sortBy(fn (array $entry): int => (int) $entry['invoice_id'])
            ->values()
            ->all();
        $preflight = $this->emptyGenerationResult();
        foreach ($entries as $entryData) {
            $invoiceId = (int) $entryData['invoice_id'];
            try {
                $entryDate = Carbon::parse($entryData['entry_date']);
            } catch (Throwable) {
                $preflight['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Datum KPO evidencije nije validan.'];
                continue;
            }

            if ((int) $entryDate->year !== $year) {
                $preflight['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Datum KPO evidencije ne pripada izabranoj godini.'];
                continue;
            }

            if (! Invoice::query()->whereKey($invoiceId)->where('company_id', $company->id)->exists()) {
                $preflight['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Faktura nije dostupna u aktivnoj firmi.'];
            }
        }

        if ($preflight['failed'] !== []) {
            return $preflight;
        }

        $historicalRequested = $includeHistorical;

        $manualOverlapWarning = $historicalRequested ? $this->manualOverlapWarning($company, $year) : null;
        if ($manualOverlapWarning !== null && ! $confirmManualOverlap) {
            throw ValidationException::withMessages([
                'confirm_manual_overlap' => 'Istorijsko generisanje zahteva eksplicitnu potvrdu zbog postojećih ručnih ili odvojenih KPO zapisa u relevantnoj godini.',
            ]);
        }

        return DB::transaction(function () use ($company, $year, $entries): array {
            $result = $this->emptyGenerationResult();

            foreach ($entries as $entryData) {
                $invoiceId = (int) $entryData['invoice_id'];
                $entryDate = Carbon::parse($entryData['entry_date']);

                if ((int) $entryDate->year !== $year) {
                    $result['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Datum KPO evidencije ne pripada izabranoj godini.'];
                    continue;
                }

                $invoice = Invoice::query()
                    ->whereKey($invoiceId)
                    ->where('company_id', $company->id)
                    ->with(['items', 'kpoEntries' => static fn ($query) => $query->withTrashed()])
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    throw new RuntimeException('Faktura nije dostupna u aktivnoj firmi tokom generisanja.');
                }

                $eligibility = $this->generationEligibility($company, $invoice);
                if ($eligibility['status'] === 'legacy_blocked') {
                    $result['legacy_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
                    continue;
                }
                if ($eligibility['status'] === 'financially_blocked') {
                    $result['financially_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
                    continue;
                }
                if ($eligibility['status'] === 'vat_policy_blocked') {
                    $result['vat_policy_blocked'][] = $this->invoiceReportItem($invoice, $eligibility['reason']);
                    continue;
                }
                if ($eligibility['status'] === 'partial') {
                    $result['partial'][] = $this->invoiceReportItem($invoice, 'unclassified_items');
                    $result['unresolved'][] = [
                        'invoice_id' => $invoice->id,
                        'amount_original' => $eligibility['calculation']['unresolved_amount_original'],
                        'amount_rsd' => $eligibility['calculation']['unresolved_amount_rsd'],
                    ];
                }

                foreach ($eligibility['skipped_categories'] as $category) {
                    $result['skipped'][] = [
                        'invoice_id' => $invoice->id,
                        'income_category' => $category,
                        'reason' => 'KPO kategorijski zapis već postoji.',
                    ];
                }

                $categoryResidual = $eligibility['calculation']['category_rounding_residual_rsd'];
                $snapshotResidual = $eligibility['calculation']['snapshot_discrepancy_rsd'];
                if (($categoryResidual !== null && $categoryResidual !== '0.00')
                    || ($snapshotResidual !== null && $snapshotResidual !== '0.00')) {
                    $result['rounding_residuals'][] = [
                        'invoice_id' => $invoice->id,
                        'category_rounding_residual_rsd' => $categoryResidual,
                        'snapshot_discrepancy_rsd' => $snapshotResidual,
                        'allocated' => false,
                    ];
                }

                foreach ($eligibility['new_categories'] as $category) {
                    $existing = $invoice->kpoEntries
                        ->first(fn (KpoEntry $entry): bool => $entry->income_category === $category);

                    if ($existing) {
                        $result['skipped'][] = [
                            'invoice_id' => $invoice->id,
                            'income_category' => $category,
                            'reason' => 'KPO kategorijski zapis već postoji.',
                        ];
                        continue;
                    }

                    $amounts = $eligibility['calculation']['categories'][$category];
                    $entryNumber = $this->nextNumber($company->id, (int) $entryDate->year);
                    $entry = KpoEntry::create([
                        'company_id' => $company->id,
                        'source_invoice_id' => $invoice->id,
                        'year' => $entryDate->year,
                        'entry_number' => $entryNumber,
                        'entry_date' => $entryDate->toDateString(),
                        'source_type' => 'invoice',
                        'origin' => 'generated',
                        'income_type' => $category === 'product' ? 'proizvod' : 'usluga',
                        'income_category' => $category,
                        'currency' => $invoice->currency,
                        'amount_original' => $amounts['amount_original'],
                        'amount_rsd' => $amounts['amount_rsd'],
                        'adjustment_rsd' => '0.00',
                        'total_rsd' => $amounts['amount_rsd'],
                        'description' => 'Faktura '.$invoice->invoice_number.' - '.($category === 'product' ? 'proizvodi' : 'usluge'),
                    ]);
                    KpoEntryRevision::create([
                        'kpo_entry_id' => $entry->id,
                        'acting_user_id' => auth()->id(),
                        'action_type' => 'created',
                        'previous_values' => null,
                        'new_values' => $this->revisionValues($entry),
                        'manual_edit_overwritten' => false,
                    ]);

                    $result['created'][] = [
                        'invoice_id' => $invoice->id,
                        'kpo_entry_id' => $entry->id,
                        'income_category' => $category,
                        'entry_number' => $entry->entry_number,
                    ];
                }
            }

            return $result;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function generationEligibility(Company $company, Invoice $invoice): array
    {
        $calculation = $this->amountCalculator->calculate($invoice);
        $newCategories = [];
        $skippedCategories = [];

        foreach (['product', 'service'] as $category) {
            if (($calculation['categories'][$category]['amount_rsd'] ?? '0.00') === '0.00') {
                continue;
            }

            if ($invoice->kpoEntries->contains(fn (KpoEntry $entry): bool => $entry->income_category === $category)) {
                $skippedCategories[] = $category;
            } else {
                $newCategories[] = $category;
            }
        }

        if ($invoice->kpoEntries->contains(fn (KpoEntry $entry): bool => $entry->income_category === null)) {
            return [
                'status' => 'legacy_blocked',
                'reason' => 'Legacy invoice-linked KPO zapis bez income_category blokira generisanje.',
                'calculation' => $calculation,
                'new_categories' => [],
                'skipped_categories' => $skippedCategories,
            ];
        }

        if (($calculation['generation_readiness']['status'] ?? null) === 'blocked') {
            return [
                'status' => 'financially_blocked',
                'reason' => implode(', ', $calculation['generation_readiness']['reasons'] ?? ['financial_inconsistency']),
                'calculation' => $calculation,
                'new_categories' => [],
                'skipped_categories' => $skippedCategories,
            ];
        }

        if ($invoice->vat_enabled_at_issue === null) {
            return [
                'status' => 'vat_policy_blocked',
                'reason' => 'unknown_historical_vat_status',
                'calculation' => $calculation,
                'new_categories' => [],
                'skipped_categories' => $skippedCategories,
            ];
        }

        if ((bool) $invoice->vat_enabled_at_issue) {
            return [
                'status' => 'vat_policy_blocked',
                'reason' => 'Generisanje invoice-sourced KPO zapisa za VAT fakture čeka odobrenje poreske osnovice.',
                'calculation' => $calculation,
                'new_categories' => [],
                'skipped_categories' => $skippedCategories,
            ];
        }

        $status = $calculation['generation_readiness']['status'] === 'partial' ? 'partial' : 'ready';

        return [
            'status' => $status,
            'reason' => $status === 'partial' ? 'Faktura sadrži nerazvrstane stavke.' : null,
            'calculation' => $calculation,
            'new_categories' => $newCategories,
            'skipped_categories' => $skippedCategories,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function emptyGenerationReport(): array
    {
        return [
            'new' => [],
            'skipped' => [],
            'legacy_blocked' => [],
            'financially_blocked' => [],
            'vat_policy_blocked' => [],
            'partial' => [],
            'rounding_residuals' => [],
        ];
    }

    /** @return array{count:int, year:int, requires_confirmation:bool}|null */
    private function manualOverlapWarning(Company $company, int $year): ?array
    {
        $count = KpoEntry::query()
            ->where('company_id', $company->id)
            ->whereYear('entry_date', $year)
            ->whereNull('source_invoice_id')
            ->count();

        return $count === 0 ? null : [
            'count' => $count,
            'year' => $year,
            'requires_confirmation' => true,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function emptyGenerationResult(): array
    {
        return [
            'created' => [],
            'skipped' => [],
            'legacy_blocked' => [],
            'financially_blocked' => [],
            'vat_policy_blocked' => [],
            'partial' => [],
            'unresolved' => [],
            'rounding_residuals' => [],
            'failed' => [],
        ];
    }

    private function invoiceReportItem(Invoice $invoice, string $reason): array
    {
        return ['invoice_id' => $invoice->id, 'reason' => $reason];
    }

    private function categoryReportItem(Invoice $invoice, string $category): array
    {
        $calculation = $this->amountCalculator->calculate($invoice);

        return [
            'invoice_id' => $invoice->id,
            'income_category' => $category,
            'amount_original' => $calculation['categories'][$category]['amount_original'],
            'amount_rsd' => $calculation['categories'][$category]['amount_rsd'],
        ];
    }

    private function nextNumber(int $companyId, int $year): int
    {
        $query = DB::table('kpo_counters')
            ->where('company_id', $companyId)
            ->where('year', $year);
        $counter = $query->lockForUpdate()->first();

        if ($counter) {
            $number = (int) $counter->next_number;
            $query->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        }

        try {
            $nextNumber = ((int) DB::table('kpo_entries')
                ->where('company_id', $companyId)
                ->where('year', $year)
                ->max('entry_number')) + 1;
            DB::table('kpo_counters')->insert([
                'company_id' => $companyId,
                'year' => $year,
                'next_number' => $nextNumber + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $nextNumber;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                // Deadlocks and serialization failures invalidate the current
                // InnoDB transaction. Let DB::transaction(..., 3) roll it
                // back and retry from the outer boundary.
                throw $exception;
            }

            $counter = $query->lockForUpdate()->first();
            if (! $counter) {
                throw $exception;
            }

            $number = (int) $counter->next_number;
            $query->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        }
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23000'
            && (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
