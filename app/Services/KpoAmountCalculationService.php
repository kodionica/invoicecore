<?php

namespace App\Services;

use App\Models\Invoice;
use InvalidArgumentException;

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
        $fxRateMicros = $this->decimalToScaledInteger($invoice->fx_rate_to_rsd, 6);

        if ($fxRateMicros === null || $fxRateMicros <= 0) {
            throw new InvalidArgumentException('Faktura nema validan sačuvan kurs za RSD konverziju.');
        }

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

        $categories = [];
        foreach (array_keys($originalCents) as $category) {
            $categories[$category] = [
                'amount_original' => $this->formatCents($originalCents[$category]),
                'amount_rsd' => $this->formatCents($this->convertCentsToRsdCents($originalCents[$category], $fxRateMicros)),
                'item_count' => $itemCounts[$category],
            ];
        }

        $calculatedTotalRsdCents = array_sum(array_map(
            fn (array $category): int => $this->decimalToScaledInteger($category['amount_rsd'], 2) ?? 0,
            $categories
        ));
        $classifiedTotalRsdCents = $this->decimalToScaledInteger($categories['product']['amount_rsd'], 2)
            + $this->decimalToScaledInteger($categories['service']['amount_rsd'], 2);
        $persistedTotalRsdCents = $this->decimalToScaledInteger($invoice->total_rsd, 2) ?? 0;
        $discrepancyCents = $persistedTotalRsdCents - $calculatedTotalRsdCents;
        $isComplete = $originalCents['unclassified'] === 0;

        return [
            'currency' => strtoupper((string) $invoice->currency),
            'fx_rate_to_rsd' => (string) $invoice->fx_rate_to_rsd,
            'categories' => $categories,
            'is_complete' => $isComplete,
            'unresolved_amount_original' => $categories['unclassified']['amount_original'],
            'unresolved_amount_rsd' => $categories['unclassified']['amount_rsd'],
            'classified_total_rsd' => $this->formatCents($classifiedTotalRsdCents),
            'calculated_total_rsd' => $this->formatCents($calculatedTotalRsdCents),
            'persisted_total_rsd' => $this->formatCents($persistedTotalRsdCents),
            'reconciliation_discrepancy_rsd' => $this->formatCents($discrepancyCents),
            'proposed_reconciliation' => $discrepancyCents === 0 ? null : [
                'type' => 'explicit_snapshot_residual',
                'amount_rsd' => $this->formatCents($discrepancyCents),
                'apply_automatically' => false,
            ],
        ];
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
