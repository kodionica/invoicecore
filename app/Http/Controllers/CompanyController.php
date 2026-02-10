<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
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
        return view( 'companies.create' );
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

            unset( $data[ 'logo' ] );
        }

        $data[ 'user_id' ] = auth()->id();

        Company::create( $data );

        return redirect()->route( 'company.index' )->with( 'flash', [
            'message' => 'Firma je uspešno kreirana.',
            'type'    => 'success',
        ] );
    }

    /**
     * Display the specified resource.
     */
    public function show( Company $company ) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Company $company ) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( Request $request, Company $company ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Company $company ) {
        //
    }
}
