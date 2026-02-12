<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanySwitchController extends Controller {
    public function switch( Request $request ) {
        $request->validate( [
                                'company_id' => 'required|exists:companies,id',
                            ] );

        $company = auth()->user()
            ->companies()
            ->where( 'id', $request->company_id )
            ->firstOrFail();

        auth()->user()->update( [
                                    'active_company_id' => $company->id,
                                ] );

        return back()->with( 'flash', [
            'message' => 'Izabrana firma je promenjena.',
            'type'    => 'success',
        ] );
    }
}
