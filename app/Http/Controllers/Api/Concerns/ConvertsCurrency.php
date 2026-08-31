<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\CurrencyRateService;

trait ConvertsCurrency
{
    /**
     * Live (or fallback) multipliers for 1 TZS -> target currency, so the
     * client can show approximate USD / CNY prices next to the TZS price.
     *
     * @return array{usd: float, cny: float}
     */
    protected function currencyRates(): array
    {
        $rates = app(CurrencyRateService::class)->rates();

        return [
            'usd' => (float) ($rates['USD'] ?? config('currency.tzs_to_usd')),
            'cny' => (float) ($rates['CNY'] ?? config('currency.tzs_to_cny')),
        ];
    }
}
