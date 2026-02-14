<?php

use App\Models\Setting;

if ( ! function_exists( 'get_currencies' ) ) {
    function get_currencies(): array {
        return collect( config( 'currency' ) )
            ->map( fn( $currency ) => $currency[ 'name' ] . ' (' . $currency[ 'symbol' ] . ')' )
            ->all();
    }
}

function setting( string $key, $default = null ) {
    return cache()->remember(
        "setting:$key",
        3600,
        fn() => Setting::where( 'key', $key )->value( 'value' ) ?? $default
    );
}
