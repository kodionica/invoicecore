<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller {
    public function edit() {
        return view( 'account.profile', [ 'user' => auth()->user() ] );
    }

    public function update( Request $request ) {
        $user_attributes = $request->validate(
            [
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'phone'      => 'string',
            ]
        );

        $user = auth()->user();
        $user->update( $user_attributes );

        return redirect()->back()->with( 'flash', [
            'message' => 'Profil uspešno ažuriran.',
            'type'    => 'success',
        ] );
    }
}
