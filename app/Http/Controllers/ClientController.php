<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $clients = \Auth::user()->clients;

        return view( 'clients.index', compact( 'clients' ) );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return view( 'clients.create' );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ): \Illuminate\Http\RedirectResponse {
        $attrs = $request->validate(
            [
                'name'           => 'required|string|max:255',
                'email'          => 'required|email|max:255',
                'address'        => 'required|string|max:255',
                'country'        => 'required|string|max:255',
                'vat_number'     => 'nullable|string|max:255',
                'company_number' => 'nullable|string|max:255',
            ]
        );

        Auth::user()->clients()->create( $attrs );

        return redirect()
            ->route( 'clients.index' )
            ->with(
                'status',
                [
                    'type'    => 'success',
                    'message' => 'Client created successfully.',
                ]
            );
    }

    /**
     * Display the specified resource.
     */
    public function show( Client $client ) {
        return view( 'clients.edit', [ 'client' => $client ] );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Client $client ) {
        return view( 'clients.edit', [ 'client' => $client ] );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( Request $request, Client $client ): \Illuminate\Http\RedirectResponse {
        if ( $client->user_id !== Auth::id() ) {
            abort( 403 );
        }

        $attrs = $request->validate(
            [
                'name'           => 'required|string|max:255',
                'email'          => 'required|email|max:255',
                'address'        => 'required|string|max:255',
                'country'        => 'required|string|max:255',
                'vat_number'     => 'nullable|string|max:255',
                'company_number' => 'nullable|string|max:255',
            ]
        );

        $client->update( $attrs );

        return redirect()
            ->route( 'clients.edit', [ 'client' => $client->id ] )
            ->with(
                'status',
                [
                    'type'    => 'success',
                    'message' => 'Client updated successfully.',
                ]
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Client $client ): \Illuminate\Http\RedirectResponse {
        $client->delete();

        return redirect()->route( 'clients.index' );
    }
}
