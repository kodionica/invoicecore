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
                'login'    => 'required|string',
                'password' => 'required',
            ]
        );

        // Check if login is an email or username
        $login_type = filter_var( $request->login, FILTER_VALIDATE_EMAIL ) ? 'email' : 'username';

        if ( ! Auth::attempt( [ $login_type => $attributes[ 'login' ], 'password' => $attributes[ 'password' ] ], $request->has( 'remember' ) ) ) {
            return back()->withErrors( [ 'login' => 'Invalid credentials' ] )->onlyInput( 'login' );
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
