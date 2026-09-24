<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Learning\RoomAccessException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Learn\DashboardController as LearnDashboard;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\LearningVideoResource;
use App\Models\User;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\ProgressService;
use App\Services\Learning\RoomService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Learning API for the MHub app (Sanctum). Media is served via temporary signed URLs.
 *
 * Every list goes through the models' visibleTo() scopes and every detail
 * through the policies, exactly like the web pages. Lists are paginated (20)
 * as {data: [...], meta: {current_page, last_page, per_page, total}}.
 */
class LearningController extends Controller
{
    private const PER_PAGE = 20;

    private const STREAM_TTL_HOURS = 6;

    public function __construct(protected ProgressService $progress)
    {
    }

    // --- Home & catalogue ---------------------------------------------------
    public function home(Request $request): JsonResponse
    {
        $user = $request->user();

        $live = LearningRoom::query()->live()->visibleTo($user)
            ->with(['host:id,name', 'category:id,name,slug', 'course:id,title,slug'])
            ->orderByDesc('started_at')->limit(6)->get();

        $upcoming = LearningRoom::query()->upcoming()->visibleTo($user)
            ->with(['host:id,name', 'category:id,name,slug', 'course:id,title,slug'])
            ->limit(6)->get();

        $courses = LearnDashboard::myCoursesQuery($user)
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($user)])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(6)->get();

        return response()->json([
            'stats' => $this->progress->stats($user),
            'live' => $live->map(fn (LearningRoom $r) => $this->roomCard($r))->values(),
            'continue_watching' => $this->progress->continueWatching($user, 6)
                ->map(fn (LearningVideoProgress $p) => $this->videoCard($p->video, $p))->values(),
            'my_courses' => $courses->map(fn (LearningCourse $c) => $this->courseCard($c, $this->progress->courseProgress($user, $c)))->values(),
            'upcoming' => $upcoming->map(fn (LearningRoom $r) => $this->roomCard($r))->values(),
            'recently_watched' => $this->progress->recentlyWatched($user, 8)
                ->map(fn (LearningVideoProgress $p) => $this->videoCard($p->video, $p))->values(),
            'completed' => $this->progress->completedLessons($user, 8)
                ->map(fn (LearningVideoProgress $p) => $this->videoCard($p->video, $p))->values(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = LearningCategory::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('courses', fn ($c) => $c->published()->visibleTo($user))
                ->orWhereHas('videos', fn ($v) => $v->published()->visibleTo($user)))
            ->withCount([
                'courses' => fn ($c) => $c->published()->visibleTo($user),
                'videos' => fn ($v) => $v->published()->visibleTo($user),
            ])
            ->orderBy('position')->orderBy('name')
            ->paginate(self::PER_PAGE);

        return $this->paginated($page, fn (LearningCategory $c) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'description' => $c->description,
            'icon' => $c->icon,
            'courses_count' => (int) $c->courses_count,
            'lessons_count' => (int) $c->videos_count,
        ]);
    }

    public function courses(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'in:'.implode(',', array_keys(LearningCourse::LEVELS))],
            'mine' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:latest,popular,title'],
        ]);

        $query = filter_var($data['mine'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? LearnDashboard::myCoursesQuery($user)
            : LearningCourse::query()->published()->visibleTo($user);

        $page = $query
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($user)])
            ->withExists(['enrollments as is_enrolled' => fn ($q) => $q->where('user_id', $user->id)])
            ->when($data['category'] ?? null, fn (Builder $q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($data['level'] ?? null, fn (Builder $q, $level) => $q->where('level', $level))
            ->when(trim((string) ($data['q'] ?? '')) !== '', function (Builder $q) use ($data) {
                $term = '%'.addcslashes(trim($data['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->when(($data['sort'] ?? null) === 'popular', fn (Builder $q) => $q->withCount('enrollments')->orderByDesc('enrollments_count'))
            ->when(($data['sort'] ?? null) === 'title', fn (Builder $q) => $q->orderBy('title'))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->paginated($page, fn (LearningCourse $c) => $this->courseCard($c));
    }

    public function course(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $course = LearningCourse::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $course);

        $course->load(['category:id,name,slug', 'instructor:id,name']);

        $lessons = LearningVideo::query()
            ->where('learning_course_id', $course->id)
            ->published()
            ->visibleTo($user)
            ->with(['category:id,name,slug', 'instructor:id,name', 'progress' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('position')->orderBy('id')
            ->get();

        $course->setAttribute('videos_count', $lessons->count());

        $topics = $course->topics()->get();
        $topicIds = $topics->modelKeys();
        $byTopic = $lessons->groupBy(fn (LearningVideo $v) => (int) $v->learning_topic_id);

        $sections = $topics
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'lessons' => $byTopic->get($t->id, collect())->map(fn (LearningVideo $v) => $this->videoCard($v, $v->progressFor($user)))->values(),
            ])
            ->filter(fn ($s) => $s['lessons']->isNotEmpty())
            ->values();

        $loose = $lessons->reject(fn (LearningVideo $v) => $v->learning_topic_id !== null && in_array((int) $v->learning_topic_id, $topicIds, true));
        if ($loose->isNotEmpty()) {
            $sections->push([
                'id' => null,
                'title' => 'More lessons',
                'description' => null,
                'lessons' => $loose->map(fn (LearningVideo $v) => $this->videoCard($v, $v->progressFor($user)))->values(),
            ]);
        }

        $enrollment = LearningEnrollment::query()
            ->where('user_id', $user->id)->where('learning_course_id', $course->id)->first();
        $course->setAttribute('is_enrolled', $enrollment !== null);

        $rooms = LearningRoom::query()
            ->where('learning_course_id', $course->id)
            ->whereIn('status', ['scheduled', 'live'])
            ->visibleTo($user)
            ->with(['host:id,name', 'category:id,name,slug'])
            ->orderByRaw("case when status = 'live' then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->limit(6)->get();

        $progress = $this->progress->courseProgress($user, $course);

        return response()->json(['data' => $this->courseCard($course, $progress) + [
            'description' => $course->description,
            'description_html' => $course->descriptionHtml(),
            'enrollment' => $enrollment ? [
                'source' => $enrollment->source,
                'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
            ] : null,
            'can_enroll' => ! $enrollment && $course->isPublished() && $course->isOpen() && Gate::forUser($user)->allows('enroll', $course),
            'next_lesson' => $progress['next_video'] ? ['id' => $progress['next_video']->id, 'slug' => $progress['next_video']->slug, 'title' => $progress['next_video']->title] : null,
            'sections' => $sections,
            'rooms' => $rooms->map(fn (LearningRoom $r) => $this->roomCard($r))->values(),
        ]]);
    }

    public function enroll(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $course = LearningCourse::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $course);
        $this->authorizeFor($user, 'enroll', $course);

        // Gate::before lets super admins through the policy: re-check the state rule.
        if (! $course->isPublished() || ! $course->isOpen()) {
            return response()->json(['message' => 'This course does not accept self-enrolment.'], 422);
        }

        $enrollment = LearningEnrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'learning_course_id' => $course->id],
            ['source' => 'self', 'enrolled_at' => now()],
        );

        if ($enrollment->wasRecentlyCreated) {
            ActivityLog::log('learning_course_enrolled', 'LearningCourse', $course->id, ['user_id' => $user->id, 'source' => 'self', 'via' => 'api']);
        }

        return response()->json([
            'enrolled' => true,
            'created' => $enrollment->wasRecentlyCreated,
            'message' => $enrollment->wasRecentlyCreated
                ? 'You are enrolled in "'.$course->title.'".'
                : 'You are already enrolled in this course.',
        ], $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    // --- Lessons -------------------------------------------------------------
    public function videos(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'in:short,medium,long'],
            'status' => ['nullable', 'string', 'in:completed,in_progress,not_started'],
            'sort' => ['nullable', 'string', 'in:latest,popular,title,duration'],
        ]);

        $mine = fn ($p) => $p->where('user_id', $user->id);
        $status = $data['status'] ?? null;
        $duration = $data['duration'] ?? null;
        $sort = $data['sort'] ?? 'latest';

        $page = LearningVideo::query()
            ->published()
            ->visibleTo($user)
            ->with(['category:id,name,slug', 'course:id,title,slug', 'instructor:id,name', 'progress' => $mine])
            ->when($data['category'] ?? null, fn (Builder $q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($data['course'] ?? null, fn (Builder $q, $slug) => $q->whereHas('course', fn ($c) => $c->where('slug', $slug)))
            ->when($duration === 'short', fn (Builder $q) => $q->where('duration_seconds', '<', 600))
            ->when($duration === 'medium', fn (Builder $q) => $q->whereBetween('duration_seconds', [600, 1800]))
            ->when($duration === 'long', fn (Builder $q) => $q->where('duration_seconds', '>', 1800))
            ->when($status === 'not_started', fn (Builder $q) => $q->whereDoesntHave('progress', $mine))
            ->when($status === 'in_progress', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNull('completed_at')))
            ->when($status === 'completed', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNotNull('completed_at')))
            ->when(trim((string) ($data['q'] ?? '')) !== '', function (Builder $q) use ($data) {
                $term = '%'.addcslashes(trim($data['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($sort === 'popular', fn (Builder $q) => $q->orderByDesc('views'))
            ->when($sort === 'title', fn (Builder $q) => $q->orderBy('title'))
            ->when($sort === 'duration', fn (Builder $q) => $q
                ->orderByRaw('case when duration_seconds is null then 1 else 0 end')
                ->orderBy('duration_seconds'))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->paginated($page, fn (LearningVideo $v) => $this->videoCard($v, $v->progressFor($user)));
    }

    public function video(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $video = LearningVideo::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $video);

        $video->load([
            'category:id,name,slug',
            'course:id,title,slug',
            'topic:id,title',
            'instructor:id,name',
            'renditions',
            'resources',
        ]);

        $canManage = Gate::forUser($user)->allows('update', $video);
        // Same rule as MediaController: drafts only play for the people who can edit them.
        $canPlay = $video->hasFile() && ($video->isPublished() || $canManage);
        $progress = $video->progressFor($user);

        $sources = [];
        if ($canPlay) {
            $expires = now()->addHours(self::STREAM_TTL_HOURS);
            $sources[] = [
                'id' => 'original',
                'label' => $video->sourceQualityLabel().' (original)',
                'height' => $video->height,
                'type' => $video->mime ?: 'video/mp4',
                'url' => URL::temporarySignedRoute('signed.learning.videos.stream', $expires, ['video' => $video->id, 'u' => $user->id]),
            ];
            foreach ($video->renditions as $rendition) {
                $sources[] = [
                    'id' => $rendition->id,
                    'label' => $rendition->quality,
                    'height' => $rendition->height,
                    'type' => $rendition->mime ?: 'video/mp4',
                    'url' => URL::temporarySignedRoute('signed.learning.videos.stream', $expires, [
                        'video' => $video->id, 'rendition' => $rendition->id, 'u' => $user->id,
                    ]),
                ];
            }
        }

        return response()->json(['data' => $this->videoCard($video, $progress) + [
            'description' => $video->description,
            'description_html' => $video->descriptionHtml(),
            'tags' => array_values((array) ($video->tags ?? [])),
            'topic' => $video->topic ? ['id' => $video->topic->id, 'title' => $video->topic->title] : null,
            'status' => $video->status,
            'can_play' => $canPlay,
            'stream_url' => $sources[0]['url'] ?? null,
            'stream_expires_at' => $canPlay ? now()->addHours(self::STREAM_TTL_HOURS)->toIso8601String() : null,
            'sources' => $sources,
            'resume_at' => $this->resumeAt($video, $progress),
            'resources' => $video->resources->map(fn (LearningVideoResource $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'type' => $r->type,
                'url' => $r->isLink() ? $r->url : null,
                // Files need the web session route; no signed download exists for the app yet.
                'download_url' => null,
                'size_label' => $r->isFile() ? $r->sizeLabel() : null,
            ])->values(),
            'progress_url' => route('api.learning.videos.progress', $video->slug),
            'complete_url' => route('api.learning.videos.complete', $video->slug),
        ]]);
    }

    public function progress(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $video = LearningVideo::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $video);

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'event' => ['required', 'string', 'in:'.implode(',', ProgressService::EVENTS)],
            'watched' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        $row = $this->progress->record(
            $user,
            $video,
            (int) $data['position'],
            isset($data['duration']) ? (int) $data['duration'] : null,
            $data['event'],
            (int) ($data['watched'] ?? 0),
        );

        return response()->json($this->progressPayload($row));
    }

    public function complete(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $video = LearningVideo::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $video);

        $data = $request->validate([
            'completed' => ['sometimes', 'boolean'],
        ]);

        $completed = array_key_exists('completed', $data) ? filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN) : true;

        return response()->json($this->progressPayload($this->progress->setCompleted($user, $video, $completed)));
    }

    // --- Live rooms ----------------------------------------------------------
    public function rooms(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'tab' => ['nullable', 'string', 'in:live,upcoming,completed,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $tab = $data['tab'] ?? 'all';

        $page = LearningRoom::query()
            ->visibleTo($user)
            ->where('status', '!=', 'draft')
            ->with(['host:id,name', 'category:id,name,slug', 'course:id,title,slug'])
            ->when(trim((string) ($data['q'] ?? '')) !== '', function (Builder $q) use ($data) {
                $term = '%'.addcslashes(trim($data['q']), '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($tab === 'live', fn (Builder $q) => $q->where('status', 'live')->orderByDesc('started_at'))
            ->when($tab === 'upcoming', fn (Builder $q) => $q->where('status', 'scheduled')->orderBy('scheduled_at'))
            ->when($tab === 'completed', fn (Builder $q) => $q->where('status', 'completed')->orderByDesc('ended_at'))
            ->when($tab === 'all', fn (Builder $q) => $q
                ->orderByRaw("case status when 'live' then 0 when 'scheduled' then 1 else 2 end")
                ->orderByDesc('scheduled_at'))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->paginated($page, fn (LearningRoom $r) => $this->roomCard($r));
    }

    public function room(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $room = LearningRoom::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'view', $room);

        $room->load(['host:id,name', 'category:id,name,slug', 'course:id,title,slug']);

        $isManager = $room->isManageableBy($user);

        return response()->json(['data' => $this->roomCard($room) + [
            'description' => $room->description,
            'description_html' => $room->description
                ? Str::markdown($room->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
                : '',
            'cancel_reason' => $room->isCancelled() ? $room->cancel_reason : null,
            'chat_enabled' => (bool) $room->chat_enabled,
            'questions_enabled' => (bool) $room->questions_enabled,
            'allow_participant_media' => (bool) $room->allow_participant_media,
            'is_manager' => $isManager,
            'can_join' => $room->isLive(),
            'join_url' => route('api.learning.rooms.join', $room->slug),
            'web_url' => route('learn.rooms.live', $room),
            'shared_recordings_count' => $room->recordings()
                ->where('status', 'ready')->whereNotNull('path')
                ->when(! $isManager, fn (Builder $q) => $q->where('is_shared', true))
                ->count(),
        ]]);
    }

    /** Same contract as the web join: 409 not_live, 403 removed/inactive, 503 provider_unavailable. */
    public function join(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        $room = LearningRoom::query()->where('slug', $slug)->firstOrFail();

        $this->authorizeFor($user, 'join', $room);

        $live = app(LiveProvider::class);
        $status = $live->status();

        if (! $status['configured']) {
            return $this->providerUnavailable($room, $user, implode(' ', $status['issues']));
        }

        try {
            $result = app(RoomService::class)->join($room, $user);
        } catch (RoomAccessException $e) {
            return response()->json([
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], $e->reason === 'not_live' ? 409 : 403);
        } catch (\RuntimeException $e) {
            if ($e instanceof \PDOException) {
                throw $e;
            }
            report($e);

            return $this->providerUnavailable($room, $user, $e->getMessage());
        }

        return response()->json([
            'config' => $result['config'],
            'attendance_id' => $result['attendance']->id,
        ]);
    }

    // --- Cards & helpers ----------------------------------------------------
    protected function videoCard(LearningVideo $v, ?LearningVideoProgress $p = null): array
    {
        return [
            'id' => $v->id,
            'slug' => $v->slug,
            'title' => $v->title,
            'duration_seconds' => $v->duration_seconds,
            'duration_label' => $v->durationLabel(),
            'thumbnail_url' => $v->thumbnailUrl(),
            'visibility' => $v->visibility,
            'views' => (int) $v->views,
            'published_at' => $v->published_at?->toIso8601String(),
            'category' => $v->relationLoaded('category') && $v->category
                ? ['id' => $v->category->id, 'name' => $v->category->name, 'slug' => $v->category->slug] : null,
            'course' => $v->relationLoaded('course') && $v->course
                ? ['id' => $v->course->id, 'title' => $v->course->title, 'slug' => $v->course->slug] : null,
            'instructor' => $v->relationLoaded('instructor') && $v->instructor
                ? ['id' => $v->instructor->id, 'name' => $v->instructor->name] : null,
            'progress' => $p ? $this->progressPayload($p) : null,
            'web_url' => route('learn.videos.show', $v),
        ];
    }

    protected function courseCard(LearningCourse $c, ?array $progress = null): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'summary' => $c->summary,
            'level' => $c->level,
            'level_label' => $c->levelLabel(),
            'access' => $c->access,
            'thumbnail_url' => $c->thumbnailUrl(),
            'published_at' => $c->published_at?->toIso8601String(),
            'lessons_count' => $c->videos_count !== null ? (int) $c->videos_count : null,
            'is_enrolled' => $c->getAttribute('is_enrolled') !== null ? (bool) $c->getAttribute('is_enrolled') : null,
            'category' => $c->relationLoaded('category') && $c->category
                ? ['id' => $c->category->id, 'name' => $c->category->name, 'slug' => $c->category->slug] : null,
            'instructor' => $c->relationLoaded('instructor') && $c->instructor
                ? ['id' => $c->instructor->id, 'name' => $c->instructor->name] : null,
            'progress' => $progress ? [
                'total' => (int) $progress['total'],
                'completed' => (int) $progress['completed'],
                'percent' => (int) $progress['percent'],
                'started' => (bool) $progress['started'],
            ] : null,
            'web_url' => route('learn.courses.show', $c),
        ];
    }

    protected function roomCard(LearningRoom $r): array
    {
        return [
            'id' => $r->id,
            'slug' => $r->slug,
            'title' => $r->title,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'is_live' => $r->isLive(),
            'access' => $r->access,
            'access_label' => $r->accessLabel(),
            'scheduled_at' => $r->scheduled_at?->toIso8601String(),
            'ends_at' => $r->endsAt()?->toIso8601String(),
            'started_at' => $r->started_at?->toIso8601String(),
            'ended_at' => $r->ended_at?->toIso8601String(),
            'duration_minutes' => (int) $r->duration_minutes,
            'host' => $r->relationLoaded('host') && $r->host ? ['id' => $r->host->id, 'name' => $r->host->name] : null,
            'category' => $r->relationLoaded('category') && $r->category
                ? ['id' => $r->category->id, 'name' => $r->category->name, 'slug' => $r->category->slug] : null,
            'course' => $r->relationLoaded('course') && $r->course
                ? ['id' => $r->course->id, 'title' => $r->course->title, 'slug' => $r->course->slug] : null,
            'web_url' => route('learn.rooms.show', $r),
        ];
    }

    protected function progressPayload(LearningVideoProgress $p): array
    {
        return [
            'position_seconds' => (int) $p->position_seconds,
            'max_position_seconds' => (int) $p->max_position_seconds,
            'percent' => (int) $p->percent,
            'completed' => $p->isCompleted(),
            'completed_at' => $p->completed_at?->toIso8601String(),
            'last_watched_at' => $p->last_watched_at?->toIso8601String(),
        ];
    }

    /** Offer a resume point only for an unfinished lesson with a meaningful position. */
    protected function resumeAt(LearningVideo $video, ?LearningVideoProgress $progress): int
    {
        if (! $progress || $progress->isCompleted()) {
            return 0;
        }

        $position = (int) $progress->position_seconds;
        $duration = (int) ($video->duration_seconds ?: $progress->duration_seconds);

        return ($position < 5 || ($duration > 0 && $position >= $duration - 5)) ? 0 : $position;
    }

    protected function paginated(LengthAwarePaginator $page, callable $map): JsonResponse
    {
        return response()->json([
            'data' => $page->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'links' => [
                'next' => $page->nextPageUrl(),
                'prev' => $page->previousPageUrl(),
            ],
        ]);
    }

    /** 403 JSON (via AuthorizationException) for the Sanctum user. */
    protected function authorizeFor(User $user, string $ability, $model): void
    {
        Gate::forUser($user)->authorize($ability, $model);
    }

    protected function providerUnavailable(LearningRoom $room, User $user, string $details): JsonResponse
    {
        $message = $room->isManageableBy($user) || $user->hasPermission('rooms.manage')
            ? 'The live class provider is not configured: '.$details
            : 'Live classes are temporarily unavailable. Please try again later or contact the host.';

        return response()->json(['reason' => 'provider_unavailable', 'message' => $message], 503);
    }
}
