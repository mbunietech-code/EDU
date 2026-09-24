<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Month calendar of live rooms (?month=YYYY-MM). Monday-first grid on md+,
 * an agenda list on small screens. Only rooms the viewer may see, never drafts.
 */
class CalendarController extends Controller
{
    /** Hard cap so a busy month can never turn into a huge page. */
    private const MAX_EVENTS = 500;

    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'month' => ['nullable', 'string', 'regex:/^(20[0-9]{2}|2100)-(0[1-9]|1[0-2])$/'],
        ], [
            'month.regex' => 'The month must look like 2026-09.',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                throw new ValidationException($validator);
            }

            // Never bounce "back" (could be the same bad URL): land on the current month.
            return redirect()->route('learn.calendar')->with('error', 'That month is not valid — showing the current month instead.');
        }

        $user = $request->user();
        $monthParam = $validator->validated()['month'] ?? null;

        $start = $monthParam
            ? Carbon::createFromFormat('!Y-m', $monthParam)->startOfMonth()
            : now()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $rooms = LearningRoom::query()
            ->visibleTo($user)
            ->where('status', '!=', 'draft')
            ->where(fn (Builder $q) => $q
                ->whereBetween('scheduled_at', [$start, $end])
                ->orWhere(fn (Builder $w) => $w->whereNull('scheduled_at')->whereBetween('started_at', [$start, $end])))
            ->with(['host:id,name', 'category:id,name'])
            ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
            ->orderBy('scheduled_at')
            ->orderBy('started_at')
            ->limit(self::MAX_EVENTS)
            ->get();

        $byDay = $rooms
            ->groupBy(fn (LearningRoom $r) => ($r->scheduled_at ?? $r->started_at)->format('Y-m-d'))
            ->map(fn ($day) => $day->sortBy(fn (LearningRoom $r) => ($r->scheduled_at ?? $r->started_at)->getTimestamp())->values());

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $weeks[intdiv($gridStart->diffInDays($day), 7)][] = $day->copy();
        }

        return view('learn.calendar', [
            'month' => $start,
            'weeks' => $weeks,
            'byDay' => $byDay,
            'rooms' => $rooms,
            'today' => now()->format('Y-m-d'),
            'prevMonth' => $start->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $start->copy()->addMonthNoOverflow()->format('Y-m'),
            'isCurrentMonth' => $start->format('Y-m') === now()->format('Y-m'),
            'truncated' => $rooms->count() >= self::MAX_EVENTS,
            'statusStyles' => self::STATUS_STYLES,
        ]);
    }

    /** Event chip colours per room status (no gradients). */
    public const STATUS_STYLES = [
        'live' => 'bg-red-50 text-red-700 ring-red-600/20',
        'scheduled' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'cancelled' => 'bg-gray-50 text-gray-500 line-through ring-gray-300',
    ];
}
