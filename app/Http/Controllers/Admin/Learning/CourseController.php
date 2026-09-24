<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LearningStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Courses (admin).
 */
class CourseController extends Controller
{
    private const PER_PAGE = 20;

    public const TABS = ['overview' => 'Overview', 'topics' => 'Topics', 'lessons' => 'Lessons', 'enrolments' => 'Enrolments'];

    public function __construct(
        protected LearningAnalytics $analytics,
        protected LearningStorage $storage,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('learning.view');

        $filters = [
            'category' => $request->integer('category') ?: null,
            'status' => in_array($request->query('status'), LearningCourse::STATUSES, true) ? (string) $request->query('status') : null,
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
        ];

        $query = LearningCourse::query()
            ->with(['category:id,name', 'instructor:id,name'])
            ->withCount('videos')
            ->when($filters['category'], fn (Builder $q, int $id) => $q->where('learning_category_id', $id))
            ->when($filters['status'], fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where('title', 'like', $like)->orWhere('summary', 'like', $like);
            }))
            ->orderBy('position')
            ->orderBy('title');

        $courses = $this->analytics->withCourseStats($query)->paginate(self::PER_PAGE)->withQueryString();
        $courses->getCollection()->each(fn (LearningCourse $c) => $c->setAttribute('average_completion', $this->analytics->averageCompletion($c)));

        return view('admin.learning.courses.index', [
            'courses' => $courses,
            'filters' => $filters,
            'categories' => LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('learning.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('learning.manage');

        $course = new LearningCourse([
            'learning_category_id' => $request->integer('category') ?: null,
        ]);

        return view('admin.learning.courses.form', $this->formData($course));
    }

    public function store(Request $request, LearningNotifier $notifier): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $this->validated($request);
        $actor = $request->user();

        $thumbnail = $request->file('thumbnail') ? $this->storage->storeThumbnail($request->file('thumbnail')) : null;

        try {
            $course = DB::transaction(function () use ($data, $actor, $thumbnail) {
                $course = LearningCourse::create(array_merge($data, [
                    'position' => $data['position'] ?? ((int) LearningCourse::query()->where('learning_category_id', $data['learning_category_id'])->max('position') + 1),
                    'thumbnail_path' => $thumbnail,
                    'published_at' => $data['status'] === 'published' ? now() : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]));

                ActivityLog::log('learning_course_created', 'LearningCourse', $course->id, [
                    'title' => $course->title,
                    'status' => $course->status,
                ]);

                return $course;
            });
        } catch (\Throwable $e) {
            $this->storage->deleteFile($this->storage->publicDisk(), $thumbnail);
            throw $e;
        }

        $this->analytics->forget();

        if ($course->isPublished() && $request->boolean('notify')) {
            $notifier->notifyNewCourse($course, $actor);
        }

        return redirect()->route('admin.learning.courses.show', $course)
            ->with('success', 'Course “'.$course->title.'” created.');
    }

    public function show(Request $request, LearningCourse $course, LearningDeletionService $deletions): View
    {
        Gate::authorize('learning.view');

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'overview';

        $course->load(['category:id,name,slug', 'instructor:id,name,email']);
        $stats = $this->analytics->withCourseStats(LearningCourse::query()->whereKey($course->id))
            ->withCount(['videos', 'topics'])
            ->withSum('videos as views_total', 'views')
            ->first();
        $stats->setAttribute('average_completion', $this->analytics->averageCompletion($stats));

        $data = [
            'course' => $course,
            'tab' => $tab,
            'tabs' => self::TABS,
            'stats' => $stats,
            'canManage' => Gate::allows('learning.manage'),
            'impact' => $deletions->impact($course),
        ];

        if ($tab === 'topics') {
            $data['topics'] = $course->topics()->withCount('videos')->get();
            $data['topicImpact'] = $data['topics']->mapWithKeys(fn ($t) => [$t->id => $deletions->impact($t)]);
        }

        if ($tab === 'lessons') {
            $videos = $course->videos()->with(['topic:id,title', 'instructor:id,name'])->get();
            $data['topics'] = $course->topics()->get(['id', 'title', 'position']);
            $data['videosByTopic'] = $videos->groupBy(fn (LearningVideo $v) => (int) $v->learning_topic_id);
        }

        if ($tab === 'enrolments') {
            $enrollments = $course->enrollments()
                ->with(['user:id,name,email,status', 'enroller:id,name'])
                ->orderByDesc('enrolled_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $data['enrollments'] = $enrollments;
            $data['enrollmentProgress'] = $this->enrollmentProgress($course, $enrollments->getCollection());
        }

        return view('admin.learning.courses.show', $data);
    }

    public function edit(Request $request, LearningCourse $course): View
    {
        Gate::authorize('learning.manage');

        return view('admin.learning.courses.form', $this->formData($course));
    }

    public function update(Request $request, LearningCourse $course, LearningNotifier $notifier): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $this->validated($request, $course);
        $actor = $request->user();
        $firstPublish = $data['status'] === 'published' && $course->published_at === null;
        $oldThumbnail = $course->thumbnail_path;

        $newThumbnail = $request->file('thumbnail') ? $this->storage->storeThumbnail($request->file('thumbnail')) : null;

        if ($newThumbnail !== null) {
            $data['thumbnail_path'] = $newThumbnail;
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail_path'] = null;
        }

        if ($firstPublish) {
            $data['published_at'] = now();
        }
        $data['position'] ??= (int) $course->position;
        $data['updated_by'] = $actor->id;

        try {
            DB::transaction(function () use ($course, $data) {
                $course->update($data);

                ActivityLog::log('learning_course_updated', 'LearningCourse', $course->id, [
                    'title' => $course->title,
                    'changes' => array_values(array_diff(array_keys($course->getChanges()), ['updated_at', 'updated_by'])),
                ]);
            });
        } catch (\Throwable $e) {
            $this->storage->deleteFile($this->storage->publicDisk(), $newThumbnail);
            throw $e;
        }

        if ($oldThumbnail && $oldThumbnail !== $course->thumbnail_path) {
            $this->storage->deleteFile($this->storage->publicDisk(), $oldThumbnail);
        }

        $this->analytics->forget();

        if ($firstPublish && $request->boolean('notify')) {
            $notifier->notifyNewCourse($course, $actor);
        }

        return redirect()->route('admin.learning.courses.show', $course)
            ->with('success', 'Course “'.$course->title.'” saved.');
    }

    public function destroy(Request $request, LearningCourse $course, LearningDeletionService $deletions): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'with_videos' => ['nullable', 'boolean'],
        ]);

        try {
            $deletions->deleteCourse($course, $data['reason'], (bool) ($data['with_videos'] ?? false));
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->analytics->forget();

        return redirect()->route('admin.learning.courses.index')
            ->with('success', 'Course “'.$course->title.'” moved to the trash.');
    }

    // --- Helpers ---------------------------------------------------------
    /** @return array<string,mixed> */
    private function formData(LearningCourse $course): array
    {
        $instructors = $this->eligibleInstructors()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Keep the current instructor selectable even if no longer eligible.
        if ($course->instructor_id && ! $instructors->contains('id', $course->instructor_id)) {
            $current = User::query()->find($course->instructor_id, ['id', 'name', 'email']);
            if ($current) {
                $instructors->prepend($current);
            }
        }

        return [
            'course' => $course,
            'categories' => LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
            'instructors' => $instructors,
        ];
    }

    /** Users who may be set as a course instructor: instructors (can_teach) and admins. */
    private function eligibleInstructors(): Builder
    {
        return User::query()->where(fn (Builder $q) => $q->where('can_teach', true)->orWhere('is_admin', true));
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?LearningCourse $course = null): array
    {
        $data = $request->validate([
            'learning_category_id' => ['required', 'integer', Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'level' => ['nullable', Rule::in(array_keys(LearningCourse::LEVELS))],
            'instructor_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($course) {
                if ($course && (int) $course->instructor_id === (int) $value) {
                    return; // unchanged — keep even if no longer eligible
                }
                if (! $this->eligibleInstructors()->whereKey((int) $value)->exists()) {
                    $fail('Choose an instructor (a member with instructor access, or an admin).');
                }
            }],
            'access' => ['required', Rule::in(array_keys(LearningCourse::ACCESS))],
            'status' => ['required', Rule::in(LearningCourse::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'thumbnail' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
        ]);

        return [
            'learning_category_id' => (int) $data['learning_category_id'],
            'title' => trim($data['title']),
            'summary' => filled($data['summary'] ?? null) ? trim($data['summary']) : null,
            'description' => filled($data['description'] ?? null) ? $data['description'] : null,
            'level' => $data['level'] ?? null,
            'instructor_id' => isset($data['instructor_id']) ? (int) $data['instructor_id'] : null,
            'access' => $data['access'],
            'status' => $data['status'],
            'position' => isset($data['position']) ? (int) $data['position'] : null,
        ];
    }

    /**
     * Completed-lesson percentage per enrolled user on this page, in two
     * queries (instead of ProgressService::courseProgress() per row).
     *
     * @param  Collection<int,\App\Models\LearningEnrollment>  $enrollments
     * @return array<int,array{completed:int,started:bool,percent:int}>
     */
    private function enrollmentProgress(LearningCourse $course, Collection $enrollments): array
    {
        $userIds = $enrollments->pluck('user_id')->unique()->values();
        if ($userIds->isEmpty()) {
            return [];
        }

        $lessonIds = LearningVideo::query()
            ->where('learning_course_id', $course->id)
            ->where('status', 'published')
            ->pluck('id');
        $total = $lessonIds->count();

        $rows = $lessonIds->isEmpty() ? collect() : LearningVideoProgress::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('learning_video_id', $lessonIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as started, sum(case when completed_at is not null then 1 else 0 end) as completed')
            ->toBase()
            ->get()
            ->keyBy('user_id');

        $out = [];
        foreach ($userIds as $id) {
            $row = $rows->get($id);
            $completed = (int) ($row->completed ?? 0);
            $out[(int) $id] = [
                'completed' => $completed,
                'total' => $total,
                'started' => $row !== null,
                'percent' => $total > 0 ? (int) min(100, round($completed / $total * 100)) : 0,
            ];
        }

        return $out;
    }
}
