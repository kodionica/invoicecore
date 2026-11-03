<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetaController extends Controller {
    public function __invoke(): JsonResponse {
        $data = Cache::remember( 'meta:public:client-profiles', 3600, static function () {
            $statusLabels = [
                InvoiceStatus::DRAFT->value     => 'Nacrt',
                InvoiceStatus::SENT->value      => 'Izdata',
                InvoiceStatus::PAID->value      => 'Plaćeno',
                InvoiceStatus::OVERDUE->value   => 'Kasni',
                InvoiceStatus::CANCELLED->value => 'Otkazano',
            ];

            $invoiceStatuses = array_map( static function ( InvoiceStatus $status ) use ( $statusLabels ) {
                return [
                    'key'   => $status->value,
                    'label' => $statusLabels[ $status->value ] ?? $status->value,
                ];
            }, InvoiceStatus::cases() );

            return [
                'countries'        => config( 'countries' ),
                'currencies'       => config( 'currency' ),
                'payment_methods'  => config( 'payment' ),
                'client_countries' => \App\Support\ClientProfile::countries(),
                'invoice_languages' => \App\Support\ClientProfile::LANGUAGES,
                'client_types'     => config( 'client-type' ),
                'invoice_statuses' => $invoiceStatuses,
            ];
        } );

        return response()->json( $data );
    }
}
