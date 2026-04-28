<?php

use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it( 'returns rsd fallback without remote call', function () {
    Http::fake();
    $service = new ExchangeRateService();

    $result = $service->getTodayRate( 'RSD' );

    expect( $result['exchange_middle'] )->toBe( 1.0 );
    expect( $result['provider'] )->toBe( 'internal' );
    Http::assertNothingSent();
} );

it( 'caches remote exchange rate for today', function () {
    Cache::flush();
    Http::fake( [
        'https://kurs.resenje.org/*' => Http::response( [
            'currency'        => 'EUR',
            'exchange_middle' => 117.3,
            'date'            => '2026-03-31',
        ], 200 ),
    ] );

    $service = new ExchangeRateService();

    $first = $service->getTodayRate( 'EUR' );
    $second = $service->getTodayRate( 'EUR' );

    expect( $first['exchange_middle'] )->toBe( 117.3 );
    expect( $second['exchange_middle'] )->toBe( 117.3 );
    Http::assertSentCount( 1 );
} );

it( 'throws for invalid currency code', function () {
    $service = new ExchangeRateService();

    $call = static fn() => $service->getTodayRate( 'EURO' );

    expect( $call )->toThrow( InvalidArgumentException::class );
} );
