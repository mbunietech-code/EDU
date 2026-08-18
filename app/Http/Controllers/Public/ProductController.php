<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return view('public.products.index');
    }

    public function show(Product $product)
    {
        if ($product->status !== 'published' && ! auth()->check()) {
            abort(404);
        }

        $product->load(['plans' => function ($query) {
            $query->active()->orderBy('sort_order');
        }]);

        return view('public.products.show', compact('product'));
    }
}