<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;

class CurrencyRateController extends Controller {
    public function __invoke( string $currency, ExchangeRateService $exchangeRateService ): JsonResponse {
        try {
            $rate = $exchangeRateService->getTodayRate( $currency );
        } catch ( \InvalidArgumentException $exception ) {
            return response()->json( [
                'message' => $exception->getMessage(),
            ], 422 );
        } catch ( \Throwable $exception ) {
            return response()->json( [
                'message' => 'Neuspesno preuzimanje dnevnog kursa.',
            ], 502 );
        }

        return response()->json( $rate['raw'] !== [] ? $rate['raw'] : [
            'currency'        => $rate['currency'],
            'exchange_middle' => $rate['exchange_middle'],
            'date'            => $rate['date'],
        ] );
    }
}
