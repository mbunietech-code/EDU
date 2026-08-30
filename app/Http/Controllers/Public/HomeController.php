<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use App\Services\HomeTickerService;

class HomeController extends Controller
{
    public function index(HomeTickerService $ticker)
    {
        $scholarships = Scholarship::published()
            ->open()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        $tickerItems = $ticker->cachedItems();

        return view('public.home', compact('scholarships', 'tickerItems'));
    }
}
