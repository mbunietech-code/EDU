<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningVideo;
use App\Models\LearningVideoComment;
use App\Models\LearningVideoProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Video lesson listing and the lesson player page (bound by slug).
 */
class VideoController extends Controller
{
    public const SORTS = ['latest' => 'Newest', 'popular' => 'Most viewed', 'title' => 'Title A–Z', 'duration' => 'Shortest first'];

    public const DURATIONS = ['short' => 'Under 10 min', 'medium' => '10–30 min', 'long' => 'Over 30 min'];

    public const PROGRESS = ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed'];

    public function index(Request $request)
    {
        $user = $request->user();

        $filters = [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'category' => is_string($request->query('category')) ? $request->query('category') : null,
            'course' => is_string($request->query('course')) ? $request->query('course') : null,
            'duration' => array_key_exists((string) $request->query('duration'), self::DURATIONS) ? (string) $request->query('duration') : null,
            'status' => array_key_exists((string) $request->query('status'), self::PROGRESS) ? (string) $request->query('status') : null,
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'latest',
        ];

        $categories = LearningCategory::query()
            ->whereHas('videos', fn ($q) => $q->published()->visibleTo($user))
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $activeCategory = $filters['category'] ? $categories->firstWhere('slug', $filters['category']) : null;

        $courses = LearningCourse::query()
            ->published()
            ->visibleTo($user)
            ->whereHas('videos', fn ($q) => $q->published()->visibleTo($user))
            ->when($activeCategory, fn (Builder $q, $c) => $q->where('learning_category_id', $c->id))
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'learning_category_id']);

        $activeCourse = $filters['course'] ? $courses->firstWhere('slug', $filters['course']) : null;

        $mine = fn ($p) => $p->where('user_id', $user->id);

        $videos = LearningVideo::query()
            ->published()
            ->visibleTo($user)
            ->with([
                'category:id,name,slug',
                'instructor:id,name',
                'progress' => $mine,
            ])
            ->when($filters['category'], fn (Builder $q) => $q->where('learning_category_id', $activeCategory?->id ?? 0))
            ->when($filters['course'], fn (Builder $q) => $q->where('learning_course_id', $activeCourse?->id ?? 0))
            ->when($filters['duration'] === 'short', fn (Builder $q) => $q->where('duration_seconds', '<', 600))
            ->when($filters['duration'] === 'medium', fn (Builder $q) => $q->whereBetween('duration_seconds', [600, 1800]))
            ->when($filters['duration'] === 'long', fn (Builder $q) => $q->where('duration_seconds', '>', 1800))
            ->when($filters['status'] === 'not_started', fn (Builder $q) => $q->whereDoesntHave('progress', $mine))
            ->when($filters['status'] === 'in_progress', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNull('completed_at')))
            ->when($filters['status'] === 'completed', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNotNull('completed_at')))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->when($filters['sort'] === 'popular', fn (Builder $q) => $q->orderByDesc('views'))
            ->when($filters['sort'] === 'title', fn (Builder $q) => $q->orderBy('title'))
            ->when($filters['sort'] === 'duration', fn (Builder $q) => $q
                ->orderByRaw('case when duration_seconds is null then 1 else 0 end')
                ->orderBy('duration_seconds'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('learn.videos.index', [
            'videos' => $videos,
            'categories' => $categories,
            'courses' => $courses,
            'activeCategory' => $activeCategory,
            'activeCourse' => $activeCourse,
            'filters' => $filters,
            'sorts' => self::SORTS,
            'durations' => self::DURATIONS,
            'statuses' => self::PROGRESS,
            'filtered' => $filters['q'] !== '' || $filters['category'] || $filters['course'] || $filters['duration'] || $filters['status'],
        ]);
    }

    public function show(Request $request, LearningVideo $video)
    {
        $user = $request->user();

        if (Gate::denies('view', $video)) {
            return $this->locked($user, $video);
        }

        $video->load([
            'category:id,name,slug',
            'course' => fn ($q) => $q->withTrashed(),
            'course.category:id,name,slug',
            'topic:id,learning_course_id,title',
            'instructor:id,name,can_teach',
            'renditions',
            'resources',
        ]);

        $course = $video->course && ! $video->course->trashed() ? $video->course : null;
        $canManage = Gate::allows('update', $video);
        // Streams need a published lesson unless the viewer manages it (MediaController::stream).
        $canPlay = $video->hasFile() && ($video->isPublished() || $canManage);

        $progress = $video->progressFor($user);

        $playlist = $course && Gate::allows('view', $course) ? $this->playlist($user, $course) : collect();
        $flat = $playlist->flatMap(fn ($section) => $section['lessons'])->values();
        $index = $flat->search(fn (LearningVideo $v) => $v->id === $video->id);
        $next = $index !== false ? $flat->get($index + 1) : null;
        $previous = $index !== false && $index > 0 ? $flat->get($index - 1) : null;

        $related = $this->related($user, $video, $course);

        $comments = LearningVideoComment::query()
            ->where('learning_video_id', $video->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name',
                'replies' => fn ($q) => $q->with('user:id,name'),
            ])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'comments')
            ->withQueryString()
            ->fragment('comments');

        $preview = $this->previewReasons($video, $course);

        $config = $canPlay ? [
            'progressUrl' => route('learn.videos.progress', $video),
            'completeUrl' => route('learn.videos.complete', $video),
            'sources' => $this->sources($video),
            'resumeAt' => $this->resumeAt($video, $progress),
            'completed' => (bool) $progress?->isCompleted(),
            'percent' => (int) ($progress?->percent ?? 0),
            'durationHint' => $video->duration_seconds,
            'nextUrl' => $next ? route('learn.videos.show', $next) : null,
            'nextTitle' => $next?->title,
            'csrf' => csrf_token(),
        ] : null;

        return view('learn.videos.show', [
            'video' => $video,
            'course' => $course,
            'progress' => $progress,
            'canManage' => $canManage,
            'canPlay' => $canPlay,
            'config' => $config,
            'playlist' => $playlist,
            'next' => $next,
            'previous' => $previous,
            'related' => $related,
            'comments' => $comments,
            'commentCount' => LearningVideoComment::query()->where('learning_video_id', $video->id)->count(),
            'canModerateComments' => $user->hasPermission('learning.manage') || $video->isOwnedBy($user),
            'preview' => $preview,
        ]);
    }

    // --- Internals -----------------------------------------------------

    /**
     * A lesson the viewer may not open. A published "same as course" lesson of
     * a live, published course gets a friendly locked page (still HTTP 403):
     * with an Enrol button when the course is open, otherwise a note that
     * enrolment is arranged by an administrator — without revealing any
     * titles of a course the viewer cannot see. Everything else is a plain 403.
     */
    private function locked(User $user, LearningVideo $video)
    {
        $course = $video->learning_course_id ? $video->course : null; // SoftDeletes: trashed courses resolve to null

        abort_unless(
            $course && $video->isPublished() && $video->visibility === 'course' && $course->isPublished(),
            403,
        );

        $canEnroll = $course->isOpen() && Gate::allows('enroll', $course);

        return response()->view('learn.videos.locked', [
            'video' => $video,
            'course' => $canEnroll ? $course : null,
            'canEnroll' => $canEnroll,
        ], 403);
    }

    /** Why this page is a staff/instructor preview rather than what learners see. */
    private function previewReasons(LearningVideo $video, ?LearningCourse $course): array
    {
        $reasons = [];

        if (! $video->isPublished()) {
            $reasons[] = 'This lesson is a draft.';
        }
        if ($video->visibility === 'private') {
            $reasons[] = 'This lesson is private (staff & instructor only).';
        }
        if ($video->learning_course_id && ! $course) {
            $reasons[] = 'Its course is in the trash.';
        } elseif ($course && ! $course->isPublished()) {
            $reasons[] = 'Its course is not published yet.';
        }

        return $reasons;
    }

    /**
     * The course playlist: topics in order with their visible, published
     * lessons, then the lessons without a topic under "Other lessons".
     *
     * @return Collection<int,array{title:string,lessons:Collection}>
     */
    private function playlist(User $user, LearningCourse $course): Collection
    {
        $lessons = LearningVideo::query()
            ->where('learning_course_id', $course->id)
            ->published()
            ->visibleTo($user)
            ->with(['progress' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'learning_course_id', 'learning_topic_id', 'duration_seconds', 'position']);

        $topics = $course->topics()->get(['id', 'learning_course_id', 'title', 'position']);
        $grouped = $lessons->groupBy(fn (LearningVideo $v) => (int) $v->learning_topic_id);
        $topicIds = $topics->modelKeys();

        $sections = $topics
            ->map(fn ($t) => ['title' => $t->title, 'lessons' => $grouped->get($t->id, collect())->values()])
            ->filter(fn ($s) => $s['lessons']->isNotEmpty())
            ->values();

        $other = $lessons->reject(fn (LearningVideo $v) => $v->learning_topic_id !== null
            && in_array((int) $v->learning_topic_id, $topicIds, true))->values();

        if ($other->isNotEmpty()) {
            $sections->push(['title' => $sections->isEmpty() ? 'Lessons' : 'Other lessons', 'lessons' => $other]);
        }

        return $sections;
    }

    /** Up to 6 other lessons: same course first, else same category. */
    private function related(User $user, LearningVideo $video, ?LearningCourse $course): Collection
    {
        $base = fn () => LearningVideo::query()
            ->published()
            ->visibleTo($user)
            ->whereKeyNot($video->id)
            ->with([
                'category:id,name,slug',
                'instructor:id,name',
                'progress' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(6);

        $related = $course
            ? $base()->where('learning_course_id', $course->id)->get()
            : collect();

        if ($related->isEmpty()) {
            $related = $base()->where('learning_category_id', $video->learning_category_id)->get();
        }

        return $related;
    }

    /** Original file first, then the extra renditions (highest first). */
    private function sources(LearningVideo $video): array
    {
        $sources = [[
            'id' => 'original',
            'label' => $video->sourceQualityLabel().' (original)',
            'url' => route('learn.videos.stream', $video),
            'height' => $video->height,
            'type' => $video->mime ?: 'video/mp4',
        ]];

        foreach ($video->renditions as $rendition) {
            $sources[] = [
                'id' => $rendition->id,
                'label' => $rendition->quality,
                'url' => route('learn.videos.stream', [$video, $rendition]),
                'height' => $rendition->height,
                'type' => $rendition->mime ?: 'video/mp4',
            ];
        }

        return $sources;
    }

    /** Offer "Continue from …" only for an unfinished lesson with a meaningful position. */
    private function resumeAt(LearningVideo $video, ?LearningVideoProgress $progress): int
    {
        if (! $progress || $progress->isCompleted()) {
            return 0;
        }

        $position = (int) $progress->position_seconds;
        $duration = (int) ($video->duration_seconds ?: $progress->duration_seconds);

        if ($position < 5 || ($duration > 0 && $position >= $duration - 5)) {
            return 0;
        }

        return $position;
    }
}
