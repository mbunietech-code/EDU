<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Services\Learning\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Course catalogue, course pages and self-enrolment (bound by slug).
 */
class CourseController extends Controller
{
    public const SORTS = ['latest' => 'Newest', 'popular' => 'Most enrolled', 'title' => 'Title A–Z'];

    public function index(Request $request, ProgressService $progress)
    {
        $user = $request->user();

        $filters = [
            'q' => Str::limit(trim((string) $request->query('q', '')), 100, ''),
            'category' => is_string($request->query('category')) ? $request->query('category') : null,
            'level' => array_key_exists((string) $request->query('level'), LearningCourse::LEVELS) ? (string) $request->query('level') : null,
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'latest',
            'mine' => $request->boolean('mine'),
        ];

        $categories = LearningCategory::query()
            ->whereHas('courses', fn ($q) => $q->published()->visibleTo($user))
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $activeCategory = $filters['category'] ? $categories->firstWhere('slug', $filters['category']) : null;

        $courses = LearningCourse::query()
            ->published()
            ->visibleTo($user)
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($user)])
            ->when($filters['category'], fn (Builder $q) => $q->where('learning_category_id', $activeCategory?->id ?? 0))
            ->when($filters['level'], fn (Builder $q, $level) => $q->where('level', $level))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $term)->orWhere('summary', 'like', $term));
            })
            ->when($filters['mine'], fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereIn('id', LearningEnrollment::query()->select('learning_course_id')->where('user_id', $user->id))
                ->orWhereIn('id', LearningVideo::query()
                    ->select('learning_course_id')
                    ->whereNotNull('learning_course_id')
                    ->whereIn('id', LearningVideoProgress::query()->select('learning_video_id')->where('user_id', $user->id)))))
            ->when($filters['sort'] === 'popular', fn (Builder $q) => $q->withCount('enrollments')->orderByDesc('enrollments_count'))
            ->when($filters['sort'] === 'title', fn (Builder $q) => $q->orderBy('title'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        // At most 12 per page; the service stays the single source of truth for roll-ups.
        $courseProgress = $courses->getCollection()
            ->mapWithKeys(fn (LearningCourse $c) => [$c->id => $progress->courseProgress($user, $c)]);

        return view('learn.courses.index', [
            'courses' => $courses,
            'courseProgress' => $courseProgress,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'filters' => $filters,
            'sorts' => self::SORTS,
            'levels' => LearningCourse::LEVELS,
        ]);
    }

    public function show(Request $request, LearningCourse $course, ProgressService $progress)
    {
        $this->authorize('view', $course);

        $user = $request->user();

        $course->load(['category:id,name,slug', 'instructor:id,name,can_teach']);

        $lessons = LearningVideo::query()
            ->where('learning_course_id', $course->id)
            ->published()
            ->visibleTo($user)
            ->with(['progress' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $topics = $course->topics()->get();
        $grouped = $lessons->groupBy(fn (LearningVideo $v) => (int) $v->learning_topic_id);
        $topicIds = $topics->modelKeys();

        $sections = $topics
            ->map(fn ($topic) => ['topic' => $topic, 'lessons' => $grouped->get($topic->id, collect())])
            ->filter(fn ($s) => $s['lessons']->isNotEmpty())
            ->values();

        // Lessons with no topic, or whose topic no longer belongs to this course.
        $otherLessons = $lessons->reject(fn (LearningVideo $v) => $v->learning_topic_id !== null
            && in_array((int) $v->learning_topic_id, $topicIds, true))->values();

        $enrollment = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->where('learning_course_id', $course->id)
            ->first();

        $upcomingRooms = LearningRoom::query()
            ->where('learning_course_id', $course->id)
            ->whereIn('status', ['scheduled', 'live'])
            ->visibleTo($user)
            ->with(['host:id,name', 'category:id,name'])
            ->orderByRaw("case when status = 'live' then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();

        return view('learn.courses.show', [
            'course' => $course,
            'progress' => $progress->courseProgress($user, $course),
            'sections' => $sections,
            'otherLessons' => $otherLessons,
            'lessonCount' => $lessons->count(),
            'totalSeconds' => (int) $lessons->sum('duration_seconds'),
            'enrollment' => $enrollment,
            'canEnroll' => $user->can('enroll', $course) && $course->isPublished() && $course->isOpen(),
            'upcomingRooms' => $upcomingRooms,
        ]);
    }

    public function enroll(Request $request, LearningCourse $course)
    {
        $this->authorize('view', $course);
        $this->authorize('enroll', $course);

        // Gate::before lets super admins through the policy: re-check the state rule.
        if (! $course->isPublished() || ! $course->isOpen()) {
            return back()->with('error', 'This course does not accept self-enrolment.');
        }

        $user = $request->user();

        $enrollment = LearningEnrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'learning_course_id' => $course->id],
            ['source' => 'self', 'enrolled_at' => now()],
        );

        if (! $enrollment->wasRecentlyCreated) {
            return back()->with('success', 'You are already enrolled in this course.');
        }

        ActivityLog::log('learning_course_enrolled', 'LearningCourse', $course->id, ['user_id' => $user->id, 'source' => 'self']);

        return redirect()->route('learn.courses.show', $course)
            ->with('success', 'You are enrolled in "'.$course->title.'".');
    }

    public function unenroll(Request $request, LearningCourse $course)
    {
        $user = $request->user();

        $enrollment = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->where('learning_course_id', $course->id)
            ->first();

        if (! $enrollment) {
            return back()->with('error', 'You are not enrolled in this course.');
        }

        if ($enrollment->source !== 'self') {
            return back()->with('error', 'An administrator enrolled you in this course, so only an administrator can remove the enrolment.');
        }

        $enrollment->delete();

        ActivityLog::log('learning_course_unenrolled', 'LearningCourse', $course->id, ['user_id' => $user->id]);

        // After leaving, an enrolled-only course may no longer be viewable.
        return ($user->can('view', $course)
                ? redirect()->route('learn.courses.show', $course)
                : redirect()->route('learn.courses.index'))
            ->with('success', 'You left "'.$course->title.'". Your lesson progress is kept.');
    }
}
