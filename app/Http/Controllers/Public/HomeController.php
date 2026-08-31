<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Research;
use App\Models\Scholarship;
use App\Services\HomeTickerService;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index(HomeTickerService $ticker)
    {
        $scholarships = Scholarship::published()
            ->open()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        $tickerItems = $ticker->cachedItems();
        $researchMarquee = $this->researchMarquee();

        return view('public.home', compact('scholarships', 'tickerItems', 'researchMarquee'));
    }

    /** Published research for the home-page marquee (empty before the table exists). */
    private function researchMarquee(): array
    {
        try {
            if (! Schema::hasTable('researches')) {
                return [];
            }

            return Research::query()
                ->where('status', 'published')
                ->with(['category:id,name', 'author:id,name'])
                ->withCount('chapters')
                ->latest('published_at')
                ->take(12)
                ->get()
                ->map(fn (Research $r) => [
                    'title' => $r->title,
                    'category' => $r->category->name ?? null,
                    'author' => $r->author->name ?? null,
                    'chapters' => $r->chapters_count,
                    'url' => route('research.show', $r),
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
