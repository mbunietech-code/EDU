<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Search across the courses, lessons, topics, rooms and instructors the
 * viewer may see. Every query goes through the models' visibleTo() scopes;
 * nothing a learner cannot open is ever listed (or counted).
 */
class SearchController extends Controller
{
    public const TYPES = [
        'all' => 'All',
        'videos' => 'Lessons',
        'courses' => 'Courses',
        'categories' => 'Categories',
        'topics' => 'Topics',
        'rooms' => 'Live classes',
        'instructors' => 'Instructors',
    ];

    public const DURATIONS = ['short' => 'Under 10 min', 'medium' => '10–30 min', 'long' => 'Over 30 min'];

    public const MODES = ['live' => 'Live classes', 'recorded' => 'Recorded'];

    public const STATUSES = ['completed' => 'Completed', 'in_progress' => 'In progress', 'not_started' => 'Not started'];

    public const SORTS = ['latest' => 'Newest', 'popular' => 'Most popular', 'title' => 'Title A–Z'];

    private const GROUP_SIZE = 4;

    private const PER_PAGE = 20;

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $categoryOptions = LearningCategory::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('courses', fn ($c) => $c->published()->visibleTo($user))
                ->orWhereHas('videos', fn ($v) => $v->published()->visibleTo($user)))
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $courseOptions = LearningCourse::query()
            ->published()
            ->visibleTo($user)
            ->orderBy('title')
            ->limit(200)
            ->get(['id', 'title', 'slug', 'learning_category_id']);

        $scope = [
            'category' => $filters['category'] ? $categoryOptions->firstWhere('slug', $filters['category']) : null,
            'course' => $filters['course'] ? $courseOptions->firstWhere('slug', $filters['course']) : null,
        ];

        $hasFilters = $filters['category'] || $filters['course'] || $filters['duration'] || $filters['mode'] || $filters['status'];
        $idle = $filters['type'] === 'all' && $filters['q'] === '' && ! $hasFilters;

        $groups = [];
        $results = null;

        if (! $idle) {
            if ($filters['type'] === 'all') {
                foreach (array_keys(self::TYPES) as $type) {
                    if ($type === 'all') {
                        continue;
                    }
                    $query = $this->query($type, $user, $filters, $scope);
                    $total = (clone $query)->count();
                    $groups[$type] = [
                        'label' => self::TYPES[$type],
                        'total' => $total,
                        'items' => $total > 0 ? $this->sorted($type, $query, $filters['sort'])->limit(self::GROUP_SIZE)->get() : collect(),
                    ];
                }
            } else {
                $results = $this->sorted($filters['type'], $this->query($filters['type'], $user, $filters, $scope), $filters['sort'])
                    ->paginate(self::PER_PAGE)
                    ->withQueryString();
            }
        }

        return view('learn.search', [
            'filters' => $filters,
            'idle' => $idle,
            'hasFilters' => $hasFilters,
            'groups' => $groups,
            'results' => $results,
            'totalFound' => $results ? $results->total() : array_sum(array_column($groups, 'total')),
            'types' => self::TYPES,
            'durations' => self::DURATIONS,
            'modes' => self::MODES,
            'statuses' => self::STATUSES,
            'sorts' => self::SORTS,
            'categoryOptions' => $categoryOptions,
            'courseOptions' => $courseOptions,
            'seeAll' => fn (string $type) => route('learn.search', array_filter(
                array_merge($filters, ['type' => $type]),
                fn ($v, $k) => $v !== null && $v !== '' && ! ($k === 'sort' && $v === 'latest'),
                ARRAY_FILTER_USE_BOTH,
            )),
        ]);
    }

    /** Unknown values fall back to defaults instead of erroring (links get shared). */
    protected function filters(Request $request): array
    {
        $pick = fn (string $key, array $allowed, $default = null) => array_key_exists((string) $request->query($key), $allowed)
            ? (string) $request->query($key)
            : $default;

        return [
            'q' => Str::limit(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 100, ''),
            'type' => $pick('type', self::TYPES, 'all'),
            'category' => is_string($request->query('category')) && $request->query('category') !== '' ? Str::limit($request->query('category'), 255, '') : null,
            'course' => is_string($request->query('course')) && $request->query('course') !== '' ? Str::limit($request->query('course'), 255, '') : null,
            'duration' => $pick('duration', self::DURATIONS),
            'mode' => $pick('mode', self::MODES),
            'status' => $pick('status', self::STATUSES),
            'sort' => $pick('sort', self::SORTS, 'latest'),
        ];
    }

    /**
     * Visible-only base query for one result type with the filters that apply
     * to it. A filter that names an unknown / invisible category or course
     * matches nothing (id 0) rather than being ignored.
     */
    protected function query(string $type, User $user, array $f, array $scope): Builder
    {
        $term = $f['q'] !== '' ? '%'.addcslashes($f['q'], '%_\\').'%' : null;
        $categoryId = $f['category'] ? ($scope['category']?->id ?? 0) : null;
        $courseId = $f['course'] ? ($scope['course']?->id ?? 0) : null;
        $mine = fn ($p) => $p->where('user_id', $user->id);

        return match ($type) {
            'videos' => LearningVideo::query()
                ->published()
                ->visibleTo($user)
                ->with(['category:id,name,slug', 'instructor:id,name', 'progress' => $mine])
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $term)->orWhere('description', 'like', $term)))
                ->when($categoryId !== null, fn (Builder $q) => $q->where('learning_category_id', $categoryId))
                ->when($courseId !== null, fn (Builder $q) => $q->where('learning_course_id', $courseId))
                ->when($f['duration'] === 'short', fn (Builder $q) => $q->where('duration_seconds', '<', 600))
                ->when($f['duration'] === 'medium', fn (Builder $q) => $q->whereBetween('duration_seconds', [600, 1800]))
                ->when($f['duration'] === 'long', fn (Builder $q) => $q->where('duration_seconds', '>', 1800))
                ->when($f['status'] === 'not_started', fn (Builder $q) => $q->whereDoesntHave('progress', $mine))
                ->when($f['status'] === 'in_progress', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNull('completed_at')))
                ->when($f['status'] === 'completed', fn (Builder $q) => $q->whereHas('progress', fn ($p) => $mine($p)->whereNotNull('completed_at')))
                // Lessons are recorded content: a "live" search has none.
                ->when($f['mode'] === 'live', fn (Builder $q) => $q->whereRaw('1 = 0')),

            'courses' => LearningCourse::query()
                ->published()
                ->visibleTo($user)
                ->with(['category:id,name,slug', 'instructor:id,name'])
                ->withCount(['videos' => fn ($v) => $v->published()->visibleTo($user)])
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $term)->orWhere('summary', 'like', $term)->orWhere('description', 'like', $term)))
                ->when($categoryId !== null, fn (Builder $q) => $q->where('learning_category_id', $categoryId))
                ->when($courseId !== null, fn (Builder $q) => $q->whereKey($courseId))
                ->when($f['status'] !== null, fn (Builder $q) => $this->courseStatus($q, $user, $f['status'])),

            'categories' => LearningCategory::query()
                ->where(fn (Builder $q) => $q
                    ->whereHas('courses', fn ($c) => $c->published()->visibleTo($user))
                    ->orWhereHas('videos', fn ($v) => $v->published()->visibleTo($user)))
                ->withCount([
                    'courses' => fn ($c) => $c->published()->visibleTo($user),
                    'videos' => fn ($v) => $v->published()->visibleTo($user),
                ])
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $term)->orWhere('description', 'like', $term)))
                ->when($categoryId !== null, fn (Builder $q) => $q->whereKey($categoryId)),

            // Topics only from courses the viewer may open.
            'topics' => LearningTopic::query()
                ->whereHas('course', fn (Builder $c) => $c->published()->visibleTo($user)
                    ->when($categoryId !== null, fn (Builder $c) => $c->where('learning_category_id', $categoryId)))
                ->with(['course:id,title,slug,learning_category_id', 'course.category:id,name'])
                ->withCount(['videos' => fn ($v) => $v->published()->visibleTo($user)])
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $term)->orWhere('description', 'like', $term)))
                ->when($courseId !== null, fn (Builder $q) => $q->where('learning_course_id', $courseId)),

            'rooms' => LearningRoom::query()
                ->visibleTo($user)
                ->where('status', '!=', 'draft')
                ->with(['host:id,name', 'category:id,name'])
                ->when($term, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $term)->orWhere('description', 'like', $term)))
                ->when($categoryId !== null, fn (Builder $q) => $q->where('learning_category_id', $categoryId))
                ->when($courseId !== null, fn (Builder $q) => $q->where('learning_course_id', $courseId))
                ->when($f['mode'] === 'live', fn (Builder $q) => $q->whereIn('status', ['live', 'scheduled']))
                ->when($f['mode'] === 'recorded', fn (Builder $q) => $q->whereHas('recordings', fn ($r) => $r
                    ->where('status', 'ready')->where('is_shared', true)->whereNotNull('path'))),

            // Never select the email: id, name and join date only.
            'instructors' => User::query()
                ->select(['id', 'name', 'can_teach', 'created_at'])
                ->where('status', 'active')
                ->where(fn (Builder $q) => $q
                    ->where('can_teach', true)
                    ->orWhereIn('id', LearningRoom::query()->visibleTo($user)->where('status', '!=', 'draft')
                        ->whereNotNull('host_id')->select('host_id'))
                    ->orWhereIn('id', LearningCourse::query()->published()->visibleTo($user)
                        ->whereNotNull('instructor_id')->select('instructor_id')))
                ->withCount([
                    'taughtCourses as courses_count' => fn ($c) => $c->published()->visibleTo($user),
                    'teachingVideos as lessons_count' => fn ($v) => $v->published()->visibleTo($user),
                ])
                ->when($term, fn (Builder $q) => $q->where('name', 'like', $term)),
        };
    }

    protected function courseStatus(Builder $q, User $user, string $status): Builder
    {
        $enrolled = LearningEnrollment::query()->select('learning_course_id')->where('user_id', $user->id);
        $completedEnrolment = LearningEnrollment::query()->select('learning_course_id')
            ->where('user_id', $user->id)->whereNotNull('completed_at');
        $started = LearningVideo::query()->select('learning_course_id')->whereNotNull('learning_course_id')
            ->whereIn('id', LearningVideoProgress::query()->select('learning_video_id')->where('user_id', $user->id));

        return match ($status) {
            'completed' => $q->whereIn('id', $completedEnrolment),
            'in_progress' => $q->where(fn (Builder $w) => $w->whereIn('id', $enrolled)->orWhereIn('id', $started))
                ->whereNotIn('id', $completedEnrolment),
            default => $q->whereNotIn('id', $enrolled)->whereNotIn('id', $started),
        };
    }

    protected function sorted(string $type, Builder $q, string $sort): Builder
    {
        return match ($type) {
            'videos' => match ($sort) {
                'popular' => $q->orderByDesc('views')->orderByDesc('id'),
                'title' => $q->orderBy('title')->orderBy('id'),
                default => $q->orderByDesc('published_at')->orderByDesc('id'),
            },
            'courses' => match ($sort) {
                'popular' => $q->withCount('enrollments')->orderByDesc('enrollments_count')->orderByDesc('id'),
                'title' => $q->orderBy('title')->orderBy('id'),
                default => $q->orderByDesc('published_at')->orderByDesc('id'),
            },
            'categories' => match ($sort) {
                'popular' => $q->orderByDesc('courses_count')->orderBy('name'),
                'title' => $q->orderBy('name'),
                default => $q->orderBy('position')->orderBy('name'),
            },
            'topics' => match ($sort) {
                'popular' => $q->orderByDesc('videos_count')->orderBy('title'),
                'title' => $q->orderBy('title')->orderBy('id'),
                default => $q->orderByDesc('id'),
            },
            'rooms' => match ($sort) {
                'popular' => $q->withCount('attendances')->orderByDesc('attendances_count')->orderByDesc('id'),
                'title' => $q->orderBy('title')->orderBy('id'),
                default => $q->orderByRaw("case when status = 'live' then 0 else 1 end")
                    ->orderByDesc('scheduled_at')->orderByDesc('id'),
            },
            'instructors' => match ($sort) {
                'popular' => $q->orderByDesc('courses_count')->orderByDesc('lessons_count')->orderBy('name'),
                'latest' => $q->orderByDesc('created_at')->orderByDesc('id'),
                default => $q->orderBy('name')->orderBy('id'),
            },
        };
    }
}
