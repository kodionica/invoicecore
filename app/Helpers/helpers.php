<?php

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
