<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\CurrencyRateService;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(protected CurrencyRateService $currencyRates)
    {
    }

    public function index()
    {
        $user = auth()->user();

        $subscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->latest()
            ->take(5)
            ->get();

        $activeSubscriptions = $user->subscriptions()
            ->with(['product', 'plan'])
            ->where('status', 'active')
            ->get();

        $pendingOrders = $user->orders()
            ->with(['product', 'plan', 'tool'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $featuredProducts = Product::published()
            ->featured()
            ->withCount('plans')
            ->get();

        $rates = $this->currencyRates->rates();

        $learning = $this->learningSummary($user);

        return view('user.dashboard', compact('subscriptions', 'activeSubscriptions', 'pendingOrders', 'featuredProducts', 'rates', 'learning'));
    }

    /**
     * Compact "My Learning" block: live now, continue learning (3) and
     * upcoming sessions (3). Returns null — and the section is hidden — when
     * the learning tables are missing or anything goes wrong, so /dashboard
     * never breaks because of the learning feature.
     *
     * @return array{live:\Illuminate\Support\Collection,continue:\Illuminate\Support\Collection,upcoming:\Illuminate\Support\Collection}|null
     */
    protected function learningSummary(User $user): ?array
    {
        try {
            if (! Schema::hasTable('learning_rooms') || ! Schema::hasTable('learning_video_progress')) {
                return null;
            }

            $rooms = fn () => LearningRoom::query()->visibleTo($user)->with(['host:id,name']);

            return [
                'live' => $rooms()->live()->orderByDesc('started_at')->limit(3)->get(),
                'continue' => app(ProgressService::class)->continueWatching($user, 3),
                'upcoming' => $rooms()->upcoming()->limit(3)->get(),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}