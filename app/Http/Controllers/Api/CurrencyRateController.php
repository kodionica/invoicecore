<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class CurrencyRateController extends Controller {
    public function __invoke( string $currency ): JsonResponse {
        $code = strtoupper( trim( $currency ) );

        if ( ! preg_match( '/^[A-Z]{3}$/', $code ) ) {
            return response()->json( [
                'message' => 'Neispravan kod valute.',
            ], 422 );
        }

        $response = Http::timeout( 10 )
            ->acceptJson()
            ->get( "https://kurs.resenje.org/api/v1/currencies/{$code}/rates/today" );

        if ( ! $response->ok() ) {
            return response()->json( [
                'message' => 'Neuspesno preuzimanje dnevnog kursa.',
            ], 502 );
        }

        return response()->json( $response->json() );
    }
}
