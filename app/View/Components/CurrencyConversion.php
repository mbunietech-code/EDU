<?php

namespace App\View\Components;

use App\Services\CurrencyRateService;
use Illuminate\View\Component;

class CurrencyConversion extends Component
{
    public array $rates;

    public function __construct(
        public float|int $amount,
        protected CurrencyRateService $ratesService
    ) {
        $this->rates = $ratesService->rates();
    }

    public function render()
    {
        return view('components.currency-conversion');
    }
}