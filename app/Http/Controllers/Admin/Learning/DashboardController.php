<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LiveProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Learning overview: headline numbers, live rooms, recent activity.
 */
class DashboardController extends Controller
{
    public function index(Request $request, LearningAnalytics $analytics, LiveProvider $provider): View
    {
        Gate::authorize('learning.view');

        $stats = $analytics->overview($request->boolean('refresh'));

        $liveRooms = LearningRoom::query()
            ->live()
            ->with('host:id,name')
            ->withCount(['attendances as present_count' => fn ($q) => $q->present()])
            ->orderByDesc('started_at')
            ->limit(5)
            ->get();

        return view('admin.learning.dashboard', [
            'stats' => $stats,
            'liveRooms' => $liveRooms,
            'provider' => $provider->status(),
        ]);
    }
}
