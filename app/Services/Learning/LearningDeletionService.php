<?php

namespace App\Services\Learning;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoComment;
use App\Services\DeletionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Safe deletes for learning content. Every delete is snapshotted through
 * App\Services\DeletionService, runs in a DB transaction, deletes files only
 * after commit (DB::afterCommit) and is written to the ActivityLog. Anything
 * that would orphan related data throws LearningDeletionBlocked with a
 * user-facing message.
 *
 * Soft-deleted items (categories, courses, lessons, rooms) keep their files
 * until they are purged — by hand from the trash or by learning:purge-trash.
 */
class LearningDeletionService
{
    /** A recording still downloading this recently is left alone (the job would write an orphan file). */
    private const PROCESSING_GRACE_HOURS = 3;

    public function __construct(
        protected DeletionService $deletions,
        protected LearningStorage $storage,
    ) {
    }

    /**
     * Human-readable consequences for the confirm-delete modal, e.g.
     * ["3 session records", "120 chat messages", "2 recordings (1.2 GB) — files deleted"].
     *
     * @return list<string>
     */
    public function impact(Model $model): array
    {
        $lines = match (true) {
            $model instanceof LearningCategory => [
                $this->count(LearningCourse::query()->where('learning_category_id', $model->id)->count(), 'course').' — must be moved or deleted first',
                $this->count(LearningVideo::query()->where('learning_category_id', $model->id)->count(), 'lesson').' — must be moved or deleted first',
                $this->count(LearningRoom::query()->where('learning_category_id', $model->id)->count(), 'room').' will be detached from the category',
            ],
            $model instanceof LearningCourse => [
                $this->count(LearningVideo::query()->where('learning_course_id', $model->id)->count(), 'lesson').' in this course',
                $this->count($model->topics()->count(), 'topic'),
                $this->count($model->enrollments()->count(), 'enrolment'),
                $this->count(LearningRoom::query()->where('learning_course_id', $model->id)->count(), 'room').' linked to this course',
            ],
            $model instanceof LearningTopic => [
                $this->count(LearningVideo::withTrashed()->where('learning_topic_id', $model->id)->count(), 'lesson').' will stay in the course without a topic',
            ],
            $model instanceof LearningVideo => [
                $this->count($model->progress()->count(), 'learner progress record'),
                $this->count($model->comments()->count(), 'comment'),
                $this->count($model->renditions()->count(), 'extra quality', 'extra qualities'),
                $this->count($model->resources()->count(), 'resource'),
                $model->hasFile() ? 'Video files are kept in the trash until it is purged' : '',
            ],
            $model instanceof LearningRoom => [
                $this->count($model->sessions()->count(), 'session record'),
                $this->count($model->attendances()->count(), 'attendance record'),
                $this->count($model->messages()->count(), 'chat message'),
                $this->recordingsLine($model->recordings()->get(), ' — files deleted when purged from the trash'),
            ],
            $model instanceof LearningRoomSession => [
                $this->count($model->attendances()->count(), 'attendance record'),
                $this->count(LearningRoomMessage::query()->where('learning_room_session_id', $model->id)->count(), 'chat message').' kept on the room',
                $this->recordingsLine($model->recordings()->get(), ' — files deleted'),
            ],
            $model instanceof LearningRoomRecording => [
                $model->path !== null
                    ? 'Recording file ('.$model->sizeLabel().') deleted'
                    : '',
                $model->learning_video_id !== null ? 'The lesson made from this recording is kept' : '',
            ],
            $model instanceof LearningVideoComment => [
                $this->count($model->replies()->count(), 'reply', 'replies'),
            ],
            default => [],
        };

        return array_values(array_filter($lines, fn (string $line) => $line !== '' && ! str_starts_with($line, '0 ')));
    }

    /**
     * Soft-delete a category. Blocked while it still has (non-trashed) courses
     * or videos; its rooms are detached (category set to null).
     *
     * @throws LearningDeletionBlocked
     */
    public function deleteCategory(LearningCategory $c, string $reason): void
    {
        $courses = LearningCourse::query()->where('learning_category_id', $c->id)->count();
        $videos = LearningVideo::query()->where('learning_category_id', $c->id)->count();

        if ($courses > 0 || $videos > 0) {
            throw new LearningDeletionBlocked('“'.$c->name.'” still has '.$this->join([
                $this->count($courses, 'course'),
                $this->count($videos, 'lesson'),
            ]).'. Move or delete them first.');
        }

        DB::transaction(function () use ($c, $reason) {
            $rooms = LearningRoom::withTrashed()->where('learning_category_id', $c->id)->update(['learning_category_id' => null]);

            $this->deletions->delete($c, $reason);

            ActivityLog::log('learning_category_deleted', 'LearningCategory', $c->id, [
                'name' => $c->name,
                'reason' => $reason,
                'rooms_detached' => $rooms,
            ]);
        });
    }

    /**
     * Soft-delete a course. Blocked while it has videos unless $withVideos,
     * in which case each video is snapshotted and soft-deleted too.
     *
     * @throws LearningDeletionBlocked
     */
    public function deleteCourse(LearningCourse $c, string $reason, bool $withVideos = false): void
    {
        $videos = LearningVideo::query()->where('learning_course_id', $c->id)->get();

        if ($videos->isNotEmpty() && ! $withVideos) {
            throw new LearningDeletionBlocked('“'.$c->title.'” still has '.$this->count($videos->count(), 'lesson')
                .'. Move them to another course, or delete the course together with its lessons.');
        }

        DB::transaction(function () use ($c, $reason, $videos) {
            foreach ($videos as $video) {
                $this->deletions->delete($video, $reason);

                ActivityLog::log('learning_video_deleted', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'reason' => $reason,
                    'with_course_id' => $c->id,
                ]);
            }

            $this->deletions->delete($c, $reason);

            ActivityLog::log('learning_course_deleted', 'LearningCourse', $c->id, [
                'title' => $c->title,
                'reason' => $reason,
                'videos_deleted' => $videos->count(),
            ]);
        });
    }

    /** Hard-delete a topic; its videos keep their course and lose the topic. */
    public function deleteTopic(LearningTopic $t, string $reason): void
    {
        DB::transaction(function () use ($t, $reason) {
            $videos = LearningVideo::withTrashed()->where('learning_topic_id', $t->id)->update(['learning_topic_id' => null]);
            LearningRoom::withTrashed()->where('learning_topic_id', $t->id)->update(['learning_topic_id' => null]);

            $this->deletions->delete($t, $reason);

            ActivityLog::log('learning_topic_deleted', 'LearningTopic', $t->id, [
                'title' => $t->title,
                'course_id' => $t->learning_course_id,
                'reason' => $reason,
                'videos_detached' => $videos,
            ]);
        });
    }

    /** Soft-delete a lesson (files stay until purge). */
    public function deleteVideo(LearningVideo $v, string $reason): void
    {
        DB::transaction(function () use ($v, $reason) {
            $this->deletions->delete($v, $reason);

            ActivityLog::log('learning_video_deleted', 'LearningVideo', $v->id, [
                'title' => $v->title,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Soft-delete a room. Blocked while it is live.
     *
     * @throws LearningDeletionBlocked
     */
    public function deleteRoom(LearningRoom $r, string $reason): void
    {
        DB::transaction(function () use ($r, $reason) {
            $room = LearningRoom::query()->whereKey($r->id)->lockForUpdate()->first() ?? $r;

            if ($room->isLive()) {
                throw new LearningDeletionBlocked('“'.$room->title.'” is live right now. End the session before deleting the room.');
            }

            $this->deletions->delete($r, $reason);

            ActivityLog::log('learning_room_deleted', 'LearningRoom', $r->id, [
                'title' => $r->title,
                'status' => $r->status,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Hard-delete an ended session with its attendance, and delete its
     * recordings (rows and files). Chat messages stay on the room.
     *
     * @throws LearningDeletionBlocked while the session is still running
     */
    public function deleteSession(LearningRoomSession $s, string $reason): void
    {
        if ($s->ended_at === null) {
            throw new LearningDeletionBlocked('This session is still running. End it before deleting its records.');
        }

        $recordings = $s->recordings()->get();
        $this->assertNotDownloading($recordings);

        DB::transaction(function () use ($s, $reason, $recordings) {
            $attendance = LearningRoomAttendance::query()->where('learning_room_session_id', $s->id)->count();

            foreach ($recordings as $recording) {
                $this->deletions->delete($recording, $reason);
            }

            $this->deletions->delete($s, $reason);

            ActivityLog::log('learning_room_session_deleted', 'LearningRoomSession', $s->id, [
                'room_id' => $s->learning_room_id,
                'reason' => $reason,
                'attendance_deleted' => $attendance,
                'recordings_deleted' => $recordings->count(),
            ]);

            $this->deleteRecordingFilesAfterCommit($recordings);
        });
    }

    /**
     * Hard-delete a recording and its file. A recording already turned into
     * a lesson no longer owns a file, so the lesson is unaffected.
     *
     * @throws LearningDeletionBlocked while the recording is still being downloaded
     */
    public function deleteRecording(LearningRoomRecording $r, string $reason): void
    {
        $this->assertNotDownloading(collect([$r]));

        DB::transaction(function () use ($r, $reason) {
            $this->deletions->delete($r, $reason);

            ActivityLog::log('learning_room_recording_deleted', 'LearningRoomRecording', $r->id, [
                'room_id' => $r->learning_room_id,
                'reason' => $reason,
                'size_bytes' => $r->path !== null ? $r->size_bytes : null,
            ]);

            $this->deleteRecordingFilesAfterCommit(collect([$r]));
        });
    }

    /** Soft-delete a comment together with its replies. */
    public function deleteComment(LearningVideoComment $c): void
    {
        DB::transaction(function () use ($c) {
            $replies = $c->replies()->count();
            $c->replies()->delete();
            $c->delete();

            ActivityLog::log('learning_video_comment_deleted', 'LearningVideoComment', $c->id, [
                'video_id' => $c->learning_video_id,
                'author_id' => $c->user_id,
                'replies_deleted' => $replies,
            ]);
        });
    }

    /**
     * Restore a trashed item. $type: category | course | video | room.
     *
     * @throws LearningDeletionBlocked while its parent is still trashed
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when nothing of that type/id is in the trash
     */
    public function restore(string $type, int $id): Model
    {
        $model = $this->trashed($type, $id);

        if (! $model instanceof LearningCategory) {
            $this->assertParentsLive($model);
        }

        DB::transaction(function () use ($model, $type) {
            $model->restore();

            ActivityLog::log('learning_'.$type.'_restored', class_basename($model), $model->getKey(), [
                'label' => $this->label($model),
            ]);
        });

        return $model;
    }

    /**
     * Permanently delete a trashed item and its files (video media, room
     * recordings). Categories and courses are blocked while child rows exist
     * (trashed included) — except that purging a course also purges its
     * trashed videos.
     *
     * @throws LearningDeletionBlocked
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException when nothing of that type/id is in the trash
     */
    public function purge(string $type, int $id): void
    {
        $model = $this->trashed($type, $id);

        match (true) {
            $model instanceof LearningCategory => $this->purgeCategory($model),
            $model instanceof LearningCourse => $this->purgeCourse($model),
            $model instanceof LearningVideo => $this->purgeVideo($model),
            $model instanceof LearningRoom => $this->purgeRoom($model),
        };
    }

    /**
     * Purge everything trashed longer than $days (default config('learning.trash_retention_days')).
     * Children go first so their parents are no longer blocked; items that
     * are still blocked (e.g. a category whose course was restored) are skipped.
     *
     * @return int items purged
     */
    public function purgeExpired(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('learning.trash_retention_days', 30));
        $cutoff = now()->subDays($days);
        $purged = 0;

        foreach (['video', 'room', 'course', 'category'] as $type) {
            $ids = $this->modelClass($type)::onlyTrashed()
                ->where('deleted_at', '<=', $cutoff)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $id) {
                try {
                    $this->purge($type, (int) $id);
                    $purged++;
                } catch (LearningDeletionBlocked|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                    // Still has children, or already purged with its course.
                }
            }
        }

        return $purged;
    }

    // --- Purge per type --------------------------------------------------
    private function purgeCategory(LearningCategory $category): void
    {
        $courses = LearningCourse::withTrashed()->where('learning_category_id', $category->id)->count();
        $videos = LearningVideo::withTrashed()->where('learning_category_id', $category->id)->count();

        if ($courses > 0 || $videos > 0) {
            throw new LearningDeletionBlocked('“'.$category->name.'” still has '.$this->join([
                $this->count($courses, 'course'),
                $this->count($videos, 'lesson'),
            ]).' (including items in the trash). Purge or move them first.');
        }

        DB::transaction(function () use ($category) {
            $category->forceDelete();

            ActivityLog::log('learning_category_purged', 'LearningCategory', $category->id, ['name' => $category->name]);
        });
    }

    private function purgeCourse(LearningCourse $course): void
    {
        $active = LearningVideo::query()->where('learning_course_id', $course->id)->count();

        if ($active > 0) {
            throw new LearningDeletionBlocked('“'.$course->title.'” still has '.$this->count($active, 'active lesson')
                .'. Move or delete them before purging the course.');
        }

        $videos = LearningVideo::onlyTrashed()
            ->where('learning_course_id', $course->id)
            ->with(['renditions', 'resources'])
            ->get();

        DB::transaction(function () use ($course, $videos) {
            foreach ($videos as $video) {
                $video->forceDelete();

                ActivityLog::log('learning_video_purged', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'with_course_id' => $course->id,
                ]);
            }

            $course->forceDelete();

            ActivityLog::log('learning_course_purged', 'LearningCourse', $course->id, [
                'title' => $course->title,
                'videos_purged' => $videos->count(),
            ]);

            DB::afterCommit(function () use ($course, $videos) {
                foreach ($videos as $video) {
                    $this->storage->purgeVideoFiles($video);
                }
                $this->storage->deleteFile($this->storage->publicDisk(), $course->thumbnail_path);
            });
        });
    }

    private function purgeVideo(LearningVideo $video): void
    {
        $video->load(['renditions', 'resources']);

        DB::transaction(function () use ($video) {
            $video->forceDelete();

            ActivityLog::log('learning_video_purged', 'LearningVideo', $video->id, [
                'title' => $video->title,
                'size_bytes' => $video->size_bytes,
            ]);

            DB::afterCommit(fn () => $this->storage->purgeVideoFiles($video));
        });
    }

    private function purgeRoom(LearningRoom $room): void
    {
        $recordings = LearningRoomRecording::query()->where('learning_room_id', $room->id)->get();
        $this->assertNotDownloading($recordings);

        DB::transaction(function () use ($room, $recordings) {
            // Sessions, attendance, messages, members and recordings cascade with the room.
            $room->forceDelete();

            ActivityLog::log('learning_room_purged', 'LearningRoom', $room->id, [
                'title' => $room->title,
                'recordings_deleted' => $recordings->count(),
            ]);

            $this->deleteRecordingFilesAfterCommit($recordings);
        });
    }

    // --- Internals -----------------------------------------------------
    /** @return class-string<Model> */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'category' => LearningCategory::class,
            'course' => LearningCourse::class,
            'video' => LearningVideo::class,
            'room' => LearningRoom::class,
            default => throw new \InvalidArgumentException("Unknown learning trash type [{$type}]."),
        };
    }

    private function trashed(string $type, int $id): Model
    {
        return $this->modelClass($type)::onlyTrashed()->findOrFail($id);
    }

    /**
     * A restored item must not point at a parent that is still in the trash
     * (it would be invisible or half-broken).
     *
     * @throws LearningDeletionBlocked
     */
    private function assertParentsLive(Model $model): void
    {
        $categoryId = $model->getAttribute('learning_category_id');
        $category = $categoryId ? LearningCategory::withTrashed()->find($categoryId) : null;

        if ($category?->trashed()) {
            throw new LearningDeletionBlocked('Restore the category “'.$category->name.'” first.');
        }

        $courseId = $model->getAttribute('learning_course_id');
        $course = $courseId ? LearningCourse::withTrashed()->find($courseId) : null;

        if ($course?->trashed()) {
            throw new LearningDeletionBlocked('Restore the course “'.$course->title.'” first.');
        }
    }

    /**
     * @param  Collection<int,LearningRoomRecording>  $recordings
     *
     * @throws LearningDeletionBlocked
     */
    private function assertNotDownloading(Collection $recordings): void
    {
        $busy = $recordings->first(fn (LearningRoomRecording $r) => $r->status === 'processing'
            && $r->updated_at !== null
            && $r->updated_at->gt(now()->subHours(self::PROCESSING_GRACE_HOURS)));

        if ($busy) {
            throw new LearningDeletionBlocked('A recording is still being downloaded from the video provider. Try again once it has finished.');
        }
    }

    /** @param  Collection<int,LearningRoomRecording>  $recordings */
    private function deleteRecordingFilesAfterCommit(Collection $recordings): void
    {
        $files = $recordings->filter(fn (LearningRoomRecording $r) => $r->path !== null)
            ->map(fn (LearningRoomRecording $r) => ['disk' => $r->disk, 'path' => $r->path])
            ->values();

        if ($files->isNotEmpty()) {
            DB::afterCommit(fn () => $files->each(fn (array $f) => $this->storage->deleteFile($f['disk'], $f['path'])));
        }
    }

    /** @param  Collection<int,LearningRoomRecording>  $recordings */
    private function recordingsLine(Collection $recordings, string $suffix): string
    {
        $withFiles = $recordings->filter(fn (LearningRoomRecording $r) => $r->path !== null);

        if ($recordings->isEmpty()) {
            return '';
        }

        $line = $this->count($recordings->count(), 'recording');

        return $withFiles->isEmpty()
            ? $line
            : $line.' ('.LearningVideo::humanBytes((int) $withFiles->sum('size_bytes')).')'.$suffix;
    }

    private function count(int $n, string $singular, ?string $plural = null): string
    {
        return $n.' '.($n === 1 ? $singular : ($plural ?? Str::plural($singular)));
    }

    /** "2 courses and 5 lessons" from the non-zero parts. */
    private function join(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn (string $p) => ! str_starts_with($p, '0 ')));

        return count($parts) > 1
            ? implode(', ', array_slice($parts, 0, -1)).' and '.end($parts)
            : ($parts[0] ?? '');
    }

    private function label(Model $model): string
    {
        return (string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? class_basename($model).' #'.$model->getKey());
    }
}
