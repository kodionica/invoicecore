<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceRevision;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportExportService
{
    public const MAX_ROWS = 5000;

    public function fields(string $type): array
    {
        return $type === 'clients'
            ? ['name', 'email', 'tax_id', 'registration_number', 'address', 'city', 'country', 'phone', 'client_type']
            : ['invoice_number', 'issue_date', 'due_date', 'currency', 'status', 'client_id', 'client_name', 'payment_method', 'note', 'vat_enabled_at_issue', 'paid_at', 'payment_note', 'fx_rate_to_rsd', 'fx_provider', 'fx_date', 'item_name', 'item_description', 'quantity', 'price', 'tax_percent', 'income_category'];
    }

    public function preview(UploadedFile $file, string $type, Company $company, array $mapping = []): array
    {
        [$headers, $rows, $delimiter] = $this->read($file);
        $this->currentHeaders = $headers;
        $mapping = $this->normalizeMapping($mapping, $headers, $type);
        $result = $this->validateRows($rows, $mapping, $type, $company);

        return [
            'type' => $type,
            'delimiter' => $delimiter,
            'headers' => $headers,
            'fields' => $this->fields($type),
            'mapping' => $mapping,
            'preview' => array_slice($rows, 0, 10),
            'errors' => $result['errors'],
            'summary' => $result['summary'],
            'can_import' => $result['errors'] === [],
        ];
    }

    public function import(UploadedFile $file, string $type, array $mapping, Company $company): array
    {
        [$headers, $rows] = $this->read($file);
        $this->currentHeaders = $headers;
        $mapping = $this->normalizeMapping($mapping, $headers, $type);
        $result = $this->validateRows($rows, $mapping, $type, $company);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $result['errors']]);
        }

        return DB::transaction(fn () => $type === 'clients'
            ? $this->importClients($rows, $mapping, $company)
            : $this->importInvoices($rows, $mapping, $company));
    }

    public function export(string $type, Company $company): StreamedResponse
    {
        $filename = sprintf('%s-%s.csv', $type === 'clients' ? 'klijenti' : 'fakture', now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($type, $company): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($type === 'clients') {
                fputcsv($handle, $this->fields('clients'));
                $company->clients()->orderBy('id')->cursor()->each(function (Client $client) use ($handle): void {
                    fputcsv($handle, array_map(fn ($value) => $this->safeCell($value), [
                        $client->name, $client->email, $client->tax_id, $client->registration_number,
                        $client->address, $client->city, $client->country, $client->phone, $client->client_type,
                    ]));
                });
            } else {
                $headers = $this->fields('invoices');
                fputcsv($handle, $headers);
                $company->invoices()->with(['client', 'items'])->orderBy('id')->cursor()->each(function (Invoice $invoice) use ($handle): void {
                    $items = $invoice->items->isEmpty() ? [null] : $invoice->items;
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $this->safeCell($invoice->invoice_number), $this->safeCell($invoice->issue_date?->toDateString()),
                            $this->safeCell($invoice->due_date?->toDateString()), $this->safeCell($invoice->currency),
                            $this->safeCell($invoice->status), $invoice->client_id, $this->safeCell($invoice->client?->name),
                            $this->safeCell($invoice->payment_method), $this->safeCell($invoice->note),
                            $invoice->vat_enabled_at_issue ? '1' : '0', $this->safeCell($invoice->paid_at?->toDateString()),
                            $this->safeCell($invoice->payment_note), $invoice->fx_rate_to_rsd, $this->safeCell($invoice->fx_provider), $this->safeCell($invoice->fx_date?->toDateString()), $this->safeCell($item?->name), $this->safeCell($item?->description),
                            $item?->quantity, $item?->price, $invoice->vat_enabled_at_issue ? $invoice->company?->default_tax_percent : 0, $this->safeCell($item?->income_category),
                        ]);
                    }
                });
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['CSV fajl je prazan.']]);
        }
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $comma = count(str_getcsv($firstLine, ','));
        $semicolon = count(str_getcsv($firstLine, ';'));
        $delimiter = $semicolon > $comma ? ';' : ',';
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') continue;
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => ['CSV ima previše redova. Maksimum je '.self::MAX_ROWS.'.']]);
            }
            $rows[] = array_pad($row, count($headers), null);
        }
        fclose($handle);
        return [$headers, $rows, $delimiter];
    }

    private function normalizeMapping(array $mapping, array $headers, string $type): array
    {
        $normalized = [];
        foreach ($this->fields($type) as $field) {
            $candidate = $mapping[$field] ?? null;
            if (!is_string($candidate) || !in_array($candidate, $headers, true)) {
                $candidate = collect($headers)->first(fn ($header) => Str::lower(Str::replace([' ', '-'], '_', $header)) === $field);
            }
            if ($candidate) $normalized[$field] = $candidate;
        }
        return $normalized;
    }

    private function validateRows(array $rows, array $mapping, string $type, Company $company): array
    {
        $errors = [];
        $summary = ['rows' => count($rows), 'invoices' => 0, 'items' => 0, 'clients' => 0, 'duplicates' => 0];
        if ($type === 'clients') {
            $seen = [];
            foreach ($rows as $index => $row) {
                $name = $this->mappedValue($row, $mapping, 'name');
                if ($name === '') $errors[] = 'Red '.($index + 2).': naziv klijenta je obavezan.';
                $key = strtolower($this->mappedValue($row, $mapping, 'tax_id') ?: $this->mappedValue($row, $mapping, 'email'));
                if ($key !== '' && isset($seen[$key])) { $summary['duplicates']++; $errors[] = 'Red '.($index + 2).': duplikat klijenta.'; }
                foreach (['tax_id', 'registration_number', 'email'] as $field) {
                    $value = $this->mappedValue($row, $mapping, $field);
                    if ($value !== '' && $company->clients()->where($field, $value)->exists()) {
                        $summary['duplicates']++;
                        $errors[] = 'Red '.($index + 2).": klijent sa {$field} već postoji.";
                        break;
                    }
                }
                $seen[$key] = true;
                $summary['clients']++;
            }
        } else {
            $groups = [];
            foreach ($rows as $index => $row) {
                $number = $this->mappedValue($row, $mapping, 'invoice_number');
                $name = $this->mappedValue($row, $mapping, 'item_name');
                $currency = strtoupper($this->mappedValue($row, $mapping, 'currency'));
                if ($number === '') $errors[] = 'Red '.($index + 2).': broj fakture je obavezan.';
                if ($number !== '' && $company->invoices()->where('invoice_number', $number)->exists()) $errors[] = 'Red '.($index + 2).": faktura {$number} već postoji.";
                if ($name === '') $errors[] = 'Red '.($index + 2).': naziv stavke je obavezan.';
                foreach (['issue_date' => 'datum izdavanja', 'due_date' => 'rok plaćanja'] as $dateField => $label) {
                    $date = $this->mappedValue($row, $mapping, $dateField);
                    try { if ($date === '') throw new \InvalidArgumentException(); Carbon::parse($date); }
                    catch (\Throwable) { $errors[] = 'Red '.($index + 2).": {$label} nije ispravan."; }
                }
                if (!preg_match('/^[A-Z]{3}$/', $currency)) $errors[] = 'Red '.($index + 2).': valuta mora biti ISO kod od 3 slova.';
                if ($currency !== 'RSD' && (!is_numeric($this->mappedValue($row, $mapping, 'fx_rate_to_rsd')) || (float) $this->mappedValue($row, $mapping, 'fx_rate_to_rsd') <= 0)) $errors[] = 'Red '.($index + 2).': za stranu valutu mora biti mapiran pozitivan sačuvani kurs prema RSD.';
                $clientId = $this->mappedValue($row, $mapping, 'client_id');
                $clientName = $this->mappedValue($row, $mapping, 'client_name');
                if (($clientId === '' && $clientName === '') || ($clientId !== '' && !$company->clients()->whereKey((int) $clientId)->exists() && $clientName === '')) $errors[] = 'Red '.($index + 2).': klijent nije pronađen u aktivnoj firmi.';
                if (!is_numeric($this->mappedValue($row, $mapping, 'quantity')) || (float) $this->mappedValue($row, $mapping, 'quantity') <= 0) $errors[] = 'Red '.($index + 2).': količina mora biti veća od nule.';
                if (!is_numeric($this->mappedValue($row, $mapping, 'price')) || (float) $this->mappedValue($row, $mapping, 'price') < 0) $errors[] = 'Red '.($index + 2).': cena nije ispravna.';
                $status = $this->mappedValue($row, $mapping, 'status');
                if ($status !== '' && !in_array($status, array_map(fn (InvoiceStatus $case) => $case->value, InvoiceStatus::cases()), true)) $errors[] = 'Red '.($index + 2).': status nije podržan.';
                if ($status === InvoiceStatus::PAID->value && $this->mappedValue($row, $mapping, 'paid_at') === '') $errors[] = 'Red '.($index + 2).': plaćena faktura mora imati datum uplate.';
                $category = $this->mappedValue($row, $mapping, 'income_category');
                if ($category !== '' && !in_array($category, ['product', 'service', 'unclassified'], true)) $errors[] = 'Red '.($index + 2).': klasifikacija prihoda nije podržana.';
                if ($this->mappedValue($row, $mapping, 'paid_at') !== '') {
                    try { Carbon::parse($this->mappedValue($row, $mapping, 'paid_at')); }
                    catch (\Throwable) { $errors[] = 'Red '.($index + 2).': datum uplate nije ispravan.'; }
                }
                $groups[$number] = true;
                $summary['items']++;
            }
            $summary['invoices'] = count($groups);
        }
        return compact('errors', 'summary');
    }

    private array $currentHeaders = [];

    private function mappedValue(array $row, array $mapping, string $field): string
    {
        $header = $mapping[$field] ?? null;
        if (!$header) return '';
        $index = array_search($header, $this->currentHeaders, true);
        return $index === false ? '' : trim((string) ($row[$index] ?? ''));
    }

    private function importClients(array $rows, array $mapping, Company $company): array
    {
        $created = 0;
        foreach ($rows as $row) {
            $data = [];
            foreach ($this->fields('clients') as $field) $data[$field] = $this->mappedValue($row, $mapping, $field) ?: null;
            $data['client_type'] ??= 'b2b';
            $duplicate = false;
            foreach (['tax_id', 'registration_number', 'email'] as $field) {
                if (!empty($data[$field]) && $company->clients()->where($field, $data[$field])->exists()) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) throw ValidationException::withMessages(['file' => ['Klijent već postoji; postojeći klijenti se ne prepisuju.']]);
            $company->clients()->create($data);
            $created++;
        }
        return ['created_clients' => $created, 'created_invoices' => 0, 'created_items' => 0];
    }

    private function importInvoices(array $rows, array $mapping, Company $company): array
    {
        $groups = [];
        foreach ($rows as $row) $groups[$this->mappedValue($row, $mapping, 'invoice_number')][] = $row;
        $createdInvoices = 0; $createdItems = 0;
        foreach ($groups as $number => $invoiceRows) {
            if ($company->invoices()->where('invoice_number', $number)->exists()) throw ValidationException::withMessages(['file' => ["Faktura {$number} već postoji."]]);
            $first = $invoiceRows[0];
            $client = $this->resolveClient($first, $mapping, $company);
            $currency = strtoupper($this->mappedValue($first, $mapping, 'currency'));
            $vat = in_array(strtolower($this->mappedValue($first, $mapping, 'vat_enabled_at_issue')), ['1', 'true', 'da', 'yes'], true);
            $taxPercent = is_numeric($this->mappedValue($first, $mapping, 'tax_percent')) && $this->mappedValue($first, $mapping, 'tax_percent') !== ''
                ? (float) $this->mappedValue($first, $mapping, 'tax_percent')
                : (float) ($company->default_tax_percent ?? 0);
            $subtotal = 0.0; $tax = 0.0; $items = [];
            foreach ($invoiceRows as $row) {
                $quantity = (float) $this->mappedValue($row, $mapping, 'quantity');
                $price = (float) $this->mappedValue($row, $mapping, 'price');
                $sub = $quantity * $price; $itemTax = $vat ? $sub * $taxPercent / 100 : 0.0;
                $subtotal += $sub; $tax += $itemTax;
                $items[] = ['name' => $this->mappedValue($row, $mapping, 'item_name'), 'description' => $this->mappedValue($row, $mapping, 'item_description') ?: $this->mappedValue($row, $mapping, 'item_name'), 'quantity' => $quantity, 'price' => $price, 'sub_total' => $sub, 'tax_amount' => $itemTax, 'total' => $sub + $itemTax, 'income_category' => $this->mappedValue($row, $mapping, 'income_category') ?: null];
            }
            $rateValue = $currency === 'RSD' ? 1.0 : (float) $this->mappedValue($first, $mapping, 'fx_rate_to_rsd');
            $rate = [
                'exchange_middle' => $rateValue,
                'provider' => $this->mappedValue($first, $mapping, 'fx_provider') ?: ($currency === 'RSD' ? 'internal' : 'csv_import'),
                'date' => $this->mappedValue($first, $mapping, 'fx_date') ?: now()->toDateString(),
            ];
            $total = $subtotal + $tax; $issueDate = $this->mappedValue($first, $mapping, 'issue_date');
            $invoice = $company->invoices()->create(['invoice_number' => $number, 'client_id' => $client->id, 'issue_date' => $issueDate, 'due_date' => $this->mappedValue($first, $mapping, 'due_date') ?: null, 'currency' => $currency, 'status' => $this->mappedValue($first, $mapping, 'status') ?: InvoiceStatus::DRAFT->value, 'payment_method' => $this->mappedValue($first, $mapping, 'payment_method') ?: null, 'note' => $this->mappedValue($first, $mapping, 'note') ?: null, 'vat_enabled_at_issue' => $vat, 'paid_at' => $this->mappedValue($first, $mapping, 'paid_at') ?: null, 'payment_note' => $this->mappedValue($first, $mapping, 'payment_note') ?: null, 'include_in_kpo' => false, 'fx_rate_to_rsd' => $rate['exchange_middle'], 'fx_provider' => $rate['provider'], 'fx_date' => $rate['date'], 'subtotal_original' => round($subtotal, 2), 'tax_original' => round($tax, 2), 'total_original' => round($total, 2), 'subtotal_rsd' => round($subtotal * $rate['exchange_middle'], 2), 'tax_rsd' => round($tax * $rate['exchange_middle'], 2), 'total_rsd' => round($total * $rate['exchange_middle'], 2), 'total' => round($total, 2)]);
            foreach ($items as $item) $invoice->items()->create($item);
            InvoiceRevision::create(['invoice_id' => $invoice->id, 'acting_user_id' => auth()->id(), 'action_type' => 'created', 'new_values' => ['source' => 'csv_import', 'invoice_id' => $invoice->id], 'changed_fields' => ['invoice', 'items']]);
            $createdInvoices++; $createdItems += count($items);
        }
        return ['created_clients' => 0, 'created_invoices' => $createdInvoices, 'created_items' => $createdItems];
    }

    private function resolveClient(array $row, array $mapping, Company $company): Client
    {
        $id = $this->mappedValue($row, $mapping, 'client_id');
        if ($id !== '' && ($client = $company->clients()->find((int) $id))) return $client;
        $name = $this->mappedValue($row, $mapping, 'client_name');
        $client = $company->clients()->where('name', $name)->first();
        if (!$client) throw ValidationException::withMessages(['file' => ['Klijent nije pronađen u aktivnoj firmi; import ne kreira klijente automatski.']]);
        return $client;
    }

    private function safeCell(mixed $value): mixed
    {
        $text = (string) ($value ?? '');
        return preg_match('/^[=+\-@]/', $text) ? "'".$text : $text;
    }
}
