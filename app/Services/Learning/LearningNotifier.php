<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningVideo;
use App\Models\User;
use App\Notifications\Learning\InstructorAccessGranted;
use App\Notifications\Learning\LiveSessionCancelled;
use App\Notifications\Learning\LiveSessionScheduled;
use App\Notifications\Learning\LiveSessionStarted;
use App\Notifications\Learning\LiveSessionStartingSoon;
use App\Notifications\Learning\NewCourseAvailable;
use App\Notifications\Learning\NewVideoLesson;
use App\Notifications\Learning\RecordingReady;
use App\Notifications\Learning\RoomAnnouncement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fans learning events out as database notifications (App\Notifications\Learning\*),
 * which PushDatabaseNotification also pushes to the mobile app.
 *
 * Contract for every notify* method: never throws (exceptions are
 * report()ed), never notifies the actor, and walks the audience with
 * chunkById(500) so a large member base cannot exhaust memory.
 *
 * Audiences follow the learner visibility rules of the room / course /
 * lesson. Staff who only see everything through an oversight permission
 * (rooms.view, learning.view) are not part of the audience.
 */
class LearningNotifier
{
    private const CHUNK = 500;

    /** Active users who may view the room under its non-draft rules, excluding the host. */
    public function roomAudience(LearningRoom $room): Builder
    {
        $query = $this->activeUsers()->when($room->host_id, fn (Builder $q) => $q->whereKeyNot($room->host_id));

        return match ($room->access) {
            'public' => $query,
            'private' => $query->whereIn('id', LearningRoomMember::query()
                ->select('user_id')
                ->where('learning_room_id', $room->id)),
            'course' => $room->learning_course_id
                ? $query->whereIn('id', $this->enrolledUserIds(
                    LearningCourse::query()->whereKey($room->learning_course_id)->select('id')))
                : $this->nobody($query),
            'category' => $room->learning_category_id
                ? $query->whereIn('id', $this->enrolledUserIds(
                    LearningCourse::query()->where('learning_category_id', $room->learning_category_id)->select('id')))
                : $this->nobody($query),
            default => $this->nobody($query),
        };
    }

    /** Active users who may open the course. */
    public function courseAudience(LearningCourse $course): Builder
    {
        $query = $this->activeUsers()->when($course->instructor_id, fn (Builder $q) => $q->whereKeyNot($course->instructor_id));

        if (! $course->exists || $course->trashed() || ! $course->isPublished()) {
            return $this->nobody($query);
        }

        return $course->isOpen()
            ? $query
            : $query->whereIn('id', $this->enrolledUserIds(LearningCourse::query()->whereKey($course->id)->select('id')));
    }

    /** Active users who may watch the lesson. */
    public function videoAudience(LearningVideo $video): Builder
    {
        $course = $video->learning_course_id ? $video->course : null; // null when trashed (SoftDeletes scope)

        $query = $this->activeUsers()
            ->when($video->instructor_id, fn (Builder $q) => $q->whereKeyNot($video->instructor_id))
            ->when($course?->instructor_id, fn (Builder $q) => $q->whereKeyNot($course->instructor_id));

        if (! $video->exists || $video->trashed() || ! $video->isPublished()) {
            return $this->nobody($query);
        }

        if ($video->visibility === 'members') {
            return $query;
        }

        if ($video->visibility !== 'course') {
            return $this->nobody($query); // private lessons: staff / instructors only
        }

        if ($video->learning_course_id === null) {
            return $query;
        }

        if (! $course || ! $course->isPublished()) {
            return $this->nobody($query);
        }

        return $course->isOpen()
            ? $query
            : $query->whereIn('id', $this->enrolledUserIds(LearningCourse::query()->whereKey($course->id)->select('id')));
    }

    public function notifyRoomScheduled(LearningRoom $room, ?User $actor = null): void
    {
        $this->broadcast(fn () => $room->isDraft() ? null : $this->roomAudience($room), fn () => new LiveSessionScheduled($room), $actor);
    }

    public function notifyRoomStartingSoon(LearningRoom $room): void
    {
        $this->broadcast(fn () => $this->roomAudience($room), fn () => new LiveSessionStartingSoon($room));
    }

    public function notifyRoomStarted(LearningRoom $room, ?User $actor = null): void
    {
        $this->broadcast(fn () => $this->roomAudience($room), fn () => new LiveSessionStarted($room), $actor);
    }

    public function notifyRoomCancelled(LearningRoom $room, ?User $actor = null): void
    {
        $this->broadcast(fn () => $this->roomAudience($room), fn () => new LiveSessionCancelled($room), $actor);
    }

    public function notifyAnnouncement(LearningRoom $room, LearningRoomMessage $message, ?User $actor = null): void
    {
        $this->broadcast(fn () => $this->roomAudience($room), fn () => new RoomAnnouncement($room, $message), $actor);
    }

    public function notifyNewVideo(LearningVideo $video, ?User $actor = null): void
    {
        $this->broadcast(fn () => $this->videoAudience($video), fn () => new NewVideoLesson($video), $actor);
    }

    public function notifyNewCourse(LearningCourse $course, ?User $actor = null): void
    {
        $this->broadcast(fn () => $this->courseAudience($course), fn () => new NewCourseAvailable($course), $actor);
    }

    /** Tells the room's host that a recording finished processing. */
    public function notifyRecordingReady(LearningRoomRecording $recording): void
    {
        try {
            $host = $recording->room?->host;

            if ($host && $host->isActive()) {
                $host->notify(new RecordingReady($recording));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function notifyInstructorGranted(User $user, ?User $actor = null): void
    {
        try {
            if ($actor && (int) $actor->id === (int) $user->id) {
                return;
            }

            if ($user->isActive()) {
                $user->notify(new InstructorAccessGranted($actor));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // --- Internals ---------------------------------------------------
    /**
     * Send one notification to a whole audience, 500 users at a time.
     *
     * @param  \Closure(): ?Builder  $audience  resolved inside the try so a failing query is report()ed too
     * @param  \Closure(): BaseNotification  $notification
     */
    private function broadcast(\Closure $audience, \Closure $notification, ?User $actor = null): void
    {
        try {
            $query = $audience();

            if ($query === null) {
                return;
            }

            if ($actor) {
                $query->whereKeyNot($actor->id);
            }

            $instance = $notification();

            $query->chunkById(self::CHUNK, function ($users) use ($instance) {
                Notification::send($users, $instance);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function activeUsers(): Builder
    {
        return User::query()->where('status', 'active');
    }

    /** Ids of users enrolled in any of the given (non-trashed) courses. */
    private function enrolledUserIds(Builder $courseIds): Builder
    {
        return LearningEnrollment::query()
            ->select('user_id')
            ->whereIn('learning_course_id', $courseIds);
    }

    private function nobody(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
