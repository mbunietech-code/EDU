<?php

namespace App\Http\Controllers\Studio;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\User;
use App\Services\Learning\ChunkedUploadService;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Room management in the Teaching Studio (bound by id).
 *
 * Who: rooms.view / rooms.manage staff see every room (rooms.view alone is
 * read-only); hosts (instructors) see and manage only the rooms they host.
 * Every write is authorised through LearningRoomPolicy and performed by
 * RoomService / LearningDeletionService, which also enforce the state rules.
 *
 * Product rule (enforced here, server-side, same as for lessons): a host
 * without rooms.manage / learning.manage may only attach a room to a course
 * they instruct (or keep the course it already has); any category is fine.
 */
class RoomController extends Controller
{
    private const PER_PAGE = 20;

    public const TABS = [
        'all' => 'All',
        'live' => 'Live',
        'upcoming' => 'Upcoming',
        'completed' => 'Completed',
        'drafts' => 'Drafts',
        'cancelled' => 'Cancelled',
    ];

    /** Tab key => room status. */
    private const TAB_STATUS = [
        'live' => 'live',
        'upcoming' => 'scheduled',
        'completed' => 'completed',
        'drafts' => 'draft',
        'cancelled' => 'cancelled',
    ];

    private const TIME_PATTERN = '/\A([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?\z/';

    public function __construct(protected RoomService $rooms)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->canUseRooms($user)) {
            // /studio lands here; lesson-only staff belong on the lessons list.
            abort_unless($user->canUploadLessons() || $user->hasPermission('learning.view'), 403);

            return redirect()->route('studio.videos.index');
        }

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'all';
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $seesAll = $this->seesAll($user);

        $base = LearningRoom::query()
            ->when(! $seesAll, fn (Builder $q) => $q->where('host_id', $user->id))
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.addcslashes($search, '\\%_').'%'));

        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $counts = ['all' => (int) $byStatus->sum()];
        foreach (self::TAB_STATUS as $key => $status) {
            $counts[$key] = (int) ($byStatus[$status] ?? 0);
        }

        $query = (clone $base)
            ->when(isset(self::TAB_STATUS[$tab]), fn (Builder $q) => $q->where('status', self::TAB_STATUS[$tab]))
            ->with([
                'host:id,name',
                'category:id,name',
                'course' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'deleted_at']),
            ])
            ->withCount([
                'members',
                'sessions',
                'attendances',
                'messages',
                'recordings',
            ])
            ->withSum(['recordings as recording_bytes' => fn (Builder $q) => $q->whereNotNull('path')], 'size_bytes')
            ->withCount(['recordings as recording_files_count' => fn (Builder $q) => $q->whereNotNull('path')]);

        if ($tab === 'upcoming') {
            $query->orderBy('scheduled_at')->orderBy('id');
        } else {
            // Live rooms first; portable CASE (works on MySQL and SQLite).
            $query->orderByRaw("CASE WHEN status = 'live' THEN 0 ELSE 1 END")
                ->orderByDesc('scheduled_at')
                ->orderByDesc('id');
        }

        $rooms = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('studio.rooms.index', [
            'rooms' => $rooms,
            'participants' => $this->participantSummaries($rooms->getCollection()),
            'impacts' => $rooms->getCollection()->mapWithKeys(fn (LearningRoom $r) => [$r->id => $this->impactFromCounts($r)])->all(),
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'search' => $search,
            'seesAll' => $seesAll,
            'canCreate' => $user->can('create', LearningRoom::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LearningRoom::class);

        $room = new LearningRoom([
            'access' => 'public',
            'status' => 'draft',
            'duration_minutes' => 60,
            'chat_enabled' => true,
            'questions_enabled' => true,
            'allow_participant_media' => true,
        ]);

        return view('studio.rooms.create', $this->formOptions($request->user(), $room));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LearningRoom::class);
        $user = $request->user();

        $data = $request->validate($this->rules(), $this->messages());
        $action = $data['action'] ?? 'draft';
        $action = $action === 'schedule' ? 'schedule' : 'draft';

        $this->assertValidInput($request, $data, $user, null, $action);

        $payload = $this->placementPayload($data) + [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? '',
            'scheduled_time' => $data['scheduled_time'] ?? '',
            'duration_minutes' => $data['duration_minutes'],
            'access' => $data['access'],
            'status' => $action === 'schedule' ? 'scheduled' : 'draft',
            'notify' => $request->boolean('notify'),
        ] + $this->togglePayload($request, creating: true);

        if ($data['access'] === 'private') {
            $payload['member_ids'] = $data['user_ids'] ?? [];
        }

        if ($this->canPickHost($user) && filled($data['host_id'] ?? null)) {
            $payload['host_id'] = (int) $data['host_id'];
        }

        $room = $this->rooms->create($payload, $user);

        return redirect()
            ->route('studio.rooms.show', $room)
            ->with('success', $room->isScheduled()
                ? 'Room scheduled'.($request->boolean('notify') ? ' — eligible learners are being notified.' : '.')
                : 'Room saved as a draft. Schedule it when you are ready.');
    }

    public function show(Request $request, LearningRoom $room, LearningDeletionService $deletions, LiveProvider $live): View
    {
        $this->authorize('viewAttendance', $room);
        $user = $request->user();
        $canManage = $user->can('manage', $room);

        $room->load([
            'host:id,name',
            'category:id,name',
            'course' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'slug', 'status', 'deleted_at']),
            'topic:id,title',
        ])->loadCount('members');

        $sessionsTotal = $room->sessions()->count();
        $sessions = $room->sessions()
            ->with(['starter:id,name'])
            ->withCount('attendances')
            ->limit(20)
            ->get();

        $messageCounts = $sessions->isEmpty() ? collect() : LearningRoomMessage::query()
            ->whereIn('learning_room_session_id', $sessions->pluck('id'))
            ->select('learning_room_session_id', DB::raw('count(*) as aggregate'))
            ->groupBy('learning_room_session_id')
            ->pluck('aggregate', 'learning_room_session_id');

        $recordings = $room->recordings()
            ->with([
                'session:id,started_at',
                'uploader:id,name',
                'video' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'instructor_id', 'learning_course_id', 'status', 'deleted_at']),
            ])
            ->get();

        $recordingsBySession = $recordings->groupBy('learning_room_session_id');

        $present = $room->isLive() ? $this->rooms->presentParticipants($room) : collect();

        return view('studio.rooms.show', [
            'room' => $room,
            'canManage' => $canManage,
            'canCreateLessons' => $user->can('create', LearningVideo::class),
            'provider' => $canManage ? $live->status() : null,
            'providerName' => $live->name(),
            'supportsRecording' => $live->supportsRecording(),
            'impact' => $canManage ? $deletions->impact($room) : [],
            'sessions' => $sessions,
            'sessionsTotal' => $sessionsTotal,
            'sessionImpacts' => $sessions->mapWithKeys(fn (LearningRoomSession $s) => [
                $s->id => $this->sessionImpact($s, (int) ($messageCounts[$s->id] ?? 0), $recordingsBySession->get($s->id, collect())),
            ])->all(),
            'recordings' => $recordings,
            'recordingImpacts' => $recordings->mapWithKeys(fn ($r) => [$r->id => $deletions->impact($r)])->all(),
            'present' => $present,
            'uploader' => $canManage ? $this->uploaderConfig() : null,
        ]);
    }

    public function edit(Request $request, LearningRoom $room): View
    {
        $this->authorize('update', $room);

        $room->load(['memberUsers:id,name,email', 'host:id,name']);

        return view('studio.rooms.edit', $this->formOptions($request->user(), $room));
    }

    public function update(Request $request, LearningRoom $room): RedirectResponse
    {
        $this->authorize('update', $room);
        $user = $request->user();

        if ($room->isLive()) {
            // While live only the text and the three toggles may change.
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
                'chat_enabled' => ['nullable', 'boolean'],
                'questions_enabled' => ['nullable', 'boolean'],
                'allow_participant_media' => ['nullable', 'boolean'],
            ]);

            $this->rooms->update($room, [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
            ] + $this->togglePayload($request, creating: false), $user);

            return redirect()->route('studio.rooms.show', $room)->with('success', 'Room updated. Learners in the call see the change within a few seconds.');
        }

        $data = $request->validate($this->rules(), $this->messages());
        $action = in_array($data['action'] ?? 'save', ['schedule', 'draft', 'save'], true) ? ($data['action'] ?? 'save') : 'save';
        if ($action === 'schedule' && ! in_array($room->status, ['draft', 'cancelled'], true)) {
            $action = 'save';
        }

        $this->assertValidInput($request, $data, $user, $room, $action);

        $payload = $this->placementPayload($data) + [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? '',
            'scheduled_time' => $data['scheduled_time'] ?? '',
            'duration_minutes' => $data['duration_minutes'],
            'access' => $data['access'],
        ] + $this->togglePayload($request, creating: false);

        if ($data['access'] === 'private' && $request->boolean('members_present')) {
            $payload['member_ids'] = $data['user_ids'] ?? [];
        }

        if ($this->canPickHost($user) && filled($data['host_id'] ?? null)) {
            $payload['host_id'] = (int) $data['host_id'];
        }

        $notify = $request->boolean('notify');

        DB::transaction(function () use ($room, $payload, $user, $action, $notify) {
            $this->rooms->update($room, $payload, $user);

            if ($action === 'schedule') {
                $this->rooms->publish($room, $user, $notify);
            }
        });

        return redirect()->route('studio.rooms.show', $room)->with('success', match (true) {
            $action === 'schedule' && $notify => 'Room scheduled — eligible learners are being notified.',
            $action === 'schedule' => 'Room scheduled.',
            default => 'Room details saved.',
        });
    }

    public function destroy(Request $request, LearningRoom $room, LearningDeletionService $deletions): RedirectResponse
    {
        $this->authorize('delete', $room);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Give a reason for deleting this room.',
            'reason.min' => 'The reason must be at least 3 characters.',
        ]);

        try {
            $deletions->deleteRoom($room, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('studio.rooms.index')
            ->with('success', '“'.$room->title.'” was moved to the trash. An administrator can restore it within '
                .(int) config('learning.trash_retention_days', 30).' days.');
    }

    public function publish(Request $request, LearningRoom $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $request->validate(['notify' => ['nullable', 'boolean']]);
        $notify = $request->boolean('notify', true);

        try {
            $this->rooms->publish($room, $request->user(), $notify);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'The room could not be scheduled.');
        }

        return redirect()->route('studio.rooms.show', $room)
            ->with('success', $notify ? 'Room scheduled — eligible learners are being notified.' : 'Room scheduled.');
    }

    // --- Internals -------------------------------------------------------
    private function canUseRooms(User $user): bool
    {
        return $user->canHostRooms() || $user->hasPermission('rooms.view');
    }

    private function seesAll(User $user): bool
    {
        return $user->hasPermission('rooms.view') || $user->hasPermission('rooms.manage');
    }

    private function canPickHost(User $user): bool
    {
        return $user->hasPermission('rooms.manage');
    }

    private function usesAnyCourse(User $user): bool
    {
        return $user->hasPermission('rooms.manage') || $user->hasPermission('learning.manage');
    }

    /** @return array<string,mixed> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'learning_category_id' => ['nullable', 'integer', Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'learning_course_id' => ['nullable', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'learning_topic_id' => ['nullable', 'integer', Rule::exists('learning_topics', 'id')],
            'host_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'scheduled_date' => ['nullable', 'date_format:Y-m-d', 'required_with:scheduled_time'],
            'scheduled_time' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN, 'required_with:scheduled_date'],
            'duration_minutes' => ['required', 'integer', 'min:'.RoomService::MIN_DURATION_MINUTES, 'max:'.RoomService::MAX_DURATION_MINUTES],
            'access' => ['required', Rule::in(array_keys(LearningRoom::ACCESS))],
            'user_ids' => ['nullable', 'array', 'max:500'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'members_present' => ['nullable', 'boolean'],
            'chat_enabled' => ['nullable', 'boolean'],
            'questions_enabled' => ['nullable', 'boolean'],
            'allow_participant_media' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['draft', 'schedule', 'save'])],
        ];
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'learning_category_id.exists' => 'The selected category does not exist.',
            'learning_course_id.exists' => 'The selected course does not exist.',
            'learning_topic_id.exists' => 'The selected topic does not exist.',
            'scheduled_date.date_format' => 'Give a valid date.',
            'scheduled_date.required_with' => 'Pick a date to go with the start time.',
            'scheduled_time.regex' => 'Give a valid start time (HH:MM).',
            'scheduled_time.required_with' => 'Pick a start time to go with the date.',
            'duration_minutes.min' => 'A session lasts at least '.RoomService::MIN_DURATION_MINUTES.' minutes.',
            'duration_minutes.max' => 'A session lasts at most '.RoomService::MAX_DURATION_MINUTES.' minutes (24 hours).',
            'access.in' => 'Choose who may join the room.',
            'user_ids.*.exists' => 'One of the invited members no longer exists.',
        ];
    }

    /**
     * Rules the plain validator cannot express: the start time, placement
     * consistency, the course product rule, audiences and invited members.
     */
    private function assertValidInput(Request $request, array $data, User $user, ?LearningRoom $room, string $action): void
    {
        $errors = [];

        // --- When -------------------------------------------------------
        $at = null;
        if (filled($data['scheduled_date'] ?? null) && filled($data['scheduled_time'] ?? null)) {
            [$h, $m] = array_map('intval', explode(':', (string) $data['scheduled_time']));
            $at = Carbon::createFromFormat('Y-m-d', (string) $data['scheduled_date'], (string) config('app.timezone'))
                ->setTime($h, $m, 0);
        }

        if ($action === 'schedule' && $at === null) {
            $errors['scheduled_date'] = 'Pick a date and start time to schedule the room.';
        }

        // A date may stay in the past only when it is the one the room already has
        // (e.g. renaming a completed room); anything new must be now or later.
        $unchanged = $at !== null && $room?->scheduled_at !== null && $room->scheduled_at->equalTo($at);
        if ($at !== null && $at->lt(now()->subMinute()) && ($action === 'schedule' || ! $unchanged)) {
            $errors['scheduled_date'] = 'The start time has already passed — pick a time from now on.';
        }

        // --- Where ------------------------------------------------------
        $categoryId = filled($data['learning_category_id'] ?? null) ? (int) $data['learning_category_id'] : null;
        $courseId = filled($data['learning_course_id'] ?? null) ? (int) $data['learning_course_id'] : null;
        $topicId = filled($data['learning_topic_id'] ?? null) ? (int) $data['learning_topic_id'] : null;

        $course = $courseId ? LearningCourse::query()->find($courseId) : null;
        if ($course && $categoryId && (int) $course->learning_category_id !== $categoryId) {
            $errors['learning_course_id'] = 'The selected course is not in the selected category.';
        }

        if ($topicId) {
            $topic = LearningTopic::query()->find($topicId);
            if (! $course) {
                $errors['learning_topic_id'] = 'Choose the course first — a topic belongs to a course.';
            } elseif ($topic && (int) $topic->learning_course_id !== (int) $course->id) {
                $errors['learning_topic_id'] = 'The topic does not belong to the selected course.';
            }
        }

        if ($course && ! $this->usesAnyCourse($user)
            && (int) $course->instructor_id !== (int) $user->id
            && ($room === null || (int) $room->learning_course_id !== (int) $course->id)) {
            $errors['learning_course_id'] = 'You can only link a room to a course you teach. Leave the course empty to use any category.';
        }

        // --- Who --------------------------------------------------------
        $access = (string) $data['access'];
        if ($access === 'course' && ! $course) {
            $errors['learning_course_id'] ??= 'Pick the course whose learners may join.';
        }

        if ($access === 'category' && ! $categoryId && ! $course) {
            $errors['learning_category_id'] = 'Pick the category whose learners may join.';
        }

        if ($access === 'private') {
            $hostId = $this->canPickHost($user) && filled($data['host_id'] ?? null)
                ? (int) $data['host_id']
                : (int) ($room?->host_id ?? $user->id);

            if ($room === null || $request->boolean('members_present')) {
                $invited = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $hostId);
                $hasMembers = $invited->isNotEmpty();
            } else {
                $hasMembers = $room->members()->where('user_id', '!=', $hostId)->exists();
            }

            if (! $hasMembers) {
                $errors['user_ids'] = 'Invite at least one member to a private room.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string,mixed> */
    private function placementPayload(array $data): array
    {
        return [
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
        ];
    }

    /**
     * The three switches. The form posts a hidden 0 before each checkbox; a
     * create request that leaves one out keeps the default (on).
     *
     * @return array<string,bool>
     */
    private function togglePayload(Request $request, bool $creating): array
    {
        $out = [];
        foreach (['chat_enabled', 'questions_enabled', 'allow_participant_media'] as $toggle) {
            if ($request->has($toggle)) {
                $out[$toggle] = $request->boolean($toggle);
            } elseif ($creating) {
                $out[$toggle] = true;
            }
        }

        return $out;
    }

    /**
     * Select options and Alpine config for the room form.
     *
     * @return array<string,mixed>
     */
    private function formOptions(User $user, LearningRoom $room): array
    {
        $anyCourse = $this->usesAnyCourse($user);

        $categories = LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']);

        $courses = LearningCourse::query()
            ->when(! $anyCourse, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('instructor_id', $user->id)
                ->when($room->learning_course_id, fn (Builder $w) => $w->orWhere('id', $room->learning_course_id))))
            ->orderBy('title')
            ->get(['id', 'learning_category_id', 'title', 'status']);

        $topics = $courses->isEmpty()
            ? collect()
            : LearningTopic::query()
                ->whereIn('learning_course_id', $courses->pluck('id'))
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'learning_course_id', 'title']);

        $timezone = (string) config('app.timezone');
        $now = now($timezone);
        $localStart = $room->scheduled_at?->copy()->setTimezone($timezone);
        $selected = $this->selectedMembers($user, $room);

        return [
            'room' => $room,
            'categories' => $categories,
            'courses' => $courses,
            'canPickHost' => $this->canPickHost($user),
            'hosts' => $this->canPickHost($user) ? $this->hostOptions($room) : collect(),
            'anyCourse' => $anyCourse,
            'locked' => $room->isLive(),
            'selectedMembers' => $selected,
            'defaultDate' => old('scheduled_date', $localStart?->format('Y-m-d')),
            'defaultTime' => old('scheduled_time', $localStart?->format('H:i')),
            'timezone' => $timezone,
            'memberPicker' => [
                'searchUrl' => route('studio.users.search'),
                'name' => 'user_ids[]',
                'selected' => $selected,
                'multiple' => true,
                'excludeIds' => array_values(array_filter([$room->host_id ?? $user->id])),
                'inputId' => 'room-members',
                'label' => 'Search members to invite',
            ],
            'formConfig' => [
                'courses' => $courses->map(fn (LearningCourse $c) => [
                    'id' => $c->id,
                    'category_id' => $c->learning_category_id,
                    'title' => $c->title.($c->status === 'published' ? '' : ' (draft)'),
                ])->values(),
                'topics' => $topics->map(fn (LearningTopic $t) => [
                    'id' => $t->id,
                    'course_id' => $t->learning_course_id,
                    'title' => $t->title,
                ])->values(),
                'categoryId' => old('learning_category_id', $room->learning_category_id),
                'courseId' => old('learning_course_id', $room->learning_course_id),
                'topicId' => old('learning_topic_id', $room->learning_topic_id),
                'access' => old('access', $room->access ?? 'public'),
                'locked' => $room->isLive(),
                'today' => $now->format('Y-m-d'),
                'nowTime' => $now->format('H:i'),
                'originalDate' => $localStart?->format('Y-m-d'),
                'originalTime' => $localStart?->format('H:i'),
                'date' => old('scheduled_date', $localStart?->format('Y-m-d')),
                'time' => old('scheduled_time', $localStart?->format('H:i')),
            ],
        ];
    }

    /** Users who can host (instructors and room managers), plus the current host. */
    private function hostOptions(LearningRoom $room): Collection
    {
        return User::query()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w
                    ->where('status', 'active')
                    ->where(fn (Builder $r) => $r->where('can_teach', true)->orWhere('is_admin', true)))
                ->when($room->host_id, fn (Builder $w) => $w->orWhere('id', $room->host_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'can_teach', 'is_admin', 'role', 'permissions', 'status'])
            ->filter(fn (User $u) => $u->canHostRooms() || (int) $u->id === (int) $room->host_id)
            ->values();
    }

    /** @return list<array{id:int,name:string,email?:string}> */
    private function selectedMembers(User $viewer, LearningRoom $room): array
    {
        $old = old('user_ids');

        $users = is_array($old)
            ? User::query()->whereIn('id', array_filter(array_map('intval', $old)))->orderBy('name')->get(['id', 'name', 'email'])
            : ($room->exists ? $room->memberUsers()->orderBy('name')->get(['users.id', 'users.name', 'users.email']) : collect());

        return $users->map(fn (User $u) => array_filter([
            'id' => $u->id,
            'name' => $u->name,
            'email' => $viewer->is_admin ? $u->email : null,
        ]))->values()->all();
    }

    /**
     * Participants column: present now (live), else the last session's
     * attendees, plus the invited count for private rooms — two grouped
     * queries for the whole page.
     *
     * @param  Collection<int,LearningRoom>  $rooms
     * @return array<int,array{present:?int,attended:?int,invited:?int}>
     */
    private function participantSummaries(Collection $rooms): array
    {
        if ($rooms->isEmpty()) {
            return [];
        }

        $latest = LearningRoomSession::query()
            ->whereIn('learning_room_id', $rooms->pluck('id'))
            ->select('learning_room_id', DB::raw('max(id) as session_id'))
            ->groupBy('learning_room_id')
            ->pluck('session_id', 'learning_room_id');

        $attended = $latest->isEmpty() ? collect() : LearningRoomAttendance::query()
            ->whereIn('learning_room_session_id', $latest->values())
            ->select('learning_room_session_id', DB::raw('count(*) as aggregate'))
            ->groupBy('learning_room_session_id')
            ->pluck('aggregate', 'learning_room_session_id');

        $liveSessions = $rooms->filter->isLive()->map(fn (LearningRoom $r) => $latest[$r->id] ?? null)->filter();
        $present = $liveSessions->isEmpty() ? collect() : LearningRoomAttendance::query()
            ->present()
            ->whereIn('learning_room_session_id', $liveSessions->values())
            ->select('learning_room_session_id', DB::raw('count(*) as aggregate'))
            ->groupBy('learning_room_session_id')
            ->pluck('aggregate', 'learning_room_session_id');

        return $rooms->mapWithKeys(function (LearningRoom $room) use ($latest, $attended, $present) {
            $sessionId = $latest[$room->id] ?? null;

            return [$room->id => [
                'present' => $room->isLive() ? (int) ($present[$sessionId] ?? 0) : null,
                'attended' => $sessionId !== null ? (int) ($attended[$sessionId] ?? 0) : null,
                'invited' => $room->access === 'private' ? (int) $room->members_count : null,
            ]];
        })->all();
    }

    /**
     * The lines LearningDeletionService::impact() gives for a room, from the
     * list's withCount() columns (no extra queries per row).
     *
     * @return list<string>
     */
    private function impactFromCounts(LearningRoom $room): array
    {
        $recordings = (int) $room->recordings_count;
        $recordingLine = null;
        if ($recordings > 0) {
            $recordingLine = $this->countLine($recordings, 'recording');
            if ((int) $room->recording_files_count > 0) {
                $recordingLine .= ' ('.LearningVideo::humanBytes((int) $room->recording_bytes).') — files deleted when purged from the trash';
            }
        }

        return array_values(array_filter([
            $this->countLine((int) $room->sessions_count, 'session record'),
            $this->countLine((int) $room->attendances_count, 'attendance record'),
            $this->countLine((int) $room->messages_count, 'chat message'),
            $recordingLine,
        ]));
    }

    /** @return list<string> */
    private function sessionImpact(LearningRoomSession $session, int $messages, Collection $recordings): array
    {
        $recordingLine = null;
        if ($recordings->isNotEmpty()) {
            $recordingLine = $this->countLine($recordings->count(), 'recording');
            $withFiles = $recordings->filter(fn ($r) => $r->path !== null);
            if ($withFiles->isNotEmpty()) {
                $recordingLine .= ' ('.LearningVideo::humanBytes((int) $withFiles->sum('size_bytes')).') — files deleted';
            }
        }

        return array_values(array_filter([
            $this->countLine((int) $session->attendances_count, 'attendance record'),
            $messages > 0 ? $this->countLine($messages, 'chat message').' kept on the room' : null,
            $recordingLine,
        ]));
    }

    private function countLine(int $n, string $singular): ?string
    {
        return $n > 0 ? $n.' '.($n === 1 ? $singular : Str::plural($singular)) : null;
    }

    /** learnChunkUploader() config for recording uploads. */
    private function uploaderConfig(): array
    {
        $placeholder = str_repeat('T', 40); // satisfies the route's token pattern
        $tokenUrl = fn (string $name) => str_replace($placeholder, '__TOKEN__', route($name, ['token' => $placeholder]));

        $limits = app(ChunkedUploadService::class)->limits();
        $rules = $limits['purposes']['recording'] ?? ['extensions' => [], 'mimes' => [], 'max_bytes' => 0];

        return [
            'configUrl' => route('studio.uploads.config'),
            'initUrl' => route('studio.uploads.init'),
            'chunkUrl' => $tokenUrl('studio.uploads.chunk'),
            'completeUrl' => $tokenUrl('studio.uploads.complete'),
            'abortUrl' => $tokenUrl('studio.uploads.abort'),
            'purpose' => 'recording',
            'accept' => 'video/*,'.implode(',', array_map(fn ($ext) => '.'.$ext, $rules['extensions'] ?? [])),
            'autoThumbnail' => false,
            'captureMeta' => true,
            'required' => true,
            'maxBytes' => (int) ($rules['max_bytes'] ?? 0),
            'extensions' => $rules['extensions'] ?? [],
            'existing' => null,
        ];
    }
}
