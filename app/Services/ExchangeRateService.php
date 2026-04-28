<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService {
    /**
     * @return array{
     *   currency:string,
     *   exchange_middle:float,
     *   provider:string,
     *   date:string,
     *   raw:array<mixed>
     * }
     */
    public function getTodayRate( string $currency ): array {
        $code = strtoupper( trim( $currency ) );
        if ( ! preg_match( '/^[A-Z]{3}$/', $code ) ) {
            throw new \InvalidArgumentException( 'Neispravan kod valute.' );
        }

        if ( $code === 'RSD' ) {
            return [
                'currency'        => 'RSD',
                'exchange_middle' => 1.0,
                'provider'        => 'internal',
                'date'            => now()->toDateString(),
                'raw'             => [],
            ];
        }

        $cacheKey = "fx:today:{$code}";
        return Cache::remember( $cacheKey, now()->endOfDay(), static function () use ( $code ) {
            $response = Http::timeout( 10 )
                ->acceptJson()
                ->get( "https://kurs.resenje.org/api/v1/currencies/{$code}/rates/today" );

            if ( ! $response->ok() ) {
                throw new \RuntimeException( 'Neuspesno preuzimanje dnevnog kursa.' );
            }

            $data = $response->json();
            $exchangeMiddle = (float) ( $data['exchange_middle'] ?? 0 );
            if ( $exchangeMiddle <= 0 ) {
                throw new \RuntimeException( 'Neispravan srednji kurs.' );
            }

            $date = isset( $data['date'] ) ? Carbon::parse( (string) $data['date'] )->toDateString() : now()->toDateString();

            return [
                'currency'        => $code,
                'exchange_middle' => $exchangeMiddle,
                'provider'        => 'kurs.resenje.org',
                'date'            => $date,
                'raw'             => is_array( $data ) ? $data : [],
            ];
        } );
    }
}
