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
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]
        );

        $employer_attributes = $request->validate(
            [
                'employer' => 'required|string|max:255',
                'logo'     => 'nullable|image|max:2048',
            ]
        );

        $user = \App\Models\User::create( $user_attributes );

        $logo_path = $request->file( 'logo' )?->store( 'logos' );

        $user->employer()->create(
            [
                'name' => $employer_attributes[ 'employer' ],
                'logo' => $logo_path,
            ]
        );

        Auth::login( $user );

        return redirect( '/' );
    }
}
