<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $companies = auth()->user()->companies()->latest()->get();

        return response()->json( $companies );
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

        $company = Company::create( array_merge( $data, [
            'user_id' => auth()->id(),
        ] ) );

        return response()->json( $company, 201 );
    }

    /**
     * Display the specified resource.
     */
    public function show( Company $company ) {
        $this->authorize( 'view', $company );

        return response()->json( $company );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateCompanyRequest $request, Company $company ) {
        $this->authorize( 'update', $company );
        $data = $request->validated();

        // If user wants to remove the logo
        if ( $request->boolean( 'remove_logo' ) ) {
            if ( $company->logo_path ) {
                Storage::disk( 'public' )->delete( $company->logo_path );
            }
            $data[ 'logo_path' ] = null;
        }

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

        return response()->json( $company );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Company $company ) {
        $this->authorize( 'delete', $company );

        $user = auth()->user();

        if ( $user->active_company_id === $company->id ) {
            $user->update( [ 'active_company_id' => null ] );
        }

        $company->delete();

        return response()->noContent();
    }
}
