<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\LiveServerClient;
use App\Services\Learning\ProgressService;
use App\Services\Learning\RoomService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Learning admin pages the MHub app did not have yet: live sessions,
 * attendance, learner progress, instructors and topics. Same rules and
 * permissions as admin/learning on the web.
 */
class LearningExtrasController extends Controller
{
    private static function iso($date): ?string
    {
        return $date ? $date->toIso8601String() : null;
    }

    private static function like(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }

    // ================================================================ live

    public function live(Request $request, LiveProvider $provider, LiveServerClient $server): JsonResponse
    {
        $live = LearningRoom::query()
            ->live()
            ->with(['host:id,name', 'category:id,name'])
            ->orderByDesc('started_at')
            ->get();

        $sessions = LearningRoomSession::query()
            ->whereIn('learning_room_id', $live->pluck('id'))
            ->whereNull('ended_at')
            ->withCount(['attendances as present_count' => fn ($q) => $q->present()])
            ->get()
            ->keyBy('learning_room_id');

        $recent = LearningRoomSession::query()
            ->whereNotNull('ended_at')
            ->with(['room' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'host_id', 'deleted_at'])->with('host:id,name')])
            ->withCount('attendances')
            ->orderByDesc('ended_at')
            ->limit(10)
            ->get();

        // The video server is only contacted when an admin asks (it may be slow or down).
        $check = null;
        if ($request->boolean('check')) {
            $reachable = $server->ping();
            $check = ['reachable' => $reachable, 'error' => $reachable ? null : $server->lastError()];
        }

        return response()->json([
            'provider' => $provider->status(),
            'check' => $check,
            'can_manage' => Gate::allows('rooms.manage'),
            'live' => $live->map(fn (LearningRoom $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'host_name' => $r->host?->name,
                'category' => $r->category?->name,
                'started_at' => self::iso($r->started_at),
                'present' => (int) ($sessions->get($r->id)?->present_count ?? 0),
                'peak' => (int) ($sessions->get($r->id)?->peak_participants ?? 0),
            ])->values(),
            'recent' => $recent->map(fn (LearningRoomSession $s) => [
                'id' => $s->id,
                'room_id' => $s->learning_room_id,
                'room_title' => $s->room?->title ?? 'Deleted room',
                'room_deleted' => (bool) $s->room?->trashed(),
                'host_name' => $s->room?->host?->name,
                'started_at' => self::iso($s->started_at),
                'ended_at' => self::iso($s->ended_at),
                'attendees' => (int) $s->attendances_count,
                'peak' => (int) $s->peak_participants,
            ])->values(),
        ]);
    }

    public function liveRoom(Request $request, LearningRoom $room, RoomService $rooms): JsonResponse
    {
        $room->load(['host:id,name,email', 'category:id,name']);
        $session = $room->sessions()->whereNull('ended_at')->first() ?? $room->sessions()->first();

        $attendances = $session
            ? LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->with(['user:id,name,email,status', 'remover:id,name'])
                ->orderByDesc('last_seen_at')
                ->get()
            : collect();

        $messages = $session
            ? LearningRoomMessage::query()
                ->where('learning_room_session_id', $session->id)
                ->with('user:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();

        $presentIds = $session
            ? LearningRoomAttendance::query()->where('learning_room_session_id', $session->id)->present()->pluck('id')->all()
            : [];

        return response()->json([
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'status' => $room->status,
                'host_name' => $room->host?->name,
                'host_email' => $room->host?->email,
                'category' => $room->category?->name,
            ],
            'session' => $session ? [
                'id' => $session->id,
                'started_at' => self::iso($session->started_at),
                'ended_at' => self::iso($session->ended_at),
                'peak' => (int) $session->peak_participants,
            ] : null,
            'can_moderate' => $room->isManageableBy($request->user()),
            'attendances' => $attendances->map(fn (LearningRoomAttendance $a) => [
                'id' => $a->id,
                'name' => $a->user?->name ?? 'Guest',
                'email' => $a->user?->email,
                'role' => $a->role,
                'present' => in_array($a->id, $presentIds, true),
                'first_joined_at' => self::iso($a->first_joined_at),
                'last_seen_at' => self::iso($a->last_seen_at),
                'total_seconds' => (int) $a->total_seconds,
                'join_count' => (int) $a->join_count,
                'removed' => $a->removed_at !== null,
                'removed_by' => $a->remover?->name,
                'hand_raised' => $a->hand_raised_at !== null,
            ])->values(),
            'messages' => $messages->map(fn (LearningRoomMessage $m) => [
                'id' => $m->id,
                'user_name' => $m->user?->name ?? 'Guest',
                'type' => $m->type,
                'body' => $m->is_deleted ? null : $m->body,
                'deleted' => (bool) $m->is_deleted,
                'answered' => (bool) $m->is_answered,
                'created_at' => self::iso($m->created_at),
            ])->values(),
        ]);
    }

    /** Chat moderation: hide a message for everyone (kept for the audit trail). */
    public function destroyLiveMessage(Request $request, LearningRoom $room, LearningRoomMessage $message, RoomService $rooms): JsonResponse
    {
        $this->authorize('moderate', $room);
        abort_unless((int) $message->learning_room_id === (int) $room->id, 404);

        $message->setRelation('room', $room);
        $rooms->deleteMessage($message, $request->user());

        return response()->json(['message' => 'The message was removed from the class chat.']);
    }

    // ========================================================== attendance

    public function attendance(Request $request): JsonResponse
    {
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
            ->paginate(20);

        $sessionCount = (clone $base)->count();
        $attendeeCount = LearningRoomAttendance::query()
            ->whereIn('learning_room_session_id', (clone $base)->select('id'))
            ->count();

        return response()->json([
            'data' => collect($sessions->items())->map(fn (LearningRoomSession $s) => [
                'id' => $s->id,
                'room_id' => $s->learning_room_id,
                'room_title' => $s->room?->title ?? 'Deleted room',
                'room_deleted' => (bool) $s->room?->trashed(),
                'started_at' => self::iso($s->started_at),
                'ended_at' => self::iso($s->ended_at),
                'attendees' => (int) $s->attendances_count,
                'average_seconds' => (int) round((float) $s->average_seconds),
                'peak' => (int) $s->peak_participants,
            ])->values(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
                'summary' => [
                    'sessions' => $sessionCount,
                    'attendees' => $attendeeCount,
                    'per_session' => $sessionCount > 0 ? round($attendeeCount / $sessionCount, 1) : 0,
                ],
                'rooms' => LearningRoom::withTrashed()->whereHas('sessions')->orderBy('title')->get(['id', 'title', 'deleted_at'])
                    ->map(fn (LearningRoom $r) => ['id' => $r->id, 'title' => $r->title.($r->trashed() ? ' (deleted)' : '')])->values(),
            ],
        ]);
    }

    // ============================================================ progress

    public function progress(Request $request, LearningAnalytics $analytics): JsonResponse
    {
        $tab = $request->query('tab') === 'courses' ? 'courses' : 'learners';
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $like = self::like($search);

        if ($tab === 'courses') {
            $query = LearningCourse::query()
                ->with('category:id,name')
                ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', $like))
                ->orderBy('title');
            $page = $analytics->withCourseStats($query)->paginate(20);

            $rows = collect($page->items())->map(fn (LearningCourse $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'category' => $c->category?->name,
                'lessons' => (int) $c->published_videos_count,
                'enrolled' => (int) $c->enrollments_count,
                'completed' => (int) $c->completed_enrollments_count,
                'started_learners' => (int) $c->started_learners,
                'average_completion' => $analytics->averageCompletion($c),
            ]);
        } else {
            $page = User::query()
                ->realUsers()
                ->select(['id', 'name', 'email', 'status'])
                ->where(fn (Builder $q) => $q->whereHas('learningProgress')->orWhereHas('learningEnrollments'))
                ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->withCount([
                    'learningEnrollments as enrolled_count',
                    'learningProgress as started_count',
                    'learningProgress as completed_count' => fn (Builder $q) => $q->whereNotNull('completed_at'),
                ])
                ->withSum('learningProgress as watch_seconds', 'watched_seconds')
                ->withMax('learningProgress as last_activity_at', 'last_watched_at')
                ->orderByDesc('last_activity_at')
                ->orderBy('name')
                ->paginate(20);

            $rows = collect($page->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status,
                'enrolled' => (int) $u->enrolled_count,
                'started' => (int) $u->started_count,
                'completed' => (int) $u->completed_count,
                'watch_seconds' => (int) $u->watch_seconds,
                'last_activity_at' => $u->last_activity_at ? Carbon::parse($u->last_activity_at)->toIso8601String() : null,
            ]);
        }

        return response()->json([
            'data' => $rows->values(),
            'meta' => [
                'tab' => $tab,
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function learner(User $user, ProgressService $progress): JsonResponse
    {
        abort_if($user->isGuest(), 404);

        $enrolledIds = $user->learningEnrollments()->pluck('learning_course_id');
        $progressedIds = LearningVideo::query()
            ->whereNotNull('learning_course_id')
            ->whereIn('id', LearningVideoProgress::query()->select('learning_video_id')->where('user_id', $user->id))
            ->distinct()
            ->pluck('learning_course_id');
        $enrollments = $user->learningEnrollments()->get()->keyBy('learning_course_id');

        $courses = LearningCourse::query()
            ->with('category:id,name')
            ->whereIn('id', $enrolledIds->merge($progressedIds)->unique()->values())
            ->orderBy('title')
            ->limit(50)
            ->get()
            ->map(function (LearningCourse $course) use ($user, $progress, $enrollments) {
                $p = $progress->courseProgress($user, $course);
                $e = $enrollments->get($course->id);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'category' => $course->category?->name,
                    'total' => $p['total'],
                    'completed' => $p['completed'],
                    'percent' => $p['percent'],
                    'enrolled' => $e !== null,
                    'enrolled_at' => self::iso($e?->created_at),
                    'course_completed_at' => self::iso($e?->completed_at),
                ];
            });

        $lessons = LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('video')
            ->with(['video:id,title,learning_course_id,duration_seconds', 'video.course:id,title'])
            ->orderByDesc('last_watched_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (LearningVideoProgress $p) => [
                'title' => $p->video?->title,
                'course' => $p->video?->course?->title,
                'watched_seconds' => (int) $p->watched_seconds,
                'duration_seconds' => (int) ($p->video?->duration_seconds ?? 0),
                'completed' => $p->completed_at !== null,
                'last_watched_at' => self::iso($p->last_watched_at),
            ]);

        $attendance = LearningRoomAttendance::query()
            ->where('user_id', $user->id)
            ->with([
                'room' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'deleted_at']),
                'session:id,started_at,ended_at',
            ])
            ->orderByDesc('first_joined_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (LearningRoomAttendance $a) => [
                'room_title' => $a->room?->title ?? 'Deleted room',
                'joined_at' => self::iso($a->first_joined_at),
                'total_seconds' => (int) $a->total_seconds,
                'join_count' => (int) $a->join_count,
                'role' => $a->role,
            ]);

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status],
            'stats' => $progress->stats($user),
            'courses' => $courses->values(),
            'lessons' => $lessons->values(),
            'attendance' => $attendance->values(),
        ]);
    }

    // ========================================================= instructors

    public function instructors(Request $request): JsonResponse
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $like = self::like($search);

        $page = User::query()
            ->realUsers()
            ->where('can_teach', true)
            ->withCount(['hostedRooms', 'teachingVideos', 'taughtCourses'])
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)))
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'rooms' => (int) $u->hosted_rooms_count,
                'lessons' => (int) $u->teaching_videos_count,
                'courses' => (int) $u->taught_courses_count,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'can_manage' => Gate::allows('learning.manage'),
            ],
        ]);
    }

    /** Active members who could get instructor access (search by name or email). */
    public function instructorCandidates(Request $request): JsonResponse
    {
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }
        $like = self::like($search);

        $users = User::query()
            ->realUsers()
            ->where('can_teach', false)
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values()]);
    }

    public function grantInstructor(Request $request, LearningNotifier $notifier): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::query()->realUsers()->findOrFail((int) $data['user_id']);

        if ($user->isInstructor()) {
            return response()->json(['message' => $user->name.' already has instructor access.']);
        }
        if (! $user->isActive()) {
            return response()->json(['message' => $user->name.' is not an active member. Activate the account first.'], 422);
        }

        $user->forceFill(['can_teach' => true])->save();
        ActivityLog::log('learning_instructor_granted', 'User', $user->id, ['email' => $user->email]);
        $notifier->notifyInstructorGranted($user, $request->user());

        return response()->json(['message' => $user->name.' can now host live rooms and upload lessons.']);
    }

    public function revokeInstructor(Request $request, User $user): JsonResponse
    {
        abort_if($user->isGuest(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        if (! $user->isInstructor()) {
            return response()->json(['message' => $user->name.' does not have instructor access.']);
        }

        $user->forceFill(['can_teach' => false])->save();
        ActivityLog::log('learning_instructor_revoked', 'User', $user->id, [
            'email' => $user->email,
            'reason' => $data['reason'],
            'hosted_rooms' => $user->hostedRooms()->count(),
            'lessons' => $user->teachingVideos()->count(),
            'courses' => $user->taughtCourses()->count(),
        ]);

        return response()->json(['message' => 'Instructor access removed from '.$user->name.'. Their rooms, lessons and courses stay in place.']);
    }

    // ============================================================== topics

    public function topics(Request $request, LearningDeletionService $deletions): JsonResponse
    {
        $courseId = $request->integer('course') ?: null;

        $page = LearningTopic::query()
            ->whereHas('course')
            ->with('course:id,title')
            ->withCount('videos')
            ->when($courseId, fn (Builder $q, int $id) => $q->where('learning_course_id', $id))
            ->orderBy('learning_course_id')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(30);

        $canManage = Gate::allows('learning.manage');

        return response()->json([
            'data' => collect($page->items())->map(fn (LearningTopic $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'position' => (int) $t->position,
                'course_id' => $t->learning_course_id,
                'course_title' => $t->course?->title,
                'lessons' => (int) $t->videos_count,
                'delete_impact' => $canManage ? array_values(array_filter($deletions->impact($t))) : [],
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'can_manage' => $canManage,
                'courses' => LearningCourse::query()->orderBy('title')->get(['id', 'title']),
            ],
        ]);
    }

    public function storeTopic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learning_course_id' => ['required', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $topic = LearningTopic::create([
            'learning_course_id' => (int) $data['learning_course_id'],
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'position' => isset($data['position'])
                ? (int) $data['position']
                : (int) LearningTopic::query()->where('learning_course_id', $data['learning_course_id'])->max('position') + 1,
        ]);

        ActivityLog::log('learning_topic_created', 'LearningTopic', $topic->id, [
            'title' => $topic->title,
            'course_id' => $topic->learning_course_id,
        ]);

        return response()->json(['message' => 'Topic “'.$topic->title.'” added.'], 201);
    }

    public function updateTopic(Request $request, LearningTopic $topic): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $topic->update([
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'position' => isset($data['position']) ? (int) $data['position'] : (int) $topic->position,
        ]);

        ActivityLog::log('learning_topic_updated', 'LearningTopic', $topic->id, [
            'title' => $topic->title,
            'course_id' => $topic->learning_course_id,
            'changes' => array_values(array_diff(array_keys($topic->getChanges()), ['updated_at'])),
        ]);

        return response()->json(['message' => 'Topic “'.$topic->title.'” updated.']);
    }

    public function destroyTopic(Request $request, LearningTopic $topic, LearningDeletionService $deletions): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $deletions->deleteTopic($topic, $data['reason']);

        return response()->json(['message' => 'Topic “'.$topic->title.'” deleted. Its lessons stay in the course without a topic.']);
    }
}
