<?php

namespace App\Http\Controllers;

use App\Models\User;
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
                'first_name' => 'nullable|string|max:255',
                'last_name'  => 'nullable|string|max:255',
                'email'      => 'required|string|email|max:255|unique:users,email',
                'password'   => 'required|string|min:5|confirmed',
                'phone'      => 'nullable|string',
            ]
        );

        $user_attributes[ 'username' ] = User::generateUsername( $user_attributes[ 'email' ] );

        $user = User::create( $user_attributes );

        Auth::login( $user );

        return redirect( '/' );
    }
}
