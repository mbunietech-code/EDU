<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(protected CurrencyRateService $currencyRates)
    {
    }

    public function index()
    {
        $featuredProducts = Product::published()
            ->featured()
            ->withCount('plans')
            ->get();

        $products = Product::published()
            ->withCount('plans')
            ->get();

        $rates = $this->currencyRates->rates();

        return view('public.home', compact('featuredProducts', 'products', 'rates'));
    }
}
