<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

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

            $attrs[ 'company_id' ]     = $active_company->id;
            $sequence                  = $active_company
                    ->invoices()
                    ->whereYear( 'issue_date', $now->year )
                    ->whereMonth( 'issue_date', $now->month )
                    ->count() + 1;
            $attrs[ 'invoice_number' ] = sprintf( '%d-%02d-%03d', $now->year, $now->month, $sequence );
            $attrs[ 'issue_date' ]     = $now->toDateString();
            $attrs[ 'due_date' ]       = $now->addDays( (int) $attrs[ 'due_date' ] )->toDateString();
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
     * TODO: Create rest api route to generate pdf and download
     *
     * @param \App\Models\Invoice $invoice
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generatePDF( Invoice $invoice ) {
        $this->authorizeInvoice( $invoice );
        $user          = auth()->user() ?? $invoice->company->user;
        $invoice_items = $invoice->items;
        $company       = $invoice->company;
        $client        = $invoice->client;
        $date          = $invoice->issue_date instanceof Carbon ? $invoice->issue_date : Carbon::parse( $invoice->issue_date );
        $relative_path = "invoices/{$date->format('Y')}/{$date->format('m')}/faktura-{$invoice->invoice_number}.pdf";

        File::ensureDirectoryExists( dirname( $relative_path ) );

        Pdf::view( 'invoices.show-pdf', compact( 'invoice', 'user', 'invoice_items', 'company', 'client' ) )
            ->format( Format::A4 )
            ->disk( 'local' )
            ->save( $relative_path );

        $invoice->update( [ 'pdf_path' => $relative_path ] );

        return response()->json( [
            'pdf_path' => $relative_path,
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
}
