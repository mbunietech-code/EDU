<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearningCourse;
use App\Models\LearningRoomAttendance;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Services\Learning\LearningAnalytics;
use App\Services\Learning\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Learner progress (?tab=learners|courses) and per-learner detail.
 */
class ProgressController extends Controller
{
    private const PER_PAGE = 20;

    public const TABS = ['learners' => 'Learners', 'courses' => 'Courses'];

    public function index(Request $request, LearningAnalytics $analytics): View
    {
        Gate::authorize('learning.view');

        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'learners';
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $like = '%'.addcslashes($search, '%_\\').'%';

        $data = ['tab' => $tab, 'tabs' => self::TABS, 'search' => $search];

        if ($tab === 'courses') {
            $query = LearningCourse::query()
                ->with('category:id,name')
                ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', $like))
                ->orderBy('title');

            $courses = $analytics->withCourseStats($query)->paginate(self::PER_PAGE)->withQueryString();
            $courses->getCollection()->each(fn (LearningCourse $c) => $c->setAttribute('average_completion', $analytics->averageCompletion($c)));
            $data['courses'] = $courses;
        } else {
            $data['learners'] = User::query()
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
                ->paginate(self::PER_PAGE)
                ->withQueryString();
        }

        return view('admin.learning.progress.index', $data);
    }

    public function show(Request $request, User $user, ProgressService $progress): View
    {
        Gate::authorize('learning.view');

        $stats = $progress->stats($user);

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
            ->map(fn (LearningCourse $course) => [
                'course' => $course,
                'progress' => $progress->courseProgress($user, $course),
                'enrollment' => $enrollments->get($course->id),
            ]);

        $lessons = LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('video')
            ->with(['video:id,title,slug,learning_course_id,duration_seconds,status', 'video.course:id,title'])
            ->orderByDesc('last_watched_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'lessons')
            ->withQueryString();

        $attendance = LearningRoomAttendance::query()
            ->where('user_id', $user->id)
            ->with([
                'room' => fn ($q) => $q->withTrashed()->select(['id', 'title', 'slug', 'deleted_at']),
                'session:id,started_at,ended_at',
            ])
            ->orderByDesc('first_joined_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'attendance')
            ->withQueryString();

        return view('admin.learning.progress.show', compact('user', 'stats', 'courses', 'lessons', 'attendance'));
    }
}
