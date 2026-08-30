<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;
use Illuminate\Http\JsonResponse;

class ScholarshipController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Scholarship::query()
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'country', 'deadline', 'is_featured', 'image', 'description']);

        return response()->json([
            'data' => $items->map(fn (Scholarship $s) => $this->card($s))->all(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $s = Scholarship::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json([
            'data' => array_merge($this->card($s), [
                'description' => $s->description,
                'apply_url' => $s->apply_url,
            ]),
        ]);
    }

    private function card(Scholarship $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->title,
            'slug' => $s->slug,
            'country' => $s->country,
            'is_featured' => (bool) $s->is_featured,
            'image_url' => $s->imageUrl(),
            'short_description' => \Illuminate\Support\Str::limit(strip_tags((string) $s->description), 120),
            'deadline' => optional($s->deadline)->toDateString(),
            'deadline_label' => $s->deadline
                ? ($s->isExpired() ? 'Closed' : 'Closes '.$s->deadline->diffForHumans())
                : 'No deadline',
            'is_expired' => $s->isExpired(),
        ];
    }
}
