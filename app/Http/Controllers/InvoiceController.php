<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $invoices = auth()->user()->activeCompany->invoices()->latest()->get();

        return view( 'invoices.index', compact( 'invoices' ) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        $active_company = auth()->user()->activeCompany;

        if ( ! $active_company ) {
            return redirect()->route( 'companies.create' )->with( 'flash', [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi se mogao dodati klijent.',
                'type'    => 'error',
            ] );
        }

        $clients       = $active_company->clients->pluck( 'name', 'id' )->toArray();
        $currencies    = get_currencies();
        $payment_types = config( 'payment' );

        return view( 'invoices.create', compact( 'clients', 'currencies', 'payment_types', 'active_company' ) );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws \Throwable
     */
    public function store( StoreInvoiceRequest $request ) {
        $invoice = DB::transaction( static function () use ( $request ) {
            $now                       = now();
            $attrs                     = $request->validated();
            $user                      = auth()->user();
            $active_company            = $user->activeCompany;
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
                $tax_amount = $active_company->vat_enabled ? ( ( $sub_total * (float) $active_company->tax_rate ) / 100 ) : 0;
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
        } );

        return redirect()
            ->route( 'invoices.index' )
            ->with(
                'status',
                [
                    'type'    => 'success',
                    'message' => 'Faktura je napravljena.',
                ]
            );
    }

    /**
     * Display the specified resource.
     */
    public function show( Invoice $invoice ) {
        $user          = \Auth::user();
        $invoice_items = $invoice->items;

        return view( 'invoices.show', compact( 'invoice', 'user', 'invoice_items' ) );
    }

    /**
     * TODO: Create rest api route to generate pdf and download
     *
     * @param \App\Models\Invoice $invoice
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generatePDF( Invoice $invoice ) {
        $user          = \Auth::user();
        $invoice_items = $invoice->items;

        if ( ! $user ) {
            $user = $invoice->user;
        }

        $date = $invoice->invoice_date;
        $path = storage_path( "app/private/invoices/{$date->format('Y')}/{$date->format('m')}/faktura-{$invoice->invoice_number}.pdf" );

        File::ensureDirectoryExists( dirname( $path ) );

        Pdf::view( 'invoices.show-pdf', compact( 'invoice', 'user', 'invoice_items' ) )
            ->format( Format::A4 )
            ->save( $path );

        return redirect()
            ->route( 'invoices.show', compact( 'invoice', 'user', 'invoice_items' ) )
            ->with(
                'status',
                [
                    'type'    => 'success',
                    'message' => 'PDF with Invoice created successfully.',
                ]
            );
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Invoice $invoice ) {
        //
    }
}
