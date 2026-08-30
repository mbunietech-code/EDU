<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Scholarship;
use App\Models\Tool;
use App\Services\DeletionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    // ---------------------------------------------------------------- Products
    public function products(): JsonResponse
    {
        $products = Product::withCount(['plans', 'orders'])->latest()->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'type' => $p->type,
                'price_label' => 'TZS '.number_format((float) $p->price),
                'status' => $p->status,
                'plans_count' => $p->plans_count,
                'orders_count' => $p->orders_count,
                'is_featured' => (bool) $p->is_featured,
            ])->all(),
        ]);
    }

    public function productShow(Product $product): JsonResponse
    {
        $product->load(['plans' => fn ($q) => $q->orderBy('sort_order')]);

        return response()->json(['data' => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'features' => $product->features ?? [],
            'price' => (float) $product->price,
            'type' => $product->type,
            'status' => $product->status,
            'is_featured' => (bool) $product->is_featured,
            'software_version' => $product->software_version,
            'software_key' => $product->software_key,
            'image_url' => $product->imageUrl(),
            'plans' => $product->plans->map(fn (Plan $pl) => $this->planRow($pl))->all(),
        ]]);
    }

    public function productImage(Request $request, Product $product): JsonResponse
    {
        return $this->storeImage($request, $product, 'products');
    }

    public function productStore(Request $request): JsonResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = $this->uniqueSlug(Product::class, $data['slug'] ?? $data['name']);

        $product = Product::create($this->cleanProduct($data));
        ActivityLog::log('product_created', 'Product', $product->id, ['name' => $product->name]);

        return response()->json(['data' => ['id' => $product->id], 'message' => 'Product created.'], 201);
    }

    public function productUpdate(Request $request, Product $product): JsonResponse
    {
        $data = $this->validateProduct($request, $product->id);
        if (! empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug(Product::class, $data['slug'], $product->id);
        }

        $product->update($this->cleanProduct($data));
        ActivityLog::log('product_updated', 'Product', $product->id, ['name' => $product->name]);

        return response()->json(['message' => 'Product updated.']);
    }

    public function productDestroy(Request $request, Product $product, DeletionService $deletions): JsonResponse
    {
        $reason = $this->reason($request);
        $deletions->delete($product, $reason);

        return response()->json(['message' => 'Product deleted.']);
    }

    // ------------------------------------------------------------------- Plans
    public function planStore(Request $request, Product $product): JsonResponse
    {
        $data = $this->validatePlan($request);
        $data['product_id'] = $product->id;
        if (($data['duration_type'] ?? 'days') === 'lifetime') {
            $data['duration_days'] = null;
        }

        $plan = Plan::create($data);
        ActivityLog::log('plan_created', 'Plan', $plan->id, ['name' => $plan->name, 'product_id' => $product->id]);

        return response()->json(['data' => $this->planRow($plan), 'message' => 'Plan created.'], 201);
    }

    public function planUpdate(Request $request, Plan $plan): JsonResponse
    {
        $data = $this->validatePlan($request);
        if (($data['duration_type'] ?? 'days') === 'lifetime') {
            $data['duration_days'] = null;
        }

        $plan->update($data);
        ActivityLog::log('plan_updated', 'Plan', $plan->id, ['name' => $plan->name]);

        return response()->json(['data' => $this->planRow($plan->fresh()), 'message' => 'Plan updated.']);
    }

    public function planDestroy(Request $request, Plan $plan, DeletionService $deletions): JsonResponse
    {
        if ($plan->subscriptions()->whereIn('status', ['active', 'expiring_soon', 'pending'])->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan in use by active subscriptions. Deactivate it instead.',
            ], 422);
        }

        $deletions->delete($plan, $this->reason($request));

        return response()->json(['message' => 'Plan deleted.']);
    }

    // ------------------------------------------------------------------- Tools
    public function tools(): JsonResponse
    {
        $tools = Tool::withCount('orders')->latest()->get();

        return response()->json([
            'data' => $tools->map(fn (Tool $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'price_label' => (float) $t->price > 0 ? 'TZS '.number_format((float) $t->price) : 'Free',
                'status' => $t->status,
                'orders_count' => $t->orders_count,
                'version' => $t->version,
                'is_featured' => (bool) $t->is_featured,
            ])->all(),
        ]);
    }

    public function toolShow(Tool $tool): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $tool->id,
            'name' => $tool->name,
            'slug' => $tool->slug,
            'description' => $tool->description,
            'version' => $tool->version,
            'license_key' => $tool->license_key,
            'price' => (float) $tool->price,
            'status' => $tool->status,
            'is_featured' => (bool) $tool->is_featured,
            'sort_order' => $tool->sort_order,
            'image_url' => $tool->imageUrl(),
        ]]);
    }

    public function toolImage(Request $request, Tool $tool): JsonResponse
    {
        return $this->storeImage($request, $tool, 'tools');
    }

    public function toolStore(Request $request): JsonResponse
    {
        $data = $this->validateTool($request);
        $data['slug'] = $this->uniqueSlug(Tool::class, $data['slug'] ?? $data['name']);

        $tool = Tool::create($data);
        ActivityLog::log('tool_created', 'Tool', $tool->id, ['name' => $tool->name]);

        return response()->json(['data' => ['id' => $tool->id], 'message' => 'Tool created.'], 201);
    }

    public function toolUpdate(Request $request, Tool $tool): JsonResponse
    {
        $data = $this->validateTool($request, $tool->id);
        if (! empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug(Tool::class, $data['slug'], $tool->id);
        }

        $tool->update($data);
        ActivityLog::log('tool_updated', 'Tool', $tool->id, ['name' => $tool->name]);

        return response()->json(['message' => 'Tool updated.']);
    }

    public function toolDestroy(Request $request, Tool $tool, DeletionService $deletions): JsonResponse
    {
        $deletions->delete($tool, $this->reason($request));

        return response()->json(['message' => 'Tool deleted.']);
    }

    // ------------------------------------------------------------ Scholarships
    public function scholarships(): JsonResponse
    {
        $items = Scholarship::orderBy('sort_order')->latest()->get();

        return response()->json([
            'data' => $items->map(fn (Scholarship $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'country' => $s->country,
                'status' => $s->status,
                'deadline' => optional($s->deadline)->toDateString(),
                'is_expired' => $s->deadline !== null && $s->deadline->isPast(),
                'is_featured' => (bool) $s->is_featured,
            ])->all(),
        ]);
    }

    public function scholarshipShow(Scholarship $scholarship): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $scholarship->id,
            'title' => $scholarship->title,
            'slug' => $scholarship->slug,
            'country' => $scholarship->country,
            'description' => $scholarship->description,
            'deadline' => optional($scholarship->deadline)->toDateString(),
            'apply_url' => $scholarship->apply_url,
            'status' => $scholarship->status,
            'is_featured' => (bool) $scholarship->is_featured,
            'sort_order' => $scholarship->sort_order,
            'image_url' => $scholarship->imageUrl(),
        ]]);
    }

    public function scholarshipImage(Request $request, Scholarship $scholarship): JsonResponse
    {
        return $this->storeImage($request, $scholarship, 'scholarships');
    }

    public function scholarshipStore(Request $request): JsonResponse
    {
        $data = $this->validateScholarship($request);
        $data['slug'] = $this->uniqueSlug(Scholarship::class, $data['slug'] ?? $data['title']);

        $item = Scholarship::create($data);
        ActivityLog::log('scholarship_created', 'Scholarship', $item->id, ['title' => $item->title]);

        return response()->json(['data' => ['id' => $item->id], 'message' => 'Scholarship created.'], 201);
    }

    public function scholarshipUpdate(Request $request, Scholarship $scholarship): JsonResponse
    {
        $data = $this->validateScholarship($request, $scholarship->id);
        if (! empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug(Scholarship::class, $data['slug'], $scholarship->id);
        }

        $scholarship->update($data);
        ActivityLog::log('scholarship_updated', 'Scholarship', $scholarship->id, ['title' => $scholarship->title]);

        return response()->json(['message' => 'Scholarship updated.']);
    }

    public function scholarshipDestroy(Request $request, Scholarship $scholarship, DeletionService $deletions): JsonResponse
    {
        $deletions->delete($scholarship, $this->reason($request));

        return response()->json(['message' => 'Scholarship deleted.']);
    }

    // ----------------------------------------------------------------- Helpers
    private function storeImage(Request $request, Model $model, string $folder): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        if ($model->image && Storage::disk('public')->exists($model->image)) {
            Storage::disk('public')->delete($model->image);
        }

        $model->image = $request->file('image')->store($folder, 'public');
        $model->save();

        ActivityLog::log(class_basename($model).'_image_updated', class_basename($model), $model->getKey());

        return response()->json([
            'data' => ['image_url' => asset('storage/'.$model->image)],
            'message' => 'Image updated.',
        ]);
    }

    private function planRow(Plan $pl): array
    {
        return [
            'id' => $pl->id,
            'name' => $pl->name,
            'description' => $pl->description,
            'duration_type' => $pl->duration_type,
            'duration_days' => $pl->duration_days,
            'duration_label' => $pl->durationLabel(),
            'price' => (float) $pl->price,
            'price_label' => 'TZS '.number_format((float) $pl->price),
            'status' => $pl->status,
            'sort_order' => $pl->sort_order,
        ];
    }

    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'type' => ['required', Rule::in(['subscription', 'software'])],
            'software_version' => ['nullable', 'string', 'max:255'],
            'software_key' => ['nullable', 'string', 'max:1000', 'required_if:type,software'],
            'is_featured' => ['boolean'],
        ]);
    }

    private function cleanProduct(array $data): array
    {
        if (($data['type'] ?? 'subscription') !== 'software') {
            $data['software_version'] = null;
            $data['software_key'] = null;
        }

        return $data;
    }

    private function validatePlan(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_type' => ['required', Rule::in(['days', 'lifetime'])],
            'duration_days' => ['nullable', 'integer', 'min:1', 'required_if:duration_type,days'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function validateTool(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'version' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }

    private function validateScholarship(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }

    private function reason(Request $request): string
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ])['reason'];
    }

    private function uniqueSlug(string $model, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        while ($model::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
