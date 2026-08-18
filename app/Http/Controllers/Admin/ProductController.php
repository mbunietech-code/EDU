<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        if ($request->hasFile('software_file')) {
            $validated['software_filename'] = $request->file('software_file')->getClientOriginalName();
            $validated['software_file'] = $this->storeSoftwareFile($request->file('software_file'));
        }

        $this->normalizeType($validated);

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

        if ($request->hasFile('software_file')) {
            $this->deleteSoftwareFile($product);
            $validated['software_filename'] = $request->file('software_file')->getClientOriginalName();
            $validated['software_file'] = $this->storeSoftwareFile($request->file('software_file'));
        }

        $this->normalizeType($validated, $product);

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

        $this->deleteSoftwareFile($product);
        $product->delete();

        \App\Models\ActivityLog::log(
            'product_deleted',
            'Product',
            $id,
            ['name' => $name]
        );

        return back()->with('success', 'Product deleted.');
    }

    public function keys(Product $product)
    {
        $this->authorize('view', $product);

        $product->loadCount([
            'productKeys as keys_available_count' => fn ($query) => $query->whereNull('order_id'),
            'productKeys as keys_sold_count' => fn ($query) => $query->whereNotNull('order_id'),
        ]);

        $keys = $product->productKeys()
            ->with('order')
            ->latest('id')
            ->paginate(20);

        return view('admin.products.keys', compact('product', 'keys'));
    }

    public function storeKeys(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'keys_list' => ['required', 'string'],
        ]);

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $validated['keys_list']))
        ));

        if ($lines === []) {
            return back()->with('error', 'Enter at least one product key.');
        }

        $now = now();
        $inserted = 0;

        foreach ($lines as $keyValue) {
            $existing = ProductKey::where('product_id', $product->id)
                ->where('key_value', $keyValue)
                ->exists();

            if ($existing) {
                continue;
            }

            ProductKey::create([
                'product_id' => $product->id,
                'key_value' => $keyValue,
                'status' => 'available',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $inserted++;
        }

        \App\Models\ActivityLog::log(
            'product_keys_added',
            'Product',
            $product->id,
            ['product' => $product->name, 'added' => $inserted]
        );

        return back()->with('success', "{$inserted} product key(s) added.");
    }

    public function destroyKey(ProductKey $productKey)
    {
        $this->authorize('delete', $productKey->product);

        if (! $productKey->isAvailable()) {
            return back()->with('error', 'Only unsold product keys can be deleted.');
        }

        $productKey->delete();

        \App\Models\ActivityLog::log(
            'product_key_deleted',
            'ProductKey',
            $productKey->id,
            ['product' => $productKey->product->name]
        );

        return back()->with('success', 'Product key deleted.');
    }

    protected function normalizeType(array &$validated, ?Product $product = null): void
    {
        $type = $validated['type'] ?? $product?->type ?? 'subscription';

        if ($type !== 'software') {
            $validated['software_file'] = null;
            $validated['software_filename'] = null;
            $validated['software_version'] = null;
        }
    }

    protected function storeSoftwareFile($file): string
    {
        return $file->store('software', config('software.download_disk', 'private'));
    }

    protected function deleteSoftwareFile(Product $product): void
    {
        if (! $product->software_file) {
            return;
        }

        Storage::disk(config('software.download_disk', 'private'))->delete($product->software_file);
    }
}