<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        // Get all clients for the active company
        $company = auth()->user()->activeCompany;

        if ( ! $company ) {
            return response()->json( [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi se mogao dodati klijent.',
            ], 422 );
        }

        $clients = Client::query()->forActiveCompany( $company->id )->get();

        return response()->json( $clients );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( StoreClientRequest $request ) {
        $data = $request->validated();

        $active_company = auth()->user()->activeCompany;

        if ( ! $active_company ) {
            return response()->json( [
                'message' => 'Korisnik nema aktivnu firmu. Napravi firmu da bi se mogao dodati klijent.',
            ], 422 );
        }

        $client = $active_company->clients()->create( $data );

        return response()->json( $client, 201 );
    }

    /**
     * Display the specified resource.
     */
    public function show( Client $client ) {
        $this->authorize( 'view', $client );

        return response()->json( $client );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( UpdateClientRequest $request, Client $client ) {
        $this->authorize( 'update', $client );
        $data = $request->validated();

        $client->fill( $data );

        // Check if some data has been changed
        if ( $client->isDirty() ) {
            $client->save();
        }

        return response()->json( $client );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy( Client $client ) {
        $this->authorize( 'delete', $client );

        $client->delete();

        return response()->noContent();
    }
}
