<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;

class HomeController extends Controller
{
    public function index()
    {
        $scholarships = Scholarship::published()
            ->open()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        return view('public.home', compact('scholarships'));
    }
}
