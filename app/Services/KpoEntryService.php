<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

                if (KpoEntry::query()->where('source_invoice_id', $invoice->id)->exists()) {
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

            return KpoEntry::create([
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
        });
    }

    public function update(KpoEntry $entry, array $data): KpoEntry
    {
        return DB::transaction(function () use ($entry, $data): KpoEntry {
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
            $entry->manually_edited_at = now();
            $entry->manually_edited_by = auth()->id();
            $entry->save();

            return $entry->refresh();
        });
    }

    public function previewFromInvoices(Company $company, int $year, bool $includeHistorical = false): array
    {
        $query = Invoice::query()
            ->where('company_id', $company->id)
            ->where('include_in_kpo', true)
            ->with(['items', 'kpoEntries'])
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
                    ->with(['items', 'kpoEntries'])
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
