<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('meta:public', 3600, function () {
            $statusLabels = [
                InvoiceStatus::DRAFT->value => 'Nacrt',
                InvoiceStatus::SENT->value => 'Poslato',
                InvoiceStatus::PAID->value => 'Plaćeno',
                InvoiceStatus::OVERDUE->value => 'Kasni',
                InvoiceStatus::CANCELLED->value => 'Otkazano',
            ];

            $invoiceStatuses = array_map(static function (InvoiceStatus $status) use ($statusLabels) {
                return [
                    'key' => $status->value,
                    'label' => $statusLabels[$status->value] ?? $status->value,
                ];
            }, InvoiceStatus::cases());

            return [
                'countries' => config('countries'),
                'currencies' => config('currency'),
                'payment_methods' => config('payment'),
                'invoice_statuses' => $invoiceStatuses,
            ];
        });

        return response()->json($data);
    }
}
