<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceSettingController extends Controller {
    /**
     * Show the form for editing the specified resource.
     */
    public function edit( InvoiceSetting $invoice ) {
        $settings = \Auth::user()->invoiceSettings()->firstOrNew();

        return view( 'settings.index', compact( 'settings' ) );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( Request $request, InvoiceSetting $invoice ) {
        $data = $request->validate(
            [
                'company_name'     => 'required|string|max:255',
                'company_address'  => 'required|string|max:255',
                'company_email'    => 'required|email|max:255',
                'company_phone'    => 'required|string|max:50',
                'company_state'    => 'required|string|max:50',
                'bank_account'     => 'required|string|max:50',
                'logo'             => 'nullable|mimes:jpeg,png,jpg,webp,svg|max:2048',
                'pib'              => 'required|string|max:50',
                'mb'               => 'required|string|max:50',
                'iban'             => 'nullable|string|max:50',
                'swift'            => 'nullable|string|max:50',
                'default_currency' => 'required|string|max:3',
                'default_due_days' => 'required|integer|min:1',
                'footer_note'      => 'nullable|string',
            ]
        );

        if ( $request->hasFile( 'logo' ) ) {
            $file         = $request->file( 'logo' );
            $originalName = pathinfo( $file->getClientOriginalName(), PATHINFO_FILENAME );
            $extension    = $file->getClientOriginalExtension();
            $safeName     = Str::slug( $originalName );
            $filename     = $safeName . '-' . uniqid( '', true ) . '.' . $extension;

            $data[ 'logo_path' ] = $file->storeAs( 'logos', $filename, 'public' );
        }

        \Auth::user()->invoiceSettings()->updateOrCreate(
            [ 'user_id' => \Auth::id() ],
            $data
        );

        return back()->with( 'status', [ 'type' => 'success', 'message' => 'Podešavanja sačuvana.' ] );
    }
}
