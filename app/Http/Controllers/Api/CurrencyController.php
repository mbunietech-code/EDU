<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ConvertsCurrency;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    use ConvertsCurrency;

    /**
     * Multipliers for 1 TZS -> USD / CNY so the app can show approximate
     * prices in all three currencies. Cached server-side for ~12h.
     */
    public function rates(): JsonResponse
    {
        return response()->json([
            'data' => array_merge($this->currencyRates(), [
                'base' => 'TZS',
                'symbols' => ['tzs' => 'TZS', 'usd' => '$', 'cny' => '¥'],
            ]),
        ]);
    }
}
