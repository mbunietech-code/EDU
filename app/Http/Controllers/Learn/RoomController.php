<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Live room listing (?tab=live|upcoming|completed|all), details and calendar export.
 */
class RoomController extends Controller
{
    public const TABS = ['live' => 'Live now', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'all' => 'All'];

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = [
            'q' => Str::limit(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 100, ''),
            'category' => is_string($request->query('category')) && $request->query('category') !== '' ? $request->query('category') : null,
            'course' => is_string($request->query('course')) && $request->query('course') !== '' ? $request->query('course') : null,
        ];

        // Rooms the viewer can see, never drafts (a host previews drafts from the studio).
        $visible = fn () => LearningRoom::query()->visibleTo($user)->where('learning_rooms.status', '!=', 'draft');

        $categories = LearningCategory::query()
            ->whereIn('id', $visible()->whereNotNull('learning_category_id')->select('learning_category_id'))
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $activeCategory = $filters['category'] ? $categories->firstWhere('slug', $filters['category']) : null;

        $courses = LearningCourse::query()
            ->whereIn('id', $visible()->whereNotNull('learning_course_id')->select('learning_course_id'))
            ->when($activeCategory, fn (Builder $q, $c) => $q->where('learning_category_id', $c->id))
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'learning_category_id']);

        $activeCourse = $filters['course'] ? $courses->firstWhere('slug', $filters['course']) : null;

        $filtered = function () use ($visible, $filters, $activeCategory, $activeCourse): Builder {
            return $visible()
                ->when($filters['category'], fn (Builder $q) => $q->where('learning_category_id', $activeCategory?->id ?? 0))
                ->when($filters['course'], fn (Builder $q) => $q->where('learning_course_id', $activeCourse?->id ?? 0))
                ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                    $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                    $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
                });
        };

        $counts = [
            'live' => $filtered()->where('status', 'live')->count(),
            'upcoming' => $filtered()->where('status', 'scheduled')->count(),
            'completed' => $filtered()->where('status', 'completed')->count(),
            'all' => $filtered()->count(),
        ];

        $tab = (string) $request->query('tab', '');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = $counts['live'] > 0 ? 'live' : 'upcoming';
        }

        $rooms = $filtered()
            ->with(['host:id,name', 'category:id,name,slug'])
            ->when($tab === 'live', fn (Builder $q) => $q->where('status', 'live')->orderByDesc('started_at'))
            ->when($tab === 'upcoming', fn (Builder $q) => $q->where('status', 'scheduled')->orderBy('scheduled_at'))
            ->when($tab === 'completed', fn (Builder $q) => $q->where('status', 'completed')->orderByDesc('ended_at')->orderByDesc('scheduled_at'))
            ->when($tab === 'all', fn (Builder $q) => $q
                // Live first, then upcoming, then the rest — newest first.
                ->orderByRaw("case when status = 'live' then 0 when status = 'scheduled' then 1 else 2 end")
                ->orderByDesc('scheduled_at'))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('learn.rooms.index', [
            'rooms' => $rooms,
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'categories' => $categories,
            'courses' => $courses,
            'activeCategory' => $activeCategory,
            'activeCourse' => $activeCourse,
            'filters' => $filters,
            'isFiltered' => $filters['q'] !== '' || $filters['category'] || $filters['course'],
        ]);
    }

    public function show(Request $request, LearningRoom $room, RoomService $rooms)
    {
        $this->authorize('view', $room);

        $user = $request->user();
        $isManager = $room->isManageableBy($user);

        $room->load([
            'host:id,name,can_teach',
            'category:id,name,slug',
            'course' => fn ($q) => $q->withTrashed()->select('id', 'title', 'slug', 'status', 'deleted_at', 'instructor_id', 'access'),
            'topic:id,learning_course_id,title',
        ]);

        $recordings = $room->recordings()
            ->where('status', 'ready')
            ->whereNotNull('path')
            ->when(! $isManager, fn (Builder $q) => $q->where('is_shared', true))
            ->with('session:id,started_at,ended_at')
            ->limit(20)
            ->get();

        $pastSessions = $room->sessions()
            ->whereNotNull('ended_at')
            ->withCount('attendances')
            ->limit(10)
            ->get();

        $liveCount = $room->isLive() ? $rooms->presentParticipants($room)->count() : 0;

        // Course link only when the viewer may open that course.
        $courseVisible = $room->course && ! $room->course->trashed() && $room->course->isVisibleTo($user);

        return view('learn.rooms.show', [
            'room' => $room,
            'isManager' => $isManager,
            'canStart' => $isManager && in_array($room->status, ['draft', 'scheduled', 'completed'], true),
            'recordings' => $recordings,
            'pastSessions' => $pastSessions,
            'liveCount' => $liveCount,
            'courseVisible' => $courseVisible,
            'descriptionHtml' => $room->description
                ? Str::markdown($room->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
                : null,
        ]);
    }

    /** RFC 5545 single-event calendar file. */
    public function ics(Request $request, LearningRoom $room)
    {
        $this->authorize('view', $room);

        abort_unless($room->scheduled_at !== null, 404);

        $start = $room->scheduled_at->copy()->utc();
        $end = ($room->endsAt() ?? $room->scheduled_at->copy()->addHour())->utc();
        $url = route('learn.rooms.show', $room);
        $host = parse_url(config('app.url') ?: $url, PHP_URL_HOST) ?: 'localhost';

        $description = trim(Str::limit(strip_tags((string) $room->description), 1500));
        $description = trim(($description !== '' ? $description."\n\n" : '').'Join: '.$url);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.self::escapeText(config('app.name', 'MbunieEduHub')).'//Live rooms//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:learning-room-'.$room->id.'@'.$host,
            'DTSTAMP:'.self::utc(now()),
            'DTSTART:'.self::utc($start),
            'DTEND:'.self::utc($end),
            'SUMMARY:'.self::escapeText($room->title),
            'DESCRIPTION:'.self::escapeText($description),
            'URL:'.$url,
            'STATUS:'.($room->isCancelled() ? 'CANCELLED' : 'CONFIRMED'),
        ];

        if ($room->host) {
            $lines[] = 'ORGANIZER;CN='.self::escapeParam($room->host->name).':noreply@'.$host;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $body = implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";

        $filename = Str::slug($room->title) ?: 'live-class';

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function utc(Carbon $time): string
    {
        return $time->copy()->utc()->format('Ymd\THis\Z');
    }

    /** TEXT value escaping (RFC 5545 §3.3.11). */
    public static function escapeText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
    }

    /** Parameter values may not contain quotes; quote when they hold : ; or ,. */
    private static function escapeParam(string $value): string
    {
        $value = str_replace(['"', "\r", "\n"], ['', ' ', ' '], $value);

        return preg_match('/[:;,]/', $value) ? '"'.$value.'"' : $value;
    }

    /** Fold a content line to at most 75 octets without splitting a UTF-8 character. */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = [];
        $current = '';
        $limit = 75;

        foreach (mb_str_split($line, 1, 'UTF-8') as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $out[] = $current;
                $current = '';
                $limit = 74; // continuation lines start with one space
            }
            $current .= $char;
        }

        $out[] = $current;

        return implode("\r\n ", $out);
    }
}
