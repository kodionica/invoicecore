<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $companies = auth()->user()->companies()->latest()->get();

        return view( 'companies.index', compact( 'companies' ) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        // Map currencies to select options
        $currencies = collect( config( 'currency' ) )
            ->map( fn( $currency ) => $currency[ 'name' ] . ' (' . $currency[ 'symbol' ] . ')' )
            ->all();

        return view( 'companies.create', compact( 'currencies' ) );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( StoreCompanyRequest $request ) {
        $data = $request->validated();

        if ( $request->hasFile( 'logo' ) ) {
            $file                = $request->file( 'logo' );
            $originalName        = pathinfo( $file->getClientOriginalName(), PATHINFO_FILENAME );
            $extension           = $file->getClientOriginalExtension();
            $safeName            = Str::slug( $originalName );
            $filename            = $safeName . '-' . uniqid( '', true ) . '.' . $extension;
            $data[ 'logo_path' ] = $file->storeAs( 'logos', $filename, 'public' );
        }

        unset( $data[ 'logo' ] );

        $data[ 'user_id' ] = auth()->id();

        $company = new Company();
        $company->fill( $data );
        $company->save();

        return redirect()->route( 'companies.index' )->with( 'flash', [
            'message' => 'Firma je uspešno kreirana.',
            'type'    => 'success',
        ] );
    }

    /**
     * Display the specified resource.
     */
    public function show( Company $company ) {
        // Map currencies to select options
        $currencies = collect( config( 'currency' ) )
            ->map( fn( $currency ) => $currency[ 'name' ] . ' (' . $currency[ 'symbol' ] . ')' )
            ->all();

        return view( 'companies.show', compact( 'company', 'currencies' ) );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Company $company ) {
        // Map currencies to select options
        $currencies = collect( config( 'currency' ) )
            ->map( fn( $currency ) => $currency[ 'name' ] . ' (' . $currency[ 'symbol' ] . ')' )
            ->all();

        return view( 'companies.show', compact( 'company', 'currencies' ) );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateCompanyRequest $request, Company $company ) {
        $data = $request->validated();

        // If user wants to remove the logo
//        if ($request->boolean('remove_logo')) {
//            if ($company->logo_path) {
//                Storage::disk('public')->delete($company->logo_path);
//            }
//            $data['logo_path'] = null;
//        }

        if ( $request->hasFile( 'logo' ) ) {
            $file                = $request->file( 'logo' );
            $originalName        = pathinfo( $file->getClientOriginalName(), PATHINFO_FILENAME );
            $extension           = $file->getClientOriginalExtension();
            $safeName            = Str::slug( $originalName );
            $filename            = $safeName . '-' . uniqid( '', true ) . '.' . $extension;
            $data[ 'logo_path' ] = $file->storeAs( 'logos', $filename, 'public' );
        }

        unset( $data[ 'logo' ], $data[ 'remove_logo' ] );

        // Fill company model with validated data
        $company->fill( $data );

        // Check if some data has been changed
        if ( $company->isDirty() ) {
            $company->save();
        }

        return redirect()->route( 'companies.edit', $company )->with( 'flash', [
            'message' => 'Podaci o firmi su ažurirani.',
            'type'    => 'success',
        ] );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Company $company ) {
        //
    }
}
