<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-learner watch progress: resume points, completion and course roll-ups.
 * Fed by the player's periodic beacons (learn.videos.progress).
 */
class ProgressService
{
    public const EVENTS = ['start', 'tick', 'pause', 'seek', 'ended'];

    /** Longest credible gap between two beacons; bigger deltas are clamped. */
    private const MAX_WATCHED_DELTA = 60;

    /** Upper bound for a client-reported duration (24 h). */
    private const MAX_DURATION = 86400;

    /**
     * Record a player event.
     *
     * $event: start | tick | pause | seek | ended. "start" bumps play_count and
     * the video's views (increment query). watched_seconds grows by
     * min(max(0, $watchedDelta), 60). Position is clamped to 0..duration and
     * max_position_seconds only moves forward. The video's duration is trusted;
     * when it is unknown a client duration of 1..86400 s is persisted on the
     * video. percent = round(max_position / duration * 100), capped at 100.
     * completed_at is set once percent reaches config('learning.completion_threshold')
     * or on "ended" and is never cleared here; completing a lesson also syncs the
     * enrolment's completed_at when the whole course is done.
     */
    public function record(User $user, LearningVideo $video, int $position, ?int $duration, string $event, int $watchedDelta = 0): LearningVideoProgress
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException("Unknown player event [{$event}].");
        }

        $total = $this->resolveDuration($video, $duration);

        [$progress, $justCompleted] = DB::transaction(function () use ($user, $video, $position, $total, $event, $watchedDelta) {
            $progress = $this->lockedRow($user, $video);

            $position = max(0, $position);
            $position = $total !== null ? min($position, $total) : min($position, self::MAX_DURATION);

            $progress->position_seconds = $position;
            $progress->max_position_seconds = max((int) $progress->max_position_seconds, $position);
            $progress->watched_seconds = (int) $progress->watched_seconds + min(max(0, $watchedDelta), self::MAX_WATCHED_DELTA);
            $progress->duration_seconds = $total;
            $progress->last_watched_at = now();

            if ($total !== null && $total > 0) {
                $progress->percent = min(100, (int) round($progress->max_position_seconds / $total * 100));
            }

            if ($event === 'start') {
                $progress->play_count = (int) $progress->play_count + 1;
                $this->bumpViews($video);
            }

            $justCompleted = false;
            if ($progress->completed_at === null
                && ($event === 'ended' || ($total !== null && $progress->percent >= $this->threshold()))) {
                $progress->completed_at = now();
                $justCompleted = true;
            }

            $progress->save();

            return [$progress, $justCompleted];
        });

        if ($justCompleted) {
            $this->syncEnrollment($user, $video);
        }

        return $progress;
    }

    /**
     * Manually mark a lesson complete / not complete (also syncs the enrolment).
     * Un-completing restarts the lesson (position and furthest point back to
     * 0) — otherwise the next beacon past the threshold would complete it again.
     */
    public function setCompleted(User $user, LearningVideo $video, bool $completed): LearningVideoProgress
    {
        $progress = DB::transaction(function () use ($user, $video, $completed) {
            $progress = $this->lockedRow($user, $video);

            if ($completed) {
                $progress->completed_at ??= now();
                $progress->percent = 100;
            } else {
                $progress->completed_at = null;
                $progress->position_seconds = 0;
                $progress->max_position_seconds = 0;
                $progress->percent = 0;
            }

            $progress->duration_seconds ??= $video->duration_seconds;
            $progress->last_watched_at = now();
            $progress->save();

            return $progress;
        });

        $this->syncEnrollment($user, $video);

        return $progress;
    }

    /**
     * Roll-up of the user's progress through a course's published lessons.
     *
     * @return array{total:int,completed:int,percent:int,next_video:?LearningVideo,started:bool}
     */
    public function courseProgress(User $user, LearningCourse $course): array
    {
        $lessons = $this->courseLessons($user, $course->id)->get();

        $rows = $lessons->isEmpty()
            ? collect()
            : LearningVideoProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('learning_video_id', $lessons->modelKeys())
                ->get()
                ->keyBy('learning_video_id');

        $completedIds = $rows->filter(fn (LearningVideoProgress $p) => $p->isCompleted())->keys();
        $total = $lessons->count();
        $completed = $completedIds->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'next_video' => $lessons->first(fn (LearningVideo $v) => ! $completedIds->contains($v->id)),
            'started' => $rows->isNotEmpty(),
        ];
    }

    /**
     * Unfinished lessons (progress rows with video) the user may still see, newest first.
     *
     * @return Collection<int,LearningVideoProgress>
     */
    public function continueWatching(User $user, int $limit = 6): Collection
    {
        return $this->watchable($user)
            ->whereNull('completed_at')
            ->orderByDesc('last_watched_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int,LearningVideoProgress> */
    public function recentlyWatched(User $user, int $limit = 8): Collection
    {
        return $this->watchable($user)
            ->orderByDesc('last_watched_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int,LearningVideoProgress> */
    public function completedLessons(User $user, int $limit = 8): Collection
    {
        return $this->watchable($user)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Headline numbers for the learner dashboard.
     *
     * @return array{started:int,completed:int,watch_seconds:int,courses_enrolled:int,courses_completed:int}
     */
    public function stats(User $user): array
    {
        $progress = LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('video');

        $enrollments = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->whereHas('course');

        return [
            'started' => (clone $progress)->count(),
            'completed' => (clone $progress)->whereNotNull('completed_at')->count(),
            'watch_seconds' => (int) (clone $progress)->sum('watched_seconds'),
            'courses_enrolled' => (clone $enrollments)->count(),
            'courses_completed' => (clone $enrollments)->whereNotNull('completed_at')->count(),
        ];
    }

    // --- Internals -----------------------------------------------------
    private function threshold(): int
    {
        return max(1, min(100, (int) config('learning.completion_threshold', 90)));
    }

    /**
     * The video's own duration wins; a sane client value fills it in once
     * (only while still unknown, so two viewers cannot fight over it).
     */
    private function resolveDuration(LearningVideo $video, ?int $clientDuration): ?int
    {
        if ($video->duration_seconds) {
            return (int) $video->duration_seconds;
        }

        if ($clientDuration === null || $clientDuration < 1 || $clientDuration > self::MAX_DURATION) {
            return null;
        }

        LearningVideo::withTrashed()->whereKey($video->id)->whereNull('duration_seconds')
            ->toBase()->update(['duration_seconds' => $clientDuration]);

        $video->duration_seconds = $clientDuration;
        $video->syncOriginalAttribute('duration_seconds');

        return $clientDuration;
    }

    /** Views are a counter, not an edit: bump without touching updated_at. */
    private function bumpViews(LearningVideo $video): void
    {
        LearningVideo::withTrashed()->whereKey($video->id)->toBase()->increment('views');

        $video->views = (int) $video->views + 1;
        $video->syncOriginalAttribute('views');
    }

    /** The user's row for this video, created on first use and locked for the rest of the transaction. */
    private function lockedRow(User $user, LearningVideo $video): LearningVideoProgress
    {
        $keys = ['user_id' => $user->id, 'learning_video_id' => $video->id];

        // firstOrCreate() survives two first beacons racing on the unique key.
        $row = LearningVideoProgress::query()->firstOrCreate($keys);

        return LearningVideoProgress::query()->whereKey($row->id)->lockForUpdate()->first() ?? $row;
    }

    /** Published lessons of a course the user can see, in course order. */
    private function courseLessons(User $user, int $courseId): Builder
    {
        return LearningVideo::query()
            ->published()
            ->visibleTo($user)
            ->where('learning_course_id', $courseId)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Stamp (or clear) the enrolment's completed_at from the lessons actually
     * finished. Only enrolled learners have an enrolment to update.
     */
    private function syncEnrollment(User $user, LearningVideo $video): void
    {
        if ($video->learning_course_id === null) {
            return;
        }

        $enrollment = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->where('learning_course_id', $video->learning_course_id)
            ->first();

        if (! $enrollment) {
            return;
        }

        $lessonIds = $this->courseLessons($user, (int) $video->learning_course_id)->pluck('id');
        $done = $lessonIds->isNotEmpty() && LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('learning_video_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->count() === $lessonIds->count();

        if ($done && $enrollment->completed_at === null) {
            $enrollment->forceFill(['completed_at' => now()])->save();
        } elseif (! $done && $enrollment->completed_at !== null) {
            $enrollment->forceFill(['completed_at' => null])->save();
        }
    }

    /** The user's progress rows whose lesson is still published and visible to them. */
    private function watchable(User $user): Builder
    {
        return LearningVideoProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('video', fn (Builder $v) => $v->published()->visibleTo($user))
            ->with([
                'video.category:id,name,slug',
                'video.instructor:id,name',
                'video.course:id,title,slug',
            ]);
    }
}
