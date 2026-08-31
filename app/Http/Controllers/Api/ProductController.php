<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ConvertsCurrency;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ConvertsCurrency;

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('status', 'published')
            ->withCount(['plans as active_plans_count' => fn ($q) => $q->where('status', 'active')])
            ->with(['plans' => fn ($q) => $q->where('status', 'active')->orderBy('price')])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => $this->card($p))->all(),
            'meta' => ['rates' => $this->currencyRates()],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['plans' => fn ($q) => $q->where('status', 'active')->orderBy('sort_order')->orderBy('price')])
            ->firstOrFail();

        return response()->json([
            'meta' => ['rates' => $this->currencyRates()],
            'data' => array_merge($this->card($product), [
                'description' => $product->description,
                'features' => $product->features ?? [],
                'plans' => $product->plans->map(fn ($plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'duration' => $plan->isLifetime() ? 'Lifetime' : $plan->duration_days.' days',
                    'price' => (float) $plan->price,
                    'price_label' => $this->money($plan->price),
                ])->all(),
            ]),
        ]);
    }

    private function card(Product $p): array
    {
        $from = $p->relationLoaded('plans') ? $p->plans->min('price') : null;
        $from ??= $p->price;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'type' => $p->type,
            'image_url' => $p->imageUrl(),
            'is_featured' => (bool) $p->is_featured,
            'short_description' => \Illuminate\Support\Str::limit(strip_tags((string) $p->description), 120),
            'plans_count' => $p->active_plans_count ?? $p->plans()->where('status', 'active')->count(),
            'from_price' => $from !== null ? (float) $from : null,
            'from_price_label' => $from !== null ? 'From '.$this->money($from) : null,
        ];
    }

    private function money($amount): string
    {
        return 'TZS '.number_format((float) $amount);
    }
}
