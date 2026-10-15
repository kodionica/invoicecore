<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\KpoEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class KpoEntryService
{
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
                'income_type' => $data['income_type'] ?? ($invoice ? 'usluga' : null),
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
            $entry->save();

            return $entry->refresh();
        });
    }

    public function generateFromInvoices(Company $company, int $year, array $entries): array
    {
        $result = ['created' => [], 'skipped' => [], 'failed' => []];

        foreach ($entries as $entryData) {
            $invoiceId = (int) $entryData['invoice_id'];

            try {
                $entryDate = Carbon::parse($entryData['entry_date']);
            } catch (Throwable) {
                $result['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Datum KPO evidencije nije validan.'];

                continue;
            }

            if ((int) $entryDate->year !== $year) {
                $result['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Datum KPO evidencije ne pripada izabranoj godini.'];

                continue;
            }

            if (! Invoice::query()->whereKey($invoiceId)->where('company_id', $company->id)->exists()) {
                $result['failed'][] = ['invoice_id' => $invoiceId, 'reason' => 'Faktura nije dostupna u aktivnoj firmi.'];

                continue;
            }

            if (KpoEntry::query()->where('source_invoice_id', $invoiceId)->exists()) {
                $result['skipped'][] = ['invoice_id' => $invoiceId, 'reason' => 'KPO zapis već postoji.'];

                continue;
            }

            try {
                $entry = $this->create($company, [
                    'source_type' => 'invoice',
                    'invoice_id' => $invoiceId,
                    'entry_date' => $entryData['entry_date'],
                ]);

                $result['created'][] = [
                    'invoice_id' => $invoiceId,
                    'kpo_entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                ];
            } catch (Throwable $exception) {
                $result['failed'][] = [
                    'invoice_id' => $invoiceId,
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return $result;
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
            DB::table('kpo_counters')->insert([
                'company_id' => $companyId,
                'year' => $year,
                'next_number' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        } catch (QueryException $exception) {
            $counter = $query->lockForUpdate()->first();
            if (! $counter) {
                throw $exception;
            }

            $number = (int) $counter->next_number;
            $query->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        }
    }
}
