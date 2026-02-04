<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $invoices = \Auth::user()->invoices;

        return view( 'invoices.index', compact( 'invoices' ) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        $clients    = \Auth::user()->clients->map( fn( $client ) => [ 'id' => $client->id, 'name' => $client->name ] )->all();
        $currencies = get_currencies();

        return view( 'invoices.create', compact( 'clients', 'currencies' ) );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ) {
        $now   = now();
        $attrs = $request->validate(
            [
                'service'   => 'required|string|max:255',
                'quantity'  => 'required|numeric|min:1',
                'price'     => 'required|string|max:255',
                'client_id' => 'required|numeric',
                'currency'  => 'nullable|string',
            ]
        );

        $sequence                  = \Auth::user()
                ->invoices()
                ->whereYear( 'invoice_date', $now->year )
                ->whereMonth( 'invoice_date', $now->month )
                ->count() + 1;
        $attrs[ 'invoice_number' ] = sprintf( '%d-%02d-%03d', $now->year, $now->month, $sequence );
        $attrs[ 'invoice_date' ]   = $now->toDateString();
        $attrs[ 'due_date' ]       = $now->addDays( 7 )->toDateString();
        $attrs[ 'total_amount' ]   = (float) $attrs[ 'quantity' ] * (float) $attrs[ 'price' ];

        \Auth::user()->invoices()->create( $attrs );

        return redirect()
            ->route( 'invoices.index' )
            ->with(
                'status',
                [
                    'type'    => 'success',
                    'message' => 'Invoice created successfully.',
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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
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

        Pdf::view( 'invoices.show', compact( 'invoice', 'user', 'invoice_items' ) )
            ->format( Format::A4 )
            ->save( $path );

        return view( 'invoices.show', compact( 'invoice', 'user', 'invoice_items' ) )->with(
            'status',
            [
                'type'    => 'success',
                'message' => 'PDF generated successfully.',
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
