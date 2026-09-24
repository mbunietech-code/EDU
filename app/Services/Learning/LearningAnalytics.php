<?php

namespace App\Services\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomSession;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Headline numbers for the admin learning dashboard and the per-course
 * progress roll-ups used by the admin course / progress pages.
 *
 * Every query is portable (query builder, Carbon dates, COUNT(DISTINCT …)
 * only) so the same code runs on MySQL in production and SQLite in tests.
 * overview() caches plain arrays (never models) for five minutes.
 */
class LearningAnalytics
{
    public const CACHE_KEY = 'learning.analytics.overview';

    public const CACHE_SECONDS = 300;

    /** Days counted as "recent" for attendance and course activity. */
    public const RECENT_DAYS = 30;

    /**
     * Cached dashboard overview. "live_now" is always read fresh (it is one
     * cheap count and a stale value would be misleading).
     *
     * @return array<string,mixed>
     */
    public function overview(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $data = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->compute());
        $data['live_now'] = LearningRoom::query()->live()->count();

        return $data;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Uncached overview.
     *
     * @return array{
     *   generated_at:string, videos:int, videos_published:int, courses:int, courses_published:int,
     *   categories:int, live_now:int, upcoming:int, learners:int, video_views:int,
     *   completed_lessons:int, average_completion:int, attendance_30d:int, sessions_30d:int,
     *   attendance_per_session:float, top_lessons:list<array<string,mixed>>,
     *   active_courses:list<array<string,mixed>>, upcoming_sessions:list<array<string,mixed>>
     * }
     */
    public function compute(): array
    {
        $since = now()->subDays(self::RECENT_DAYS);

        $attendance30 = LearningRoomAttendance::query()->where('first_joined_at', '>=', $since)->count();
        $sessions30 = LearningRoomSession::query()->where('started_at', '>=', $since)->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'videos' => LearningVideo::query()->count(),
            'videos_published' => LearningVideo::query()->published()->count(),
            'courses' => LearningCourse::query()->count(),
            'courses_published' => LearningCourse::query()->published()->count(),
            'categories' => LearningCategory::query()->count(),
            'live_now' => LearningRoom::query()->live()->count(),
            'upcoming' => LearningRoom::query()->where('status', 'scheduled')->where('scheduled_at', '>=', now())->count(),
            'learners' => $this->learnerCount(),
            'video_views' => (int) LearningVideo::query()->sum('views'),
            'completed_lessons' => LearningVideoProgress::query()->whereNotNull('completed_at')->count(),
            'average_completion' => (int) round((float) LearningVideoProgress::query()->avg('percent')),
            'attendance_30d' => $attendance30,
            'sessions_30d' => $sessions30,
            'attendance_per_session' => $sessions30 > 0 ? round($attendance30 / $sessions30, 1) : 0.0,
            'top_lessons' => $this->topLessons(),
            'active_courses' => $this->activeCourses($since),
            'upcoming_sessions' => $this->upcomingSessions(),
        ];
    }

    /** Distinct users with any lesson progress or course enrolment. */
    public function learnerCount(): int
    {
        return User::query()
            ->where(fn (Builder $q) => $q->whereHas('learningProgress')->orWhereHas('learningEnrollments'))
            ->count();
    }

    /**
     * Adds per-course learner roll-ups as select columns:
     *  - published_videos_count  published, non-trashed lessons
     *  - enrollments_count / completed_enrollments_count
     *  - started_learners        distinct users with progress on the course's lessons
     *  - completed_lessons_count completed progress rows on published lessons
     * Use averageCompletion() on a row to turn these into a percentage.
     */
    public function withCourseStats(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        return $query
            ->withCount([
                'videos as published_videos_count' => fn (Builder $q) => $q->where('status', 'published'),
                'enrollments',
                'enrollments as completed_enrollments_count' => fn (Builder $q) => $q->whereNotNull('completed_at'),
            ])
            ->addSelect([
                'started_learners' => LearningVideoProgress::query()
                    ->selectRaw('count(distinct learning_video_progress.user_id)')
                    ->join('learning_videos', 'learning_videos.id', '=', 'learning_video_progress.learning_video_id')
                    ->whereColumn('learning_videos.learning_course_id', 'learning_courses.id')
                    ->whereNull('learning_videos.deleted_at'),
                'completed_lessons_count' => LearningVideoProgress::query()
                    ->selectRaw('count(*)')
                    ->join('learning_videos', 'learning_videos.id', '=', 'learning_video_progress.learning_video_id')
                    ->whereColumn('learning_videos.learning_course_id', 'learning_courses.id')
                    ->whereNull('learning_videos.deleted_at')
                    ->where('learning_videos.status', 'published')
                    ->whereNotNull('learning_video_progress.completed_at'),
            ]);
    }

    /**
     * Average completion (0-100) of a course row loaded through withCourseStats():
     * completed lessons ÷ (learners who started × published lessons).
     */
    public function averageCompletion(LearningCourse $course): int
    {
        $lessons = (int) ($course->published_videos_count ?? 0);
        $learners = (int) ($course->started_learners ?? 0);

        if ($lessons === 0 || $learners === 0) {
            return 0;
        }

        return (int) min(100, round(((int) $course->completed_lessons_count) / ($learners * $lessons) * 100));
    }

    /** Compact human duration for tables: "2h 05m", "14m", "40s", "—" for zero. */
    public static function duration(int|float|string|null $seconds): string
    {
        $seconds = (int) round((float) $seconds);
        if ($seconds <= 0) {
            return '—';
        }
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? sprintf('%dh %02dm', $hours, $minutes) : $minutes.'m';
    }

    // --- Internals -------------------------------------------------------
    /** @return list<array<string,mixed>> */
    private function topLessons(): array
    {
        return LearningVideo::query()
            ->with('course:id,title')
            ->withCount(['progress as completions_count' => fn (Builder $q) => $q->whereNotNull('completed_at')])
            ->where('views', '>', 0)
            ->orderByDesc('views')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (LearningVideo $v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'title' => $v->title,
                'status' => $v->status,
                'views' => (int) $v->views,
                'completions' => (int) $v->completions_count,
                'course' => $v->course?->title,
            ])
            ->values()
            ->all();
    }

    /**
     * Top five courses by lesson progress activity (progress rows touched)
     * since $since.
     *
     * @return list<array<string,mixed>>
     */
    private function activeCourses(\DateTimeInterface $since): array
    {
        $rows = LearningVideoProgress::query()
            ->join('learning_videos', 'learning_videos.id', '=', 'learning_video_progress.learning_video_id')
            ->join('learning_courses', 'learning_courses.id', '=', 'learning_videos.learning_course_id')
            ->whereNull('learning_videos.deleted_at')
            ->whereNull('learning_courses.deleted_at')
            ->where('learning_video_progress.last_watched_at', '>=', $since)
            ->groupBy('learning_videos.learning_course_id')
            ->selectRaw('learning_videos.learning_course_id as course_id, count(*) as activity, count(distinct learning_video_progress.user_id) as learners')
            ->orderByDesc('activity')
            ->orderBy('learning_videos.learning_course_id')
            ->limit(5)
            ->toBase()
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $courses = LearningCourse::query()
            ->with('category:id,name')
            ->whereIn('id', $rows->pluck('course_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->filter(fn ($row) => $courses->has((int) $row->course_id))
            ->map(function ($row) use ($courses) {
                $course = $courses->get((int) $row->course_id);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'status' => $course->status,
                    'category' => $course->category?->name,
                    'activity' => (int) $row->activity,
                    'learners' => (int) $row->learners,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function upcomingSessions(): array
    {
        return LearningRoom::query()
            ->upcoming()
            ->where('scheduled_at', '>=', now())
            ->with('host:id,name')
            ->limit(5)
            ->get()
            ->map(fn (LearningRoom $room) => [
                'id' => $room->id,
                'slug' => $room->slug,
                'title' => $room->title,
                'status' => $room->status,
                'scheduled_at' => $room->scheduled_at?->toIso8601String(),
                'duration_minutes' => (int) $room->duration_minutes,
                'host' => $room->host?->name,
            ])
            ->values()
            ->all();
    }
}
