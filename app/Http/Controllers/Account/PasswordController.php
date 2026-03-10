<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller {
    public function edit() {
        return response()->json( [ 'user' => auth()->user() ] );
    }

    public function update( Request $request ) {
        $passwords = $request->validate(
            [
                'current_password' => 'required',
                'password'         => 'required|string|min:5|confirmed',
            ]
        );

        $user = auth()->user();

        if ( ! Hash::check( $passwords[ 'current_password' ], $user->password ) ) {
            return response()->json( [
                'message' => 'Trenutna lozinka nije ispravna.',
            ], 422 );
        }

        $user->update(
            [
                'password' => Hash::make( $passwords[ 'password' ] ),
            ]
        );

        return response()->json( [
            'message' => 'Lozinka uspešno promenjena.',
        ], 200 );
    }
}
