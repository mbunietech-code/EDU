<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyRateService
{
    protected string $endpoint = 'https://api.exchangerate-api.com/v4/latest/';

    protected array $targets = ['USD', 'CNY'];

    public function rates(): array
    {
        $cached = Cache::get('currency_rates');

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)->get($this->endpoint.config('currency.base'));

            if ($response->ok()) {
                $source = $response->json('rates');

                $rates = [];
                foreach ($this->targets as $target) {
                    if (isset($source[$target]) && $source[$target] > 0) {
                        $rates[$target] = (float) $source[$target];
                    }
                }

                if (count($rates) === count($this->targets)) {
                    Cache::put('currency_rates', $rates, now()->addHours(config('currency.cache_ttl_hours', 12)));

                    return $rates;
                }
            }
        } catch (\Throwable) {
            // fall through to fallback rates
        }

        return [
            'USD' => (float) config('currency.tzs_to_usd'),
            'CNY' => (float) config('currency.tzs_to_cny'),
        ];
    }

    public function convert(float|int $amount, string $target): ?float
    {
        $rates = $this->rates();

        return isset($rates[$target]) ? round($amount * $rates[$target], 2) : null;
    }
}