<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
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
        $liveClasses = $this->liveClasses();

        return view('public.home', compact('scholarships', 'tickerItems', 'researchMarquee', 'liveClasses'));
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

    /**
     * Public live classes for the home-page promo: how many are live now and
     * the next few sessions. Only rooms open to everyone (access = public) are
     * listed, so guests never see private, course or draft rooms. Empty
     * before the learning tables exist.
     *
     * @return array{live:int,sessions:list<array{title:string,host:?string,is_live:bool,when:?string,url:string}>}
     */
    private function liveClasses(): array
    {
        $empty = ['live' => 0, 'sessions' => []];

        try {
            if (! Schema::hasTable('learning_rooms')) {
                return $empty;
            }

            $public = fn () => LearningRoom::query()->where('access', 'public')->with('host:id,name');

            $live = $public()->live()->orderByDesc('started_at')->take(3)->get();
            $upcoming = $public()->upcoming()->where('scheduled_at', '>=', now())->take(3 - min(3, $live->count()))->get();

            return [
                'live' => $public()->live()->count(),
                'sessions' => $live->concat($upcoming)->map(fn (LearningRoom $room) => [
                    'title' => $room->title,
                    'host' => $room->host->name ?? null,
                    'is_live' => $room->isLive(),
                    'when' => $room->isLive() ? null : $room->scheduled_at?->format('D, d M · H:i'),
                    'url' => route('learn.rooms.show', $room),
                ])->values()->all(),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }
}
