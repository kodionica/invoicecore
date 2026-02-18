<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\Request;

class ClientController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        // Get all clients for the active company
        $company = auth()->user()->activeCompany;

        if ( ! $company ) {
            return redirect()->route( 'companies.create' )->with( 'flash', [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi se mogao dodati klijent.',
                'type'    => 'error',
            ] );
        }

        $clients = $company->clients()->get();

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
    public function store( StoreClientRequest $request ) {
        $data = $request->validated();

        $active_company = auth()->user()->activeCompany;

        if ( ! $active_company ) {
            return redirect()->route( 'companies.create' )->with( 'flash', [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi se mogao dodati klijent.',
                'type'    => 'error',
            ] );
        }

        $active_company->clients()->create( $data );

        return redirect()->route( 'clients.index' )->with( 'flash', [
            'message' => 'Novi klijent je kreiran.',
            'type'    => 'success',
        ] );
    }

    /**
     * Display the specified resource.
     */
    public function show( Client $client ) {
        return view( 'clients.show', compact( 'client' ) );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Client $client ) {
        return view( 'clients.edit', compact( 'client' ) );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateClientRequest $request, Client $client ) {
        $data = $request->validated();

        $client->fill( $data );

        // Check if some data has been changed
        if ( $client->isDirty() ) {
            $client->save();
        }

        return redirect()->route( 'clients.edit', $client )->with( 'flash', [
            'message' => 'Klijent je ažuriran.',
            'type'    => 'success',
        ] );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Client $client ) {
        //
    }
}
