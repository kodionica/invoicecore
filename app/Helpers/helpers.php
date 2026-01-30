<?php

if ( ! function_exists( 'body_classes' ) ) {
    function body_classes( array $classes = [] ): string {
        $default_classes = [
            Auth::user() ? 'user--logged-in' : 'user--guest',
            'request--' . str_replace( '.', '-', request()->route()?->getName() ?: 'no-route' ),
        ];

        $all_classes = array_merge( $default_classes, $classes );

        return implode( ' ', $all_classes );
    }
}

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
