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
//        $jobs = Job::latest()->with( [ 'employer', 'tags' ] )->get()->groupBy( 'featured' );
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
    public function store( Request $request ) {
        $attrs = $request->validate(
            [
                'title'    => 'required|string|max:255',
                'salary'   => 'nullable|numeric|min:0',
                'location' => 'required|string|max:255',
                'schedule' => 'required|in:Full-time,Part-time,Contract,Internship',
                'url'      => 'required|url',
                'tags'     => 'nullable',
            ]
        );

        $attrs[ 'featured' ] = $request->has( 'featured' );

        $job = Auth::user()->employer->jobs()->create( Arr::except( $attrs, 'tags' ) );

        if ( isset( $attrs[ 'tags' ] ) ) {
            foreach ( explode( ',', $attrs[ 'tags' ] ) as $tag ) {
                $job->tag( $tag );
            }
        }

        return redirect( '/' );
    }

    /**
     * Display the specified resource.
     */
    public function show( Client $client ) {
        return view( 'clients.show', [ 'client' => $client ] );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( Client $client ) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update( Request $request, Client $client ): \Illuminate\Http\RedirectResponse {
        $attrs = $request->validate(
            [
                'name'           => 'required|string|max:255',
                'email'          => 'required|email|max:255',
                'address'        => 'required|string|max:255',
                'country'        => 'required|string|max:255',
                'vat_number'     => 'required|string|max:255',
                'company_number' => 'nullable|string|max:255',
            ]
        );

        Auth::user()->clients()->update( $attrs );

        return redirect()
            ->route( 'clients.show', [ 'client' => $client->id ] )
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
    public function destroy( Client $client ) {
        //
    }
}
