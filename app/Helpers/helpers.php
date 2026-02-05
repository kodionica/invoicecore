<?php

use App\Models\Setting;

if ( ! function_exists( 'get_currencies' ) ) {
    function get_currencies(): array {
        return [
            [
                'id'   => 'EUR',
                'name' => 'Euro',
            ],
            [
                'id'   => 'RSD',
                'name' => 'Serbian Dinar',
            ],
            [
                'id'   => 'USD',
                'name' => 'US Dollar',
            ],
            [
                'id'   => 'GBP',
                'name' => 'British Pound',
            ],
        ];
    }
}

if ( ! function_exists( 'get_states' ) ) {
    function get_states(): array {
        return [
            [
                'id'   => 'SER',
                'name' => 'Srbija',
            ],
        ];
    }
}

function setting( string $key, $default = null ) {
    return cache()->remember(
        "setting:$key",
        3600,
        fn() => Setting::where( 'key', $key )->value( 'value' ) ?? $default
    );
}
