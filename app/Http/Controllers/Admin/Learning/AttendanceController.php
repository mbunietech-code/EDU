<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Live-room attendance across all rooms.
 */
class AttendanceController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        Gate::authorize('rooms.view');

        $filters = $request->validate([
            'room' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($filters['from']) ? Carbon::createFromFormat('Y-m-d', $filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? Carbon::createFromFormat('Y-m-d', $filters['to'])->endOfDay() : null;
        $roomId = isset($filters['room']) ? (int) $filters['room'] : null;

        $base = LearningRoomSession::query()
            ->whereNotNull('started_at')
            ->when($roomId, fn (Builder $q, int $id) => $q->where('learning_room_id', $id))
            ->when($from, fn (Builder $q, Carbon $d) => $q->where('started_at', '>=', $d))
            ->when($to, fn (Builder $q, Carbon $d) => $q->where('started_at', '<=', $d));

        $sessions = (clone $base)
            ->with(['room' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'slug', 'status', 'deleted_at'])])
            ->withCount('attendances')
            ->withAvg('attendances as average_seconds', 'total_seconds')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $sessionCount = (clone $base)->count();
        $attendeeCount = \App\Models\LearningRoomAttendance::query()
            ->whereIn('learning_room_session_id', (clone $base)->select('id'))
            ->count();

        return view('admin.learning.attendance.index', [
            'sessions' => $sessions,
            'filters' => ['room' => $roomId, 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
            'rooms' => LearningRoom::withTrashed()->whereHas('sessions')->orderBy('title')->get(['id', 'title', 'deleted_at']),
            'summary' => [
                'sessions' => $sessionCount,
                'attendees' => $attendeeCount,
                'per_session' => $sessionCount > 0 ? round($attendeeCount / $sessionCount, 1) : 0,
            ],
        ]);
    }
}
