<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterUserController extends Controller {
    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        return view( 'auth.register' );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store( Request $request ) {
        $user_attributes = $request->validate(
            [
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|string|email|max:255|unique:users,email',
                'password'   => 'required|string|min:5|confirmed',
                'phone'      => 'string',
            ]
        );

        $user = \App\Models\User::create( $user_attributes );

        Auth::login( $user );

        return redirect( '/' );
    }
}
