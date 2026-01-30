<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use Illuminate\Http\Request;

class InvoiceSettingController extends Controller {
    /**
     * Show the form for editing the specified resource.
     */
    public function edit( InvoiceSetting $invoice ) {
        $settings = \Auth::user()->invoiceSettings()->firstOrNew();

        return view( 'settings.invoice', compact( 'settings' ) );
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
                'pib'              => 'required|string|max:50',
                'iban'             => 'nullable|string|max:50',
                'swift'            => 'nullable|string|max:50',
                'default_currency' => 'required|string|max:3',
                'default_due_days' => 'required|integer|min:1',
                'footer_note'      => 'nullable|string',
            ]
        );

        \Auth::user()->invoiceSettings()->updateOrCreate(
            [ 'user_id' => \Auth::id() ],
            $data
        );

        return back()->with( 'status', [ 'type' => 'success', 'message' => 'Settings saved.' ] );
    }
}
