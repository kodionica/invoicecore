<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('meta:public', 3600, function () {
            return [
                'countries' => config('countries'),
                'currencies' => config('currency'),
                'payment_methods' => config('payment'),
            ];
        });

        return response()->json($data);
    }
}
