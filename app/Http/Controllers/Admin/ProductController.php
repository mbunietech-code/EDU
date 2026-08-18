<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::withCount('plans')
            ->withCount('accounts')
            ->latest()
            ->paginate(15);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.create');
    }

    public function store(StoreProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        if (! empty($validated['features_list'])) {
            $validated['features'] = array_values(array_filter(
                array_map('trim', explode("\n", $validated['features_list']))
            ));
        }
        unset($validated['features_list']);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);

        \App\Models\ActivityLog::log(
            'product_created',
            'Product',
            $product->id,
            ['name' => $product->name]
        );

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        if (! empty($validated['features_list'])) {
            $validated['features'] = array_values(array_filter(
                array_map('trim', explode("\n", $validated['features_list']))
            ));
        }
        unset($validated['features_list']);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        \App\Models\ActivityLog::log(
            'product_updated',
            'Product',
            $product->id,
            ['name' => $product->name]
        );

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $name = $product->name;
        $id = $product->id;

        $product->delete();

        \App\Models\ActivityLog::log(
            'product_deleted',
            'Product',
            $id,
            ['name' => $name]
        );

        return back()->with('success', 'Product deleted.');
    }
}