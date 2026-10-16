<?php

namespace App\Services;

use App\Models\Invoice;

class KpoAmountCalculationService
{
    /**
     * Calculate category totals from the invoice's persisted item and FX snapshots.
     *
     * No model is written and no current exchange rate is requested.
     *
     * @return array<string, mixed>
     */
    public function calculate(Invoice $invoice): array
    {
        $originalCents = [
            'product' => 0,
            'service' => 0,
            'unclassified' => 0,
        ];
        $itemCounts = array_fill_keys(array_keys($originalCents), 0);

        foreach ($invoice->items as $item) {
            $category = in_array($item->income_category, ['product', 'service'], true)
                ? $item->income_category
                : 'unclassified';

            $originalCents[$category] += $this->decimalToScaledInteger($item->total, 2) ?? 0;
            $itemCounts[$category]++;
        }

        $itemDerivedTotalOriginalCents = array_sum($originalCents);
        $itemCount = array_sum($itemCounts);
        $fxRateMicros = $this->decimalToScaledInteger($invoice->fx_rate_to_rsd, 6);

        if ($fxRateMicros === null || $fxRateMicros <= 0) {
            return $this->blockedResult(
                $invoice,
                $originalCents,
                $itemCounts,
                'missing_persisted_fx_rate',
                'Faktura nema validan sačuvan kurs za RSD konverziju.'
            );
        }

        $categories = [];
        foreach (array_keys($originalCents) as $category) {
            $categories[$category] = [
                'amount_original' => $this->formatCents($originalCents[$category]),
                'amount_rsd' => $this->formatCents($this->convertCentsToRsdCents($originalCents[$category], $fxRateMicros)),
                'item_count' => $itemCounts[$category],
            ];
        }

        $itemDerivedTotalRsdCents = $this->convertCentsToRsdCents($itemDerivedTotalOriginalCents, $fxRateMicros);
        $calculatedTotalRsdCents = array_sum(array_map(
            fn (array $category): int => $this->decimalToScaledInteger($category['amount_rsd'], 2) ?? 0,
            $categories
        ));
        $classifiedTotalRsdCents = $this->decimalToScaledInteger($categories['product']['amount_rsd'], 2)
            + $this->decimalToScaledInteger($categories['service']['amount_rsd'], 2);
        $categoryRoundingResidualCents = $calculatedTotalRsdCents - $itemDerivedTotalRsdCents;
        $itemDerivedTotalOriginal = $this->formatCents($itemDerivedTotalOriginalCents);
        $persistedTotalOriginalCents = $this->decimalToScaledInteger($invoice->total_original, 2) ?? 0;
        $originalSnapshotDifferenceCents = $persistedTotalOriginalCents;
        $originalSnapshotDifferenceCents -= $itemDerivedTotalOriginalCents;
        $persistedTotalRsdCents = $this->decimalToScaledInteger($invoice->total_rsd, 2) ?? 0;
        $snapshotDiscrepancyCents = $persistedTotalRsdCents - $itemDerivedTotalRsdCents;
        $itemRoundingToleranceOriginalCents = $this->roundingToleranceCents($itemCount);
        $snapshotToleranceRsdCents = $this->convertCentsToRsdCents($itemRoundingToleranceOriginalCents, $fxRateMicros) + 1;
        $categoryRoundingToleranceRsdCents = $this->roundingToleranceCents(
            count(array_filter($originalCents, static fn (int $amount): bool => $amount !== 0))
        );
        $financiallyConsistent = abs($originalSnapshotDifferenceCents) <= $itemRoundingToleranceOriginalCents
            && abs($snapshotDiscrepancyCents) <= $snapshotToleranceRsdCents
            && abs($categoryRoundingResidualCents) <= $categoryRoundingToleranceRsdCents;
        $isComplete = $originalCents['unclassified'] === 0;
        $readinessStatus = $financiallyConsistent ? ($isComplete ? 'ready' : 'partial') : 'blocked';
        $hasUnallocatedRoundingResidual = $categoryRoundingResidualCents !== 0 || $snapshotDiscrepancyCents !== 0;
        $reconciliationStatus = ! $financiallyConsistent
            ? 'blocked'
            : ($hasUnallocatedRoundingResidual ? 'residual' : 'reconciled');

        return [
            'currency' => strtoupper((string) $invoice->currency),
            'fx_rate_to_rsd' => (string) $invoice->fx_rate_to_rsd,
            'categories' => $categories,
            'is_complete' => $isComplete,
            'unresolved_amount_original' => $categories['unclassified']['amount_original'],
            'unresolved_amount_rsd' => $categories['unclassified']['amount_rsd'],
            'classified_total_rsd' => $this->formatCents($classifiedTotalRsdCents),
            'item_derived_total_original' => $itemDerivedTotalOriginal,
            'item_derived_total_rsd' => $this->formatCents($itemDerivedTotalRsdCents),
            'original_snapshot_difference' => $this->formatCents($originalSnapshotDifferenceCents),
            'snapshot_discrepancy_rsd' => $this->formatCents($snapshotDiscrepancyCents),
            'category_rounding_residual_rsd' => $this->formatCents($categoryRoundingResidualCents),
            'calculated_total_rsd' => $this->formatCents($calculatedTotalRsdCents),
            'persisted_total_rsd' => $this->formatCents($persistedTotalRsdCents),
            'reconciliation_discrepancy_rsd' => $this->formatCents($persistedTotalRsdCents - $calculatedTotalRsdCents),
            'rounding_tolerances' => [
                'item_original' => $this->formatCents($itemRoundingToleranceOriginalCents),
                'snapshot_rsd' => $this->formatCents($snapshotToleranceRsdCents),
                'category_rsd' => $this->formatCents($categoryRoundingToleranceRsdCents),
            ],
            'generation_readiness' => [
                'status' => $readinessStatus,
                'can_generate_known_categories' => $financiallyConsistent,
                'reasons' => $financiallyConsistent
                    ? ($isComplete ? [] : ['unclassified_items'])
                    : ['invoice_snapshot_inconsistent'],
            ],
            'reconciliation_status' => $reconciliationStatus,
            'has_unallocated_rounding_residual' => $hasUnallocatedRoundingResidual,
            'proposed_reconciliation' => $snapshotDiscrepancyCents === 0 ? null : [
                'type' => 'explicit_snapshot_residual',
                'amount_rsd' => $this->formatCents($snapshotDiscrepancyCents),
                'apply_automatically' => false,
            ],
        ];
    }

    /**
     * @param array<string, int> $originalCents
     * @param array<string, int> $itemCounts
     * @return array<string, mixed>
     */
    private function blockedResult(
        Invoice $invoice,
        array $originalCents,
        array $itemCounts,
        string $reason,
        string $message
    ): array {
        $categories = [];
        foreach (array_keys($originalCents) as $category) {
            $categories[$category] = [
                'amount_original' => $this->formatCents($originalCents[$category]),
                'amount_rsd' => null,
                'item_count' => $itemCounts[$category],
            ];
        }

        return [
            'currency' => strtoupper((string) $invoice->currency),
            'fx_rate_to_rsd' => $invoice->fx_rate_to_rsd === null ? null : (string) $invoice->fx_rate_to_rsd,
            'categories' => $categories,
            'is_complete' => $originalCents['unclassified'] === 0,
            'unresolved_amount_original' => $categories['unclassified']['amount_original'],
            'unresolved_amount_rsd' => null,
            'classified_total_rsd' => null,
            'item_derived_total_original' => $this->formatCents(array_sum($originalCents)),
            'item_derived_total_rsd' => null,
            'original_snapshot_difference' => null,
            'snapshot_discrepancy_rsd' => null,
            'category_rounding_residual_rsd' => null,
            'calculated_total_rsd' => null,
            'persisted_total_rsd' => $this->formatCents($this->decimalToScaledInteger($invoice->total_rsd, 2) ?? 0),
            'reconciliation_discrepancy_rsd' => null,
            'rounding_tolerances' => null,
            'generation_readiness' => [
                'status' => 'blocked',
                'can_generate_known_categories' => false,
                'reasons' => [$reason],
                'message' => $message,
            ],
            'reconciliation_status' => 'blocked',
            'has_unallocated_rounding_residual' => false,
            'proposed_reconciliation' => null,
        ];
    }

    private function roundingToleranceCents(int $count): int
    {
        // Each persisted two-decimal value can differ by less than half a cent;
        // the aggregate snapshot adds one further half-cent rounding step.
        return intdiv((5 * $count) + 5 + 9, 10);
    }

    private function convertCentsToRsdCents(int $originalCents, int $fxRateMicros): int
    {
        return intdiv(($originalCents * $fxRateMicros) + 500_000, 1_000_000);
    }

    private function decimalToScaledInteger(mixed $value, int $scale): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if ($whole === '' || ! ctype_digit($whole) || $fraction !== '' && ! ctype_digit($fraction)) {
            return null;
        }

        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $scaled = ((int) $whole * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$scaled : $scaled;
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
