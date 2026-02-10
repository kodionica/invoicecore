<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanySettingsRequest;
use App\Models\Company;
use App\Models\CompanySettings;

class CompanySettingsController extends Controller {
    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Company $company ) {
        $company_settings = $company->settings;

        return view( 'companies.settings', compact( 'company_settings', 'company' ) );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateCompanySettingsRequest $request, Company $company ) {
        $data             = $request->validated();
        $company_settings = $company->settings;

        // Fill company settings with validated data
        $company_settings->fill( $data );

        // Check if some data has been changed
        if ( $company_settings->isDirty() ) {
            $company_settings->save();
        }

        return redirect()->route( 'company.settings.edit', $company )->with( 'flash', [
            'message' => 'Podešavanja firme su sačuvana.',
            'type'    => 'success',
        ] );
    }
}
