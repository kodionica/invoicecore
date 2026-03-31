<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $invoices = $active_company->invoices()
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
    public function store( StoreInvoiceRequest $request ) {
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

        $invoice = DB::transaction( static function () use ( $attrs, $active_company ) {
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

            $invoice = Invoice::create( $attrs );

            foreach ( $attrs[ 'items' ] as $item ) {
                $sub_total  = (float) $item[ 'quantity' ] * (float) $item[ 'price' ];
                $tax_amount = $active_company->vat_enabled ? ( ( $sub_total * (float) $active_company->default_tax_percent ) / 100 ) : 0;
                $total      = $sub_total + $tax_amount;

                $invoice->items()->create(
                    [
                        'name'        => $item[ 'name' ],
                        'quantity'    => $item[ 'quantity' ],
                        'price'       => $item[ 'price' ],
                        'sub_total'   => $sub_total,
                        'total'       => $total,
                        'tax_amount'  => $tax_amount,
                        'description' => $item[ 'description' ] ?? null,
                    ]
                );
            }

            $invoice->update( [ 'total' => $invoice->items->sum( 'total' ) ] );

            return $invoice;
        } );

        return response()->json( $invoice->load( [ 'items', 'client' ] ), 201 );
    }

    /**
     * Display the specified resource.
     */
    public function show( Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );
        $user          = auth()->user();
        $invoice_items = $invoice->items;
        $company       = $invoice->company;
        $client        = $invoice->client;

        return response()->json( compact( 'invoice', 'user', 'invoice_items', 'company', 'client' ) );
    }

    /**
     *
     * @param \App\Models\Invoice $invoice
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function generatePDF( Request $request, Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );
        $force_refresh = $request->boolean( 'refresh' );
        $relative_path = $this->ensureInvoicePdf( $invoice, $force_refresh );
        $file_name     = "faktura-{$invoice->invoice_number}.pdf";

        return Storage::disk( 'local' )->download( $relative_path, $file_name );
    }

    public function sendEmail( Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );

        $client = $invoice->client;
        if ( ! $client?->email ) {
            return response()->json( [
                'message' => 'Klijent nema email adresu.',
            ], 422 );
        }

        $relative_path = $this->ensureInvoicePdf( $invoice );

        Mail::to( $client->email )->send( new InvoiceMail( $invoice, $relative_path ) );

        if ( $invoice->status !== InvoiceStatus::SENT ) {
            $invoice->update( [ 'status' => InvoiceStatus::SENT ] );
        }

        return response()->json( [
            'message' => 'Email je poslat.',
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
    public function update( Request $request, Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );

        $data = $request->validate( [
            'status'         => [ 'sometimes', Rule::in( array_map( static fn( $case ) => $case->value, InvoiceStatus::cases() ) ) ],
            'due_date'       => [ 'sometimes', 'date' ],
            'payment_method' => [ 'sometimes', 'string' ],
            'note'           => [ 'sometimes', 'string', 'nullable' ],
        ] );

        $invoice->fill( $data );

        if ( $invoice->isDirty() ) {
            $invoice->save();
        }

        return response()->json( $invoice->load( [ 'items', 'client' ] ) );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );

        $invoice->delete();

        return response()->noContent();
    }

    private function authorizeInvoice( Invoice $invoice ): void {
        if ( $invoice->company?->user_id !== auth()->id() ) {
            abort( 404 );
        }
    }

    private function ensureInvoicePdf( Invoice $invoice, bool $force_refresh = false ): string {
        $existing_path = $invoice->pdf_path;
        if ( $force_refresh && $existing_path && Storage::disk( 'local' )->exists( $existing_path ) ) {
            Storage::disk( 'local' )->delete( $existing_path );
            $invoice->update( [ 'pdf_path' => null ] );
            $existing_path = null;
        }

        if ( $existing_path && Storage::disk( 'local' )->exists( $existing_path ) ) {
            return $existing_path;
        }

        $invoice_items = $invoice->items;
        $company       = $invoice->company;
        $client        = $invoice->client;
        $date          = $invoice->issue_date instanceof Carbon ? $invoice->issue_date : Carbon::parse( $invoice->issue_date );
        $relative_path = "invoices/{$date->format('Y')}/{$date->format('m')}/faktura-{$invoice->invoice_number}.pdf";

        Storage::disk( 'local' )->makeDirectory( dirname( $relative_path ) );

        $html = $this->renderInvoicePdfHtml( $invoice, $company, $client, $invoice_items );

        Pdf::html( $html )
            ->format( Format::A4 )
            ->withBrowsershot( function ( Browsershot $browsershot ) {
                $browsershot->setOption( 'args', [ '--no-sandbox' ] );
            })
            ->disk( 'local' )
            ->save( $relative_path );

        $invoice->update( [ 'pdf_path' => $relative_path ] );

        return $relative_path;
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
                'paymentMethod' => (string) $invoice->payment_method,
                'total'    => (float) $invoice->total,
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
                'bank_account'         => $company->bank_account,
                'iban'                 => $company->iban,
                'swift'                => $company->swift,
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
                'clientType'          => (string) $client->client_type,
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
