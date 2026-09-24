<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Learner home ("My Learning") and the personal progress page.
 */
class DashboardController extends Controller
{
    public function __construct(protected ProgressService $progress)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $liveRooms = LearningRoom::query()
            ->live()
            ->visibleTo($user)
            ->with(['host:id,name', 'category:id,name,slug'])
            ->orderByDesc('started_at')
            ->limit(6)
            ->get();

        $upcomingRooms = LearningRoom::query()
            ->upcoming()
            ->visibleTo($user)
            ->with(['host:id,name', 'category:id,name,slug'])
            ->limit(6)
            ->get();

        $myCourses = static::myCoursesQuery($user)
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($user)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $courseProgress = $myCourses->mapWithKeys(fn (LearningCourse $c) => [$c->id => $this->progress->courseProgress($user, $c)]);

        $categories = LearningCategory::query()
            ->where(fn (Builder $q) => $q
                ->whereHas('courses', fn ($c) => $c->published()->visibleTo($user))
                ->orWhereHas('videos', fn ($v) => $v->published()->visibleTo($user)))
            ->withCount([
                'courses' => fn ($c) => $c->published()->visibleTo($user),
                'videos' => fn ($v) => $v->published()->visibleTo($user),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'description']);

        return view('learn.dashboard', [
            'liveRooms' => $liveRooms,
            'continueWatching' => $this->progress->continueWatching($user, 6),
            'myCourses' => $myCourses,
            'courseProgress' => $courseProgress,
            'upcomingRooms' => $upcomingRooms,
            'recentlyWatched' => $this->progress->recentlyWatched($user, 8),
            'completedLessons' => $this->progress->completedLessons($user, 8),
            'categories' => $categories,
            'stats' => $this->progress->stats($user),
        ]);
    }

    public function progress(Request $request)
    {
        $user = $request->user();

        $courses = static::myCoursesQuery($user)
            ->with(['category:id,name,slug'])
            ->orderBy('title')
            ->paginate(12, ['*'], 'courses')
            ->withQueryString();

        $courseProgress = $courses->getCollection()
            ->mapWithKeys(fn (LearningCourse $c) => [$c->id => $this->progress->courseProgress($user, $c)]);

        $enrollments = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('learning_course_id', $courses->getCollection()->modelKeys() ?: [0])
            ->get()
            ->keyBy('learning_course_id');

        $completed = LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->whereHas('video', fn (Builder $v) => $v->published()->visibleTo($user))
            ->with([
                'video:id,title,slug,learning_category_id,learning_course_id,duration_seconds',
                'video.category:id,name',
                'video.course:id,title,slug',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'completed')
            ->withQueryString();

        $attendance = LearningRoomAttendance::query()
            ->where('user_id', $user->id)
            ->whereHas('room')
            ->with([
                'room:id,title,slug,status,host_id,learning_category_id',
                'room.host:id,name',
                'session:id,started_at,ended_at',
            ])
            ->orderByDesc('first_joined_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'attendance')
            ->withQueryString();

        // One query to know which of those rooms the viewer may still open.
        $roomIds = $attendance->getCollection()->pluck('learning_room_id')->unique()->values()->all();
        $visibleRoomIds = $roomIds === [] ? collect() : LearningRoom::query()
            ->visibleTo($user)
            ->whereIn('id', $roomIds)
            ->pluck('id');

        $attendedSeconds = (int) LearningRoomAttendance::query()
            ->where('user_id', $user->id)
            ->whereHas('room')
            ->sum('total_seconds');

        return view('learn.progress', [
            'stats' => $this->progress->stats($user),
            'courses' => $courses,
            'courseProgress' => $courseProgress,
            'enrollments' => $enrollments,
            'completed' => $completed,
            'attendance' => $attendance,
            'visibleRoomIds' => $visibleRoomIds->map(fn ($id) => (int) $id)->all(),
            'sessionsAttended' => $attendance->total(),
            'attendedSeconds' => $attendedSeconds,
        ]);
    }

    /**
     * Published courses the user may see and has enrolled in or started
     * (watched at least one of its lessons).
     */
    public static function myCoursesQuery(User $user): Builder
    {
        return LearningCourse::query()
            ->published()
            ->visibleTo($user)
            ->where(fn (Builder $w) => $w
                ->whereIn('id', LearningEnrollment::query()->select('learning_course_id')->where('user_id', $user->id))
                ->orWhereIn('id', LearningVideo::query()
                    ->select('learning_course_id')
                    ->whereNotNull('learning_course_id')
                    ->whereIn('id', LearningVideoProgress::query()->select('learning_video_id')->where('user_id', $user->id))));
    }

    /** "1 h 05 min" / "12 min" / "45 s" — for watch time and attendance. */
    public static function durationText(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return $seconds.' s';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? sprintf('%d h %02d min', $h, $m) : $m.' min';
    }
}
