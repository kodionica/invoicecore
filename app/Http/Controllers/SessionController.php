<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller {
    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return view( 'auth.login' );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ) {
        $attributes = $request->validate(
            [
                'email'    => 'required|email',
                'password' => 'required',
            ]
        );

        if ( ! Auth::attempt( $attributes ) ) {
            return back()->withErrors( [ 'email' => 'Invalid credentials' ] )->onlyInput( 'email' );
        }

        $request->session()->regenerate();

        return redirect( '/' )->with( 'success', 'Logged in successfully' );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy() {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect( '/' )->with( 'success', 'Logged out successfully' );
    }
}
