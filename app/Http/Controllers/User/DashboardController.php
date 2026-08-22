<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected CurrencyRateService $currencyRates)
    {
    }

    public function index()
    {
        $user = auth()->user();

        $subscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->latest()
            ->take(5)
            ->get();

        $activeSubscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->where('status', 'active')
            ->get();

        $pendingOrders = $user->orders()
            ->with(['product', 'plan', 'tool'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $featuredProducts = Product::published()
            ->featured()
            ->withCount('plans')
            ->get();

        $rates = $this->currencyRates->rates();

        return view('user.dashboard', compact('subscriptions', 'activeSubscriptions', 'pendingOrders', 'featuredProducts', 'rates'));
    }
}