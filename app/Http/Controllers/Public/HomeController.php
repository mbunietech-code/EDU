<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::published()
            ->featured()
            ->withCount('plans')
            ->get();

        $products = Product::published()
            ->withCount('plans')
            ->get();

        return view('public.home', compact('featuredProducts', 'products'));
    }
}
