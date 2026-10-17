<?php

namespace App\Http\Controllers;

use App\Exceptions\KpoInvoiceConflictException;
use App\Enums\InvoiceStatus;
use App\Http\Requests\UpdateInvoiceItemClassificationsRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceRevision;
use App\Models\InvoicePdfVersion;
use App\Models\InvoiceEmailSend;
use App\Models\KpoEntry;
use App\Models\CompanyBankAccount;
use App\Models\Company;
use App\Services\ExchangeRateService;
use App\Services\KpoEntryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\Process\Process;

class InvoiceController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $active_company = auth()->user()->activeCompany;

        if ( ! $active_company ) {
            return response()->json( [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi mogao videti fakture.',
            ], 422 );
        }

        $invoices = Invoice::query()
            ->forActiveCompany( $active_company->id )
            ->with( [ 'client', 'items' ] )
            ->latest()
            ->get();

        return response()->json( $invoices );
    }

    public function nextNumber() {
        $active_company = auth()->user()->activeCompany;

        if ( ! $active_company ) {
            return response()->json( [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi mogao videti fakture.',
            ], 422 );
        }

        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        $counter = DB::table( 'invoice_counters' )
            ->where( 'company_id', $active_company->id )
            ->where( 'year', $year )
            ->where( 'month', $month )
            ->first();

        if ( $counter ) {
            $sequence = (int) $counter->next_number;
        } else {
            $existingCount = $active_company
                ->invoices()
                ->whereYear( 'issue_date', $year )
                ->whereMonth( 'issue_date', $month )
                ->count();
            $sequence = max( (int) $active_company->invoice_start_number, $existingCount + 1 );
        }

        return response()->json( [
            'invoice_number' => sprintf( '%d-%02d-%03d', $year, $month, $sequence ),
        ] );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws \Throwable
     */
    public function store( StoreInvoiceRequest $request, ExchangeRateService $exchangeRateService ) {
        $attrs          = $request->validated();
        $user           = auth()->user();
        $active_company = $user->activeCompany;

        if ( ! $active_company ) {
            return response()->json( [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi mogao praviti fakture.',
            ], 422 );
        }

        if ( ! $active_company->clients()->whereKey( $attrs[ 'client_id' ] )->exists() ) {
            return response()->json( [
                'message' => 'Izabrani klijent ne pripada aktivnoj firmi.',
            ], 422 );
        }

        $effectiveVatEnabled = array_key_exists('vat_enabled_at_issue', $attrs)
            ? (bool) $attrs['vat_enabled_at_issue']
            : (bool) $active_company->vat_enabled;

        $invoice = DB::transaction( function () use ( $attrs, $active_company, $exchangeRateService, $effectiveVatEnabled ) {
            $now = now();
            $year = (int) $now->year;
            $month = (int) $now->month;

            $attrs[ 'company_id' ] = $active_company->id;

            $counterQuery = DB::table( 'invoice_counters' )
                ->where( 'company_id', $active_company->id )
                ->where( 'year', $year )
                ->where( 'month', $month );

            $counter = $counterQuery->lockForUpdate()->first();

            if ( $counter ) {
                $sequence = (int) $counter->next_number;
                $counterQuery->update( [ 'next_number' => $sequence + 1 ] );
            } else {
                $existingCount = $active_company
                    ->invoices()
                    ->whereYear( 'issue_date', $year )
                    ->whereMonth( 'issue_date', $month )
                    ->count();
                $sequence = max( (int) $active_company->invoice_start_number, $existingCount + 1 );

                try {
                    DB::table( 'invoice_counters' )->insert( [
                        'company_id'  => $active_company->id,
                        'year'        => $year,
                        'month'       => $month,
                        'next_number' => $sequence + 1,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ] );
                } catch ( QueryException $e ) {
                    $counter = $counterQuery->lockForUpdate()->first();

                    if ( ! $counter ) {
                        throw $e;
                    }

                    $sequence = (int) $counter->next_number;
                    $counterQuery->update( [ 'next_number' => $sequence + 1 ] );
                }
            }

            $providedNumber = isset( $attrs[ 'invoice_number' ] ) && trim( (string) $attrs[ 'invoice_number' ] ) !== ''
                ? trim( (string) $attrs[ 'invoice_number' ] )
                : null;
            $attrs[ 'invoice_number' ] = $providedNumber ?: sprintf( '%d-%02d-%03d', $year, $month, $sequence );
            $issueDate = isset( $attrs[ 'issue_date' ] ) && $attrs[ 'issue_date' ]
                ? Carbon::parse( $attrs[ 'issue_date' ] )
                : $now->copy();
            $dueDate = isset( $attrs[ 'due_date' ] ) && $attrs[ 'due_date' ]
                ? Carbon::parse( $attrs[ 'due_date' ] )
                : $issueDate->copy()->addDays( (int) ( $active_company->payment_due_days ?? 0 ) );
            $attrs[ 'issue_date' ]     = $issueDate->toDateString();
            $attrs[ 'due_date' ]       = $dueDate->toDateString();
            $attrs[ 'currency' ]       = $attrs[ 'currency' ] ?? $active_company->currency ?? 'RSD';
            $attrs[ 'total' ]          = 0;
            $attrs[ 'status' ]         = InvoiceStatus::DRAFT;

            $currency = strtoupper( (string) ( $attrs['currency'] ?? 'RSD' ) );
            $bankAccount = $this->resolveInvoiceBankAccount(
                $active_company,
                $currency,
                $attrs['bank_account_id'] ?? null
            );
            $this->applyBankAccountSnapshot($attrs, $bankAccount);
            $ratePayload = $exchangeRateService->getTodayRate( $currency );
            $fxRateToRsd = (float) $ratePayload['exchange_middle'];

            $subtotalOriginal = 0.0;
            $taxOriginal = 0.0;

            foreach ( $attrs[ 'items' ] as $item ) {
                $subTotal  = (float) $item[ 'quantity' ] * (float) $item[ 'price' ];
                $taxAmount = $effectiveVatEnabled
                    ? ( ( $subTotal * (float) $active_company->default_tax_percent ) / 100 )
                    : 0.0;

                $subtotalOriginal += $subTotal;
                $taxOriginal += $taxAmount;
            }

            $totalOriginal = $subtotalOriginal + $taxOriginal;
            $subtotalRsd = $currency === 'RSD' ? $subtotalOriginal : $subtotalOriginal * $fxRateToRsd;
            $taxRsd = $currency === 'RSD' ? $taxOriginal : $taxOriginal * $fxRateToRsd;
            $totalRsd = $currency === 'RSD' ? $totalOriginal : $totalOriginal * $fxRateToRsd;

            $attrs['fx_rate_to_rsd'] = $fxRateToRsd;
            $attrs['fx_provider'] = (string) ( $ratePayload['provider'] ?? 'unknown' );
            $attrs['fx_date'] = (string) ( $ratePayload['date'] ?? now()->toDateString() );
            $attrs['subtotal_original'] = round( $subtotalOriginal, 2 );
            $attrs['tax_original'] = round( $taxOriginal, 2 );
            $attrs['total_original'] = round( $totalOriginal, 2 );
            $attrs['subtotal_rsd'] = round( $subtotalRsd, 2 );
            $attrs['tax_rsd'] = round( $taxRsd, 2 );
            $attrs['total_rsd'] = round( $totalRsd, 2 );
            $attrs['vat_enabled_at_issue'] = $effectiveVatEnabled;

            $invoice = Invoice::create( $attrs );

            foreach ( $attrs[ 'items' ] as $item ) {
                $sub_total  = (float) $item[ 'quantity' ] * (float) $item[ 'price' ];
                $tax_amount = $effectiveVatEnabled ? ( ( $sub_total * (float) $active_company->default_tax_percent ) / 100 ) : 0;
                $total      = $sub_total + $tax_amount;

                $invoice->items()->create(
                    [
                        'name'        => $item[ 'name' ],
                        'quantity'    => $item[ 'quantity' ],
                        'price'       => $item[ 'price' ],
                        'sub_total'   => $sub_total,
                        'total'       => $total,
                        'tax_amount'  => $tax_amount,
                        'description' => $item[ 'description' ] ?? $item[ 'name' ],
                        'income_category' => $item[ 'income_category' ] ?? null,
                    ]
                );
            }

            $invoice->update( [ 'total' => $invoice->items->sum( 'total' ) ] );

            InvoiceRevision::create([
                'invoice_id' => $invoice->id,
                'acting_user_id' => auth()->id(),
                'action_type' => 'created',
                'previous_values' => null,
                'new_values' => $this->invoiceAuditSnapshot($invoice->load('items')),
                'changed_fields' => ['invoice', 'items'],
            ]);

            return $invoice;
        } );

        return response()->json( $invoice->load( [ 'items', 'client' ] ), 201 );
    }

    /**
     * Display the specified resource.
     */
    public function show( Invoice $invoice ) {
        $this->authorize( 'view', $invoice );
        $user          = auth()->user();
        $invoice_items = $invoice->items;
        $company       = $invoice->company;
        $client        = $invoice->client;

        return response()->json( compact( 'invoice', 'user', 'invoice_items', 'company', 'client' ) );
    }

    public function history(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'invoice' => $invoice->only(['id', 'invoice_number', 'company_id']),
            'revisions' => $invoice->revisions()
                ->with('actingUser:id,first_name,last_name,email')
                ->latest()
                ->get(),
        ]);
    }

    /**
     *
     * @param \App\Models\Invoice $invoice
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function generatePDF( Request $request, Invoice $invoice ) {
        $this->authorize( 'view', $invoice );
        $force_refresh = $request->boolean( 'refresh' );
        $relative_path = $this->ensureInvoicePdf( $invoice, $force_refresh );
        $file_name     = "faktura-{$invoice->invoice_number}.pdf";

        return Storage::disk( 'local' )->download( $relative_path, $file_name );
    }

    public function pdfVersions(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return response()->json($invoice->pdfVersions()->get());
    }

    public function emailHistory(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return response()->json($invoice->emailSends()->with('pdfVersion:id,version,path')->get());
    }

    public function downloadPdfVersion(Invoice $invoice, int $version)
    {
        $this->authorize('view', $invoice);

        $version = InvoicePdfVersion::query()
            ->where('invoice_id', $invoice->id)
            ->where('version', $version)
            ->firstOrFail();

        abort_unless(
            (int) $version->company_id === (int) $invoice->company_id
                && (int) $version->invoice_id === (int) $invoice->id,
            404
        );

        abort_unless(Storage::disk('local')->exists($version->path), 404, 'PDF verzija nije pronađena.');

        return Storage::disk('local')->download($version->path, "faktura-{$invoice->invoice_number}-v{$version->version}.pdf");
    }

    public function sendEmail( Request $request, Invoice $invoice ) {
        $this->authorize( 'view', $invoice );

        $client = $invoice->client;
        $data = $request->validate([
            'recipient' => ['sometimes', 'nullable', 'email', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);
        $recipient = $data['recipient'] ?? $client?->email;
        if (! $recipient) {
            return response()->json([
                'message' => 'Unesite email adresu primaoca.',
                'errors' => ['recipient' => ['Email adresa primaoca je obavezna.']],
            ], 422);
        }
        $subject = $data['subject'] ?? "Faktura #{$invoice->invoice_number}";
        $message = $data['message'] ?? null;
        $attempt = InvoiceEmailSend::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
        ]);

        try {
            $relative_path = $this->ensureInvoicePdf( $invoice );
            // Protect before transport, but only mark as sent after the
            // transport accepts the message.
            $version = $this->protectCurrentPdfVersion($invoice, false);
            $attempt->update([
                'invoice_pdf_version_id' => $version->id,
                'status' => 'sending',
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $attempt->update([
                'status' => 'failed',
                'failure_message' => 'PDF ili priprema slanja nije uspela.',
                'failed_at' => now(),
            ]);

            return response()->json(['message' => 'PDF nije pripremljen za slanje.'], 500);
        }

        try {
            Mail::to( $recipient )->send( new InvoiceMail( $invoice, $relative_path, $subject, $message ) );
        } catch (\Throwable $exception) {
            report($exception);
            $attempt->update([
                'status' => 'failed',
                'failure_message' => 'Transport nije prihvatio poruku.',
                'failed_at' => now(),
            ]);

            return response()->json(['message' => 'Email nije poslat. PDF verzija je ostala zaštićena.'], 502);
        }

        try {
            $attempt->update(['status' => 'accepted', 'accepted_at' => now()]);
            $version->update(['sent_at' => $version->sent_at ?? now()]);
            if ( $invoice->status !== InvoiceStatus::SENT ) {
                $invoice->update( [ 'status' => InvoiceStatus::SENT ] );
            }
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Email je prihvaćen, ali status nije automatski ažuriran. Proverite istoriju slanja.'], 500);
        }

        return response()->json( [
            'message' => 'Email je poslat.',
            'status' => 'accepted',
            'invoice_email_send_id' => $attempt->id,
            'invoice_pdf_version_id' => $version->id,
            'invoice' => $invoice->fresh(),
        ] );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Invoice $invoice ) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, KpoEntryService $kpoEntryService): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $invoice);

        $activeCompany = $request->user()->activeCompany;
        if (! $activeCompany || (int) $invoice->company_id !== (int) $activeCompany->id) {
            return response()->json(['message' => 'Faktura ne pripada aktivnoj firmi.'], 403);
        }

        $data = $request->validated();
        $markingSent = (($data['status'] ?? null) === InvoiceStatus::SENT->value)
            && $invoice->status !== InvoiceStatus::SENT->value;
        $hadCurrentPdf = (bool) $invoice->pdf_path;

        if ($markingSent) {
            // Generate before changing status so a renderer failure cannot mark
            // an invoice as sent without a PDF to protect.
            $this->ensureInvoicePdf($invoice);
        }
        $kpoConfirmation = $data['kpo_confirmation'] ?? null;
        unset($data['kpo_confirmation']);
        $hasKpoConfirmation = is_array($kpoConfirmation) && (bool) ($kpoConfirmation['confirmed'] ?? false);
        $kpoConfirmationFingerprint = is_array($kpoConfirmation)
            ? (string) ($kpoConfirmation['preview_fingerprint'] ?? '')
            : null;
        $expectedKpoConfirmationFingerprint = $kpoEntryService->invoiceEditConfirmationFingerprint($invoice, $data);
        $editableFields = ['client_id', 'invoice_number', 'issue_date', 'currency', 'items', 'include_in_kpo', 'vat_enabled_at_issue'];
        $isFullEdit = collect($editableFields)->contains(static fn (string $field): bool => array_key_exists($field, $data));
        $hasPdfRelevantEdit = $isFullEdit || array_key_exists('note', $data);

        $requestedVatStatus = array_key_exists('vat_enabled_at_issue', $data)
            ? (bool) $data['vat_enabled_at_issue']
            : $invoice->vat_enabled_at_issue;
        $requestedStatus = $data['status'] ?? $invoice->status;
        $requestedPaidAt = array_key_exists('paid_at', $data) ? $data['paid_at'] : $invoice->paid_at;

        if (array_key_exists('status', $data)
            && $requestedStatus === InvoiceStatus::PAID->value
            && ! $requestedPaidAt) {
            throw ValidationException::withMessages([
                'paid_at' => ['Datum uplate je obavezan za plaćenu fakturu.'],
            ]);
        }
        $vatStatusChanged = array_key_exists('vat_enabled_at_issue', $data)
            && $requestedVatStatus !== $invoice->vat_enabled_at_issue;

        if ($vatStatusChanged && ! (bool) ($data['confirm_vat_status_change'] ?? false)) {
            return response()->json([
                'message' => 'Promena istorijskog PDV statusa zahteva eksplicitnu potvrdu.',
                'vat_status_confirmation' => [
                    'from' => $invoice->vat_enabled_at_issue,
                    'to' => $requestedVatStatus,
                ],
                'requires_confirmation' => true,
            ], 409);
        }

        if ($isFullEdit && $invoice->vat_enabled_at_issue === null && ! array_key_exists('vat_enabled_at_issue', $data)) {
            return response()->json([
                'message' => 'Istorijski PDV status nije potvrđen. Izaberite vrednost pre izmene fakture.',
                'errors' => ['vat_enabled_at_issue' => ['Potrebno je eksplicitno potvrditi istorijski PDV status.']],
            ], 422);
        }

        try {
            $updated = DB::transaction(function () use (
                $invoice,
                $data,
                $activeCompany,
                $kpoEntryService,
                $isFullEdit,
                $vatStatusChanged,
                $hasKpoConfirmation,
                $kpoConfirmation,
                $kpoConfirmationFingerprint
            ): Invoice {
            $lockedInvoice = Invoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
            $previous = $this->invoiceAuditSnapshot($lockedInvoice);

            if ($hasKpoConfirmation) {
                $currentFingerprint = $kpoEntryService->invoiceEditConfirmationFingerprint($lockedInvoice, $data);
                if (! $kpoConfirmationFingerprint || ! hash_equals($kpoConfirmationFingerprint, $currentFingerprint)) {
                    throw ValidationException::withMessages([
                        'kpo_confirmation.preview_fingerprint' => ['Potvrda KPO konflikta je zastarela. Učitajte fakturu ponovo.'],
                    ]);
                }
            }

            if (isset($data['client_id']) && ! $activeCompany->clients()->whereKey($data['client_id'])->exists()) {
                abort(422, 'Izabrani klijent ne pripada aktivnoj firmi.');
            }

            if (array_key_exists('invoice_number', $data)) {
                $invoiceNumber = trim((string) $data['invoice_number']);
                $duplicateExists = Invoice::query()
                    ->where('company_id', $activeCompany->id)
                    ->whereRaw('TRIM(invoice_number) = ?', [$invoiceNumber])
                    ->where('id', '!=', $lockedInvoice->getKey())
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'invoice_number' => ['Broj fakture već postoji u aktivnoj firmi.'],
                    ]);
                }

                // Keep the exact legacy value when the user submits the same
                // logical number; only normalize an actually changed number.
                $data['invoice_number'] = trim((string) $lockedInvoice->invoice_number) === $invoiceNumber
                    ? $lockedInvoice->invoice_number
                    : $invoiceNumber;
            }

            $itemsProvided = array_key_exists('items', $data);
            $vatEnabled = array_key_exists('vat_enabled_at_issue', $data)
                ? (bool) $data['vat_enabled_at_issue']
                : (bool) $lockedInvoice->vat_enabled_at_issue;
            $currency = strtoupper((string) ($data['currency'] ?? $lockedInvoice->currency));
            $fxRate = $lockedInvoice->fx_rate_to_rsd;

            $bankAccountSelectionProvided = array_key_exists('bank_account_id', $data)
                && $data['bank_account_id'] !== null;
            $bankAccount = null;
            if ($bankAccountSelectionProvided) {
                $bankAccount = $this->resolveInvoiceBankAccount($activeCompany, $currency, $data['bank_account_id']);
            } elseif ($currency !== strtoupper((string) $lockedInvoice->currency)) {
                $bankAccount = $this->resolveInvoiceBankAccount($activeCompany, $currency, null);
                if (! $bankAccount) {
                    throw ValidationException::withMessages([
                        'bank_account_id' => ['Pri promeni valute izaberite aktivan račun u novoj valuti.'],
                    ]);
                }
            } elseif ($lockedInvoice->bank_account_id) {
                $bankAccount = $lockedInvoice->bankAccount;
            }

            if ($bankAccountSelectionProvided || $currency !== strtoupper((string) $lockedInvoice->currency)) {
                $this->applyBankAccountSnapshot($data, $bankAccount);
            }

            if ($currency !== strtoupper((string) $lockedInvoice->currency)) {
                $ratePayload = app(ExchangeRateService::class)->getTodayRate($currency);
                $fxRate = (float) $ratePayload['exchange_middle'];
                $data['fx_provider'] = (string) ($ratePayload['provider'] ?? 'unknown');
                $data['fx_date'] = (string) ($ratePayload['date'] ?? now()->toDateString());
            }

            if ($itemsProvided) {
                $existingItems = $lockedInvoice->items->keyBy('id');
                $submittedExistingIds = [];

                foreach ($data['items'] as $item) {
                    if (isset($item['id'])) {
                        if (! $existingItems->has((int) $item['id'])) {
                            abort(422, 'Jedna ili više stavki ne pripada fakturi.');
                        }
                        $submittedExistingIds[] = (int) $item['id'];
                    }
                }

                foreach ($existingItems as $existingItem) {
                    if (! in_array((int) $existingItem->id, $submittedExistingIds, true)) {
                        $existingItem->delete();
                    }
                }

                foreach ($data['items'] as $item) {
                    $subTotal = (float) $item['quantity'] * (float) $item['price'];
                    $taxAmount = $vatEnabled
                        ? ($subTotal * (float) $activeCompany->default_tax_percent) / 100
                        : 0.0;
                    $values = [
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'sub_total' => $subTotal,
                        'total' => $subTotal + $taxAmount,
                        'tax_amount' => $taxAmount,
                        'description' => $item['description'] ?? $item['name'],
                        'income_category' => $item['income_category'] ?? null,
                    ];

                    if (isset($item['id'])) {
                        $existingItems->get((int) $item['id'])->update($values);
                    } else {
                        $lockedInvoice->items()->create($values);
                    }
                }
            }

            if ($vatStatusChanged && ! $itemsProvided) {
                foreach ($lockedInvoice->items as $existingItem) {
                    $subTotal = (float) $existingItem->quantity * (float) $existingItem->price;
                    $taxAmount = $vatEnabled
                        ? ($subTotal * (float) $activeCompany->default_tax_percent) / 100
                        : 0.0;
                    $existingItem->update([
                        'sub_total' => $subTotal,
                        'tax_amount' => $taxAmount,
                        'total' => $subTotal + $taxAmount,
                    ]);
                }
            }

            $lockedInvoice->load('items');
            $subtotalOriginal = $lockedInvoice->items->sum(static fn (InvoiceItem $item): float => (float) $item->sub_total);
            $taxOriginal = $lockedInvoice->items->sum(static fn (InvoiceItem $item): float => (float) $item->tax_amount);
            $totalOriginal = $subtotalOriginal + $taxOriginal;
            $subtotalRsd = $currency === 'RSD' ? $subtotalOriginal : $subtotalOriginal * (float) $fxRate;
            $taxRsd = $currency === 'RSD' ? $taxOriginal : $taxOriginal * (float) $fxRate;
            $totalRsd = $currency === 'RSD' ? $totalOriginal : $totalOriginal * (float) $fxRate;

            $invoiceValues = collect($data)->except(['items', 'vat_enabled_at_issue', 'confirm_vat_status_change'])->all();
            $invoiceValues['currency'] = $currency;
            $invoiceValues['fx_rate_to_rsd'] = $currency === 'RSD' ? 1 : $fxRate;
            $invoiceValues['subtotal_original'] = round($subtotalOriginal, 2);
            $invoiceValues['tax_original'] = round($taxOriginal, 2);
            $invoiceValues['total_original'] = round($totalOriginal, 2);
            $invoiceValues['subtotal_rsd'] = round($subtotalRsd, 2);
            $invoiceValues['tax_rsd'] = round($taxRsd, 2);
            $invoiceValues['total_rsd'] = round($totalRsd, 2);
            $invoiceValues['total'] = round($totalOriginal, 2);
            $invoiceValues['vat_enabled_at_issue'] = $vatEnabled;
            if (($invoiceValues['status'] ?? $lockedInvoice->status) !== InvoiceStatus::PAID->value) {
                $invoiceValues['paid_at'] = null;
                $invoiceValues['payment_note'] = null;
            }
            $lockedInvoice->fill($invoiceValues);
            $lockedInvoice->save();
            $lockedInvoice->load(['items', 'client']);

            if ($isFullEdit) {
                $kpoEntryService->synchronizeAfterInvoiceUpdate(
                    $lockedInvoice,
                    $hasKpoConfirmation && (bool) ($kpoConfirmation['confirm_manual_overwrite'] ?? false),
                    $hasKpoConfirmation,
                    $hasKpoConfirmation
                );
            }

            $current = $this->invoiceAuditSnapshot($lockedInvoice);
            if ($previous !== $current) {
                InvoiceRevision::create([
                    'invoice_id' => $lockedInvoice->id,
                    'acting_user_id' => auth()->id(),
                    'action_type' => 'updated',
                    'previous_values' => $previous,
                    'new_values' => $current,
                    'changed_fields' => $this->changedInvoiceFields($previous, $current),
                ]);
            }

            return $lockedInvoice;
            });
        } catch (KpoInvoiceConflictException $exception) {
            return response()->json([
                'message' => 'Izmena fakture zahteva potvrdu KPO promena.',
                'conflict' => $exception->conflict,
                'preview_fingerprint' => $expectedKpoConfirmationFingerprint,
                'requires_confirmation' => true,
            ], 409);
        }

        if ($markingSent) {
            $this->protectCurrentPdfVersion($updated, true);
        } elseif ($hasPdfRelevantEdit && $hadCurrentPdf) {
            // The old version remains immutable; the edited invoice gets a new
            // current version after the database transaction has committed.
            $this->ensureInvoicePdf($updated, true);
        }

        return response()->json($updated->load(['pdfVersions']));
    }

    public function updateItemClassifications(UpdateInvoiceItemClassificationsRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $activeCompany = $request->user()->activeCompany;
        if (! $activeCompany || (int) $invoice->company_id !== (int) $activeCompany->id) {
            return response()->json([
                'message' => 'Faktura ne pripada aktivnoj firmi.',
            ], 403);
        }

        $items = $request->validated()['items'];
        $itemIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $invoiceItems = InvoiceItem::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        if ($invoiceItems->count() !== count($itemIds)) {
            return response()->json([
                'message' => 'Jedna ili više stavki ne pripada izabranoj fakturi.',
            ], 422);
        }

        DB::transaction(static function () use ($items, $invoiceItems): void {
            foreach ($items as $item) {
                $invoiceItems->get((int) $item['id'])->update([
                    'income_category' => $item['income_category'],
                ]);
            }
        });

        return response()->json($invoice->load(['items', 'client']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Invoice $invoice, KpoEntryService $kpoEntryService) {
        $this->authorize( 'delete', $invoice );

        $activeCompany = $request->user()->activeCompany;
        if (! $activeCompany || (int) $invoice->company_id !== (int) $activeCompany->id) {
            return response()->json(['message' => 'Faktura ne pripada aktivnoj firmi.'], 403);
        }

        $linkedEntries = KpoEntry::query()
            ->where('company_id', $invoice->company_id)
            ->where('source_invoice_id', $invoice->id)
            ->whereNull('deleted_at')
            ->orderBy('entry_number')
            ->get(['id', 'entry_number', 'income_category', 'amount_rsd', 'adjustment_rsd', 'total_rsd']);

        if ($linkedEntries->isNotEmpty() && ! $request->boolean('confirm_kpo_removal')) {
            return response()->json([
                'message' => 'Faktura ima aktivne KPO zapise koji će biti kontrolisano uklonjeni.',
                'requires_confirmation' => true,
                'kpo_deletion_confirmation' => [
                    'reason' => 'source_invoice_deleted',
                    'entries' => $linkedEntries->values(),
                ],
            ], 409);
        }

        DB::transaction(function () use ($invoice, $kpoEntryService): void {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $kpoEntryService->softDeleteEntriesForInvoice($lockedInvoice, 'source_invoice_deleted');
            $lockedInvoice->delete();
        });

        return response()->noContent();
    }

    private function ensureInvoicePdf( Invoice $invoice, bool $force_refresh = false ): string {
        $existing_path = $invoice->pdf_path;
        if ($existing_path && Storage::disk('local')->exists($existing_path)) {
            $this->ensureCurrentPdfVersion($invoice, $existing_path);
        }

        if ( ! $force_refresh && $existing_path && Storage::disk( 'local' )->exists( $existing_path ) ) {
            return $existing_path;
        }

        $invoice_items = $invoice->items;
        $company       = $invoice->company;
        $client        = $invoice->client;
        $date          = $invoice->issue_date instanceof Carbon ? $invoice->issue_date : Carbon::parse( $invoice->issue_date );
        [$reservedVersion, $relative_path] = $this->reserveInvoicePdfVersion($invoice, $date);

        Storage::disk( 'local' )->makeDirectory( dirname( $relative_path ) );

        $html = $this->renderInvoicePdfHtml( $invoice, $company, $client, $invoice_items );

        try {
            Pdf::html( $html )
                ->format( Format::A4 )
                ->withBrowsershot( function ( Browsershot $browsershot ) {
                    $browsershot->setOption( 'args', [ '--no-sandbox' ] );
                })
                ->disk( 'local' )
                ->save( $relative_path );

            $publishedPath = $this->publishLatestAvailablePdfVersion($invoice);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($reservedVersion): void {
                InvoicePdfVersion::query()
                    ->whereKey($reservedVersion->id)
                    ->whereNull('protected_at')
                    ->delete();
            });
            if (Storage::disk('local')->exists($relative_path)) {
                Storage::disk('local')->delete($relative_path);
            }

            throw $exception;
        }

        $this->applyPdfRetention($invoice);

        return $publishedPath;
    }

    private function publishLatestAvailablePdfVersion(Invoice $invoice): string
    {
        return DB::transaction(function () use ($invoice): string {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $versions = InvoicePdfVersion::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('version')
                ->get();
            $current = $versions->first(
                static fn (InvoicePdfVersion $version): bool => Storage::disk('local')->exists($version->path)
            );

            if (! $current) {
                throw new \RuntimeException('Generisani PDF nije dostupan za objavu.');
            }

            $lockedInvoice->update(['pdf_path' => $current->path]);
            $invoice->setRawAttributes($lockedInvoice->getAttributes());
            $invoice->syncOriginal();

            return $current->path;
        }, 3);
    }

    private function reserveInvoicePdfVersion(Invoice $invoice, Carbon $date): array
    {
        return DB::transaction(function () use ($invoice, $date): array {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $nextVersion = ((int) InvoicePdfVersion::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->max('version')) + 1;
            $relativePath = "invoices/{$date->format('Y')}/{$date->format('m')}/{$lockedInvoice->id}/v{$nextVersion}/faktura-{$lockedInvoice->invoice_number}.pdf";
            $version = InvoicePdfVersion::create([
                'company_id' => $lockedInvoice->company_id,
                'invoice_id' => $lockedInvoice->id,
                'version' => $nextVersion,
                'path' => $relativePath,
                'created_at' => now(),
            ]);

            return [$version, $relativePath];
        }, 3);
    }

    private function ensureCurrentPdfVersion(Invoice $invoice, string $path): InvoicePdfVersion
    {
        $existing = InvoicePdfVersion::query()
            ->where('invoice_id', $invoice->id)
            ->where('path', $path)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($invoice, $path): InvoicePdfVersion {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $existing = InvoicePdfVersion::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('path', $path)
                ->first();
            if ($existing) {
                return $existing;
            }

            $version = ((int) InvoicePdfVersion::query()->where('invoice_id', $lockedInvoice->id)->max('version')) + 1;

            return InvoicePdfVersion::create([
                'company_id' => $lockedInvoice->company_id,
                'invoice_id' => $lockedInvoice->id,
                'version' => $version,
                'path' => $path,
                'created_at' => $lockedInvoice->updated_at ?? now(),
            ]);
        }, 3);
    }

    private function protectCurrentPdfVersion(Invoice $invoice, bool $sent): InvoicePdfVersion
    {
        $path = $this->ensureInvoicePdf($invoice);
        $version = InvoicePdfVersion::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->where('path', $path)
            ->firstOrFail();

        $values = ['protected_at' => $version->protected_at ?? now()];
        if ($sent) {
            $values['sent_at'] = $version->sent_at ?? now();
        }
        $version->update($values);

        return $version->fresh();
    }

    private function applyPdfRetention(Invoice $invoice): void
    {
        $currentPath = $invoice->pdf_path;
        $limit = $invoice->company?->invoicePdfMaxUnprotectedVersions() ?? 5;
        $query = InvoicePdfVersion::query()
            ->where('company_id', $invoice->company_id)
            ->where('invoice_id', $invoice->id)
            ->whereNull('protected_at')
            ->where('path', '!=', $currentPath)
            ->whereDoesntHave('emailSends')
            ->orderBy('version');
        $versions = $query->get();

        foreach ($versions->take(max(0, $versions->count() - $limit)) as $version) {
            if (! Storage::disk('local')->exists($version->path)) {
                throw new \RuntimeException("PDF verzija {$version->id} nije pronađena; zadržavanje podataka je prekinuto.");
            }
            if (! Storage::disk('local')->delete($version->path)) {
                throw new \RuntimeException("PDF verzija {$version->id} nije mogla biti obrisana sa diska.");
            }
            $version->delete();
        }
    }

    private function resolveInvoiceBankAccount(Company $company, string $currency, mixed $bankAccountId): ?CompanyBankAccount
    {
        $currency = strtoupper($currency);
        $accounts = $company->bankAccounts()->where('currency', $currency)->where('is_active', true)->get();

        if ($bankAccountId !== null) {
            $account = $accounts->firstWhere('id', (int) $bankAccountId);
            if (! $account) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Izabrani račun ne pripada aktivnoj firmi, nije aktivan ili nije u valuti fakture.'],
                ]);
            }

            return $account;
        }

        if ($accounts->isEmpty()) {
            return null;
        }

        $defaults = $accounts->where('is_default', true);
        if ($defaults->isNotEmpty()) {
            return $defaults->first();
        }

        if ($accounts->count() === 1) {
            return $accounts->first();
        }

        throw ValidationException::withMessages([
            'bank_account_id' => ['Izaberite račun za valutu fakture; postoji više aktivnih računa bez podrazumevanog računa.'],
        ]);
    }

    private function applyBankAccountSnapshot(array &$values, ?CompanyBankAccount $account): void
    {
        $values['bank_account_id'] = $account?->id;
        $values['bank_account_bank_name'] = $account?->bank_name;
        $values['bank_account_number'] = $account?->account_number;
        $values['bank_account_iban'] = $account?->iban;
        $values['bank_account_swift_bic'] = $account?->swift_bic;
        $values['bank_account_currency'] = $account?->currency;
        $values['bank_account_payment_instructions'] = $account?->payment_instructions;
    }

    /** @return array<string, mixed> */
    private function invoiceAuditSnapshot(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice->only([
                'id', 'company_id', 'client_id', 'invoice_number', 'issue_date', 'due_date',
                'currency', 'fx_rate_to_rsd', 'fx_provider', 'fx_date', 'payment_method',
                'subtotal_original', 'tax_original', 'total_original', 'subtotal_rsd',
                'tax_rsd', 'total_rsd', 'total', 'vat_enabled_at_issue', 'include_in_kpo',
                'status', 'note', 'bank_account_id', 'bank_account_bank_name',
                'paid_at', 'payment_note',
                'bank_account_number', 'bank_account_iban', 'bank_account_swift_bic',
                'bank_account_currency', 'bank_account_payment_instructions',
            ]),
            'items' => $invoice->items->map(static fn (InvoiceItem $item): array => $item->only([
                'id', 'name', 'description', 'quantity', 'price', 'sub_total', 'tax_amount',
                'total', 'income_category',
            ]))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $previous @param array<string, mixed> $current */
    private function changedInvoiceFields(array $previous, array $current): array
    {
        $changed = [];
        foreach (array_keys($current['invoice']) as $field) {
            if (($previous['invoice'][$field] ?? null) !== ($current['invoice'][$field] ?? null)) {
                $changed[] = $field;
            }
        }

        if ($previous['items'] !== $current['items']) {
            $changed[] = 'items';
        }

        return $changed;
    }

    private function renderInvoicePdfHtml(
        Invoice $invoice,
        $company,
        $client,
        $invoice_items
    ): string {
        $payload = $this->buildInvoiceDocumentPayload( $invoice, $company, $client, $invoice_items );
        $document_html = $this->renderInvoiceDocumentWithSsr( $payload );
        $css = $this->resolveViteCssContents( 'resources/js/main.tsx' );

        $title = 'Faktura ' . $invoice->invoice_number;

        return '<!DOCTYPE html>'
            . '<html lang="' . e( str_replace( '_', '-', app()->getLocale() ) ) . '">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e( $title ) . '</title>'
            . '<style>' . $css . '</style>'
            . '</head>'
            . '<body class="invoices-pdf">'
            . $document_html
            . '</body>'
            . '</html>';
    }

    private function buildInvoiceDocumentPayload(
        Invoice $invoice,
        $company,
        $client,
        $invoice_items
    ): array {
        $logo_url = null;
        $hasBankSnapshot = (bool) $invoice->bank_account_id
            || $invoice->bank_account_bank_name !== null
            || $invoice->bank_account_number !== null
            || $invoice->bank_account_iban !== null
            || $invoice->bank_account_swift_bic !== null
            || $invoice->bank_account_payment_instructions !== null;
        $legacyBankFallbackAllowed = ! $hasBankSnapshot
            && strtoupper((string) $invoice->currency) === strtoupper((string) $company->currency);
        $legacyBankAccount = $legacyBankFallbackAllowed ? $company->bank_account : null;
        $legacyIban = $legacyBankFallbackAllowed ? $company->iban : null;
        $legacySwift = $legacyBankFallbackAllowed ? $company->swift : null;
        if ( $company?->logo_path ) {
            $logo_path = (string) $company->logo_path;
            if ( str_starts_with( $logo_path, 'http://' ) || str_starts_with( $logo_path, 'https://' ) ) {
                $logo_url = $logo_path;
            } else {
                $disk = Storage::disk( 'public' );
                $relative_path = ltrim( $logo_path, '/' );

                if ( $disk->exists( $relative_path ) ) {
                    $file_contents = $disk->get( $relative_path );
                    $mime = $disk->mimeType( $relative_path ) ?: 'application/octet-stream';
                    $logo_url = 'data:' . $mime . ';base64,' . base64_encode( $file_contents );
                } else {
                    $public_url = $disk->url( $relative_path );
                    $logo_url = url( $public_url );
                }
            }
        }

        return [
            'invoice' => [
                'id'       => $invoice->id,
                'number'   => (string) $invoice->invoice_number,
                'date'     => (string) $invoice->issue_date,
                'dueDate'  => (string) $invoice->due_date,
                'currency' => $invoice->currency,
                'fxRateToRsd' => (float) ( $invoice->fx_rate_to_rsd ?? 1 ),
                'paymentMethod' => (string) $invoice->payment_method,
                'total'    => (float) $invoice->total,
                'subtotalOriginal' => (float) ($invoice->subtotal_original ?? $invoice->total),
                'taxOriginal' => (float) ($invoice->tax_original ?? 0),
                'totalOriginal' => (float) ( $invoice->total_original ?? $invoice->total ),
                'totalRsd' => (float) ( $invoice->total_rsd ?? $invoice->total ),
                'vatEnabledAtIssue' => $invoice->vat_enabled_at_issue,
                'bankAccountBankName' => $invoice->bank_account_bank_name ?? null,
                'bankAccountNumber' => $invoice->bank_account_number ?? $legacyBankAccount,
                'bankAccountIban' => $invoice->bank_account_iban ?? $legacyIban,
                'bankAccountSwiftBic' => $invoice->bank_account_swift_bic ?? $legacySwift,
                'bankAccountCurrency' => $invoice->bank_account_currency ?? $invoice->currency,
                'bankAccountPaymentInstructions' => $invoice->bank_account_payment_instructions,
                'items'    => $invoice_items->map( static function ( $item ) {
                    return [
                        'id'          => $item->id,
                        'description' => (string) ( $item->description ?? $item->name ?? '' ),
                        'quantity'    => (float) $item->quantity,
                        'price'       => (float) $item->price,
                    ];
                } )->values()->all(),
            ],
            'company' => [
                'id'                   => $company->id,
                'name'                 => $company->name,
                'tax_id'               => $company->tax_id,
                'registration_number'  => $company->registration_number,
                'address'              => $company->address,
                'city'                 => $company->city,
                'country'              => $company->country,
                'email'                => $company->email,
                'phone'                => $company->phone,
                'bank_account'         => $invoice->bank_account_number ?? $legacyBankAccount,
                'iban'                 => $invoice->bank_account_iban ?? $legacyIban,
                'swift'                => $invoice->bank_account_swift_bic ?? $legacySwift,
                'currency'             => $company->currency,
                'vat_enabled'          => (bool) $company->vat_enabled,
                'logoUrl'              => $logo_url,
            ],
            'client' => [
                'id'                  => $client->id,
                'name'                => $client->name,
                'email'               => $client->email,
                'address'             => $client->address,
                'city'                => $client->city,
                'country'             => $client->country,
                'phone'               => $client->phone,
                'tax_id'              => $client->tax_id,
                'registration_number' => $client->registration_number,
                'client_type'          => (string) $client->client_type,
            ],
            'currency' => $invoice->currency ?: $company->currency,
            'meta' => [
                'countries'       => config( 'countries' ),
                'currencies'      => config( 'currency' ),
                'payment_methods' => config( 'payment' ),
                'client_types'    => config( 'client-type' ),
            ],
        ];
    }

    private function renderInvoiceDocumentWithSsr( array $payload ): string {
        $script_path = base_path( 'scripts/render-invoice-ssr.mjs' );
        $bundle_path = base_path( 'bootstrap/ssr/renderInvoiceDocument.js' );

        if ( ! file_exists( $script_path ) || ! file_exists( $bundle_path ) ) {
            throw new \RuntimeException( 'SSR bundle not found. Run \"npm run build:ssr\" before generating PDFs.' );
        }

        $process = new Process( [ 'node', $script_path ], base_path(), null, json_encode( $payload ), 20 );
        $process->run();

        if ( ! $process->isSuccessful() ) {
            $error = trim( $process->getErrorOutput() ?: $process->getOutput() );
            throw new \RuntimeException( $error ?: 'SSR render failed.' );
        }

        return $process->getOutput();
    }

    private function resolveViteCssContents( string $entry ): string {
        $manifest_path = public_path( 'build/manifest.json' );
        if ( ! file_exists( $manifest_path ) ) {
            throw new \RuntimeException( 'Vite manifest not found. Run \"npm run build\" before generating PDFs.' );
        }

        $manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
        if ( ! is_array( $manifest ) ) {
            throw new \RuntimeException( 'Invalid Vite manifest.' );
        }

        $entry_data = $manifest[ $entry ] ?? null;
        if ( ! is_array( $entry_data ) ) {
            throw new \RuntimeException( "Vite entry {$entry} not found in manifest." );
        }

        $css_files = $entry_data['css'] ?? [];
        if ( ! is_array( $css_files ) || $css_files === [] ) {
            return '';
        }

        $css = '';
        foreach ( $css_files as $file ) {
            $path = public_path( 'build/' . ltrim( (string) $file, '/' ) );
            if ( file_exists( $path ) ) {
                $css .= (string) file_get_contents( $path );
            }
        }

        return $css;
    }
}
