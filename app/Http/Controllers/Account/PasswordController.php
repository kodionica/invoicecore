<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller {
    public function edit() {
        return view( 'account.password', [ 'user' => auth()->user() ] );
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
            throw ValidationException::withMessages(
                [
                    'current_password' => 'Pogrešna trenutna šifra.',
                ]
            );
        }

        $user->update(
            [
                'password' => Hash::make( $passwords[ 'password' ] ),
            ]
        );

        return redirect()->back()->with( 'flash', [
            'message' => 'Šifra uspešno promenjena.',
            'type'    => 'success',
        ] );
    }
}
