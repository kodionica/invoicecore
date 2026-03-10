<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller {
    public function show( Request $request ) {
        $defaults = [
            'language'      => 'sr-Latn',
            'notifications' => [
                'invoices' => true,
                'clients'  => false,
            ],
        ];

        $storedSetting = Setting::query()->where( 'key', 'app_settings' )->first();
        $stored = [];
        if ( $storedSetting && $storedSetting->value ) {
            $decoded = json_decode( $storedSetting->value, true );
            if ( is_array( $decoded ) ) {
                $stored = $decoded;
            }
        }

        $settings = array_merge( $defaults, $stored );
        $settings['notifications'] = array_merge(
            $defaults['notifications'],
            $stored['notifications'] ?? []
        );

        return response()->json( $settings );
    }

    public function update( Request $request ) {
        $data = $request->validate( [
            'language'               => [ 'sometimes', 'string', 'max:10' ],
            'notifications'          => [ 'sometimes', 'array' ],
            'notifications.invoices' => [ 'sometimes', 'boolean' ],
            'notifications.clients'  => [ 'sometimes', 'boolean' ],
        ] );

        $storedSetting = Setting::query()->where( 'key', 'app_settings' )->first();
        $current = [];
        if ( $storedSetting && $storedSetting->value ) {
            $decoded = json_decode( $storedSetting->value, true );
            if ( is_array( $decoded ) ) {
                $current = $decoded;
            }
        }

        $next = array_merge( $current, $data );

        if ( array_key_exists( 'notifications', $data ) ) {
            $next['notifications'] = array_merge(
                $current['notifications'] ?? [],
                $data['notifications'] ?? []
            );
        }

        Setting::query()->updateOrCreate(
            [ 'key' => 'app_settings' ],
            [ 'value' => json_encode( $next, JSON_UNESCAPED_UNICODE ) ]
        );

        return response()->json( [
            'message'  => 'Podešavanja sačuvana.',
            'settings' => $next,
        ] );
    }
}
