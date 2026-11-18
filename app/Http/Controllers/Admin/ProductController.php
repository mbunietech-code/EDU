<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Services\DeletionService;
use App\Services\ProductPlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function store(StoreProductRequest $request, ProductPlanService $plans)
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
        } elseif ($request->filled('software_file_path')) {
            $validated['software_file'] = $request->input('software_file_path');
            $validated['software_filename'] = $request->input('software_filename');
        }

        $this->normalizeType($validated);
        $planRows = $validated['plans'] ?? [];
        unset($validated['plans']);

        $product = DB::transaction(function () use ($validated, $planRows, $plans) {
            $product = Product::create($validated);
            $plans->sync($product, $planRows);

            return $product;
        });

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

    public function update(UpdateProductRequest $request, Product $product, ProductPlanService $plans)
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
        } elseif ($request->filled('software_file_path') && $request->input('software_file_path') !== $product->software_file) {
            $this->deleteSoftwareFile($product);
            $validated['software_file'] = $request->input('software_file_path');
            $validated['software_filename'] = $request->input('software_filename');
        }

        if (($validated['type'] ?? $product->type) !== 'software' && $product->software_file) {
            $this->deleteSoftwareFile($product);
        }

        $this->normalizeType($validated, $product);
        $planRows = $validated['plans'] ?? [];
        unset($validated['plans']);

        $summary = DB::transaction(function () use ($product, $validated, $planRows, $plans) {
            $product->update($validated);

            return $plans->sync($product, $planRows);
        });

        \App\Models\ActivityLog::log(
            'product_updated',
            'Product',
            $product->id,
            ['name' => $product->name]
        );

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated.'.($summary['deactivated'] ? ' Plans that customers already bought were switched off instead of removed.' : ''));
    }

    public function destroy(Request $request, Product $product, DeletionService $deletionService)
    {
        $this->authorize('delete', $product);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->deleteSoftwareFile($product);
        $deletionService->delete($product, $validated['reason']);

        return back()->with('success', 'Product deleted.');
    }

    public function uploadSoftwareFile(Request $request)
    {
        $validated = $request->validate([
            'software_file' => ['required', 'file', 'extensions:exe,zip,msi,rar,apk,dmg', 'max:153600'],
        ]);

        $path = $this->storeSoftwareFile($request->file('software_file'));

        return response()->json([
            'path' => $path,
            'filename' => $request->file('software_file')->getClientOriginalName(),
        ]);
    }

    protected function normalizeType(array &$validated, ?Product $product = null): void
    {
        $type = $validated['type'] ?? $product?->type ?? 'subscription';

        if ($type !== 'software') {
            $validated['software_file'] = null;
            $validated['software_filename'] = null;
            $validated['software_version'] = null;
            $validated['software_key'] = null;
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