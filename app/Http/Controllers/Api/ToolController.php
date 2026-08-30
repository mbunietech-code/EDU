<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;

class ToolController extends Controller
{
    public function index(): JsonResponse
    {
        $tools = Tool::query()
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'image', 'version', 'price', 'is_featured']);

        return response()->json([
            'data' => $tools->map(fn (Tool $t) => $this->card($t))->all(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $t = Tool::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json([
            'data' => array_merge($this->card($t), [
                'description' => $t->description,
            ]),
        ]);
    }

    private function card(Tool $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'version' => $t->version,
            'is_featured' => (bool) $t->is_featured,
            'image_url' => $t->image ? asset('storage/'.$t->image) : null,
            'short_description' => \Illuminate\Support\Str::limit(strip_tags((string) $t->description), 120),
            'price_label' => $t->price > 0 ? 'TZS '.number_format((float) $t->price) : 'Free',
        ];
    }
}
