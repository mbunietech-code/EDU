<?php

namespace App\Services\Learning;

use App\Models\ActivityLog;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoomRecording;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoRendition;
use App\Models\LearningVideoResource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Every write to a video lesson and its assets. Each action is recorded in
 * the ActivityLog (learning_video_created / _updated / _replaced /
 * _published / _unpublished / _rendition_added / …).
 *
 * Files are stored before the database transaction opens and removed again
 * if it fails; files being replaced are deleted only after the commit, so a
 * rolled-back edit never leaves a lesson pointing at a missing file.
 */
class VideoService
{
    private const MAX_TAGS = 15;
    private const MAX_TAG_LENGTH = 40;

    public function __construct(protected LearningStorage $storage)
    {
    }

    /**
     * Create a lesson.
     *
     * $data: title, description, learning_category_id, learning_course_id?,
     * learning_topic_id?, tags (array or comma-separated string), visibility,
     * status, instructor_id?, upload_token?, duration_seconds?, width?, height?,
     * thumbnail (UploadedFile)?, notify (bool)?. The category is forced to the
     * course's category when a course is given; the topic must belong to the course.
     * status "published" needs an upload_token (publish() requires a file).
     *
     * @param  array<string,mixed>  $data
     */
    public function create(array $data, User $actor): LearningVideo
    {
        $placement = $this->placement($data);
        $wantsPublish = ($data['status'] ?? 'draft') === 'published';

        if ($wantsPublish && blank($data['upload_token'] ?? null)) {
            throw ValidationException::withMessages(['status' => 'Upload the video file before publishing this lesson.']);
        }

        $stored = [];

        try {
            $file = null;
            if (filled($data['upload_token'] ?? null)) {
                $file = $this->storage->adoptUpload($actor, (string) $data['upload_token'], 'video', 'learning/videos');
                $stored[] = $file;
            }

            $thumbnail = ($data['thumbnail'] ?? null) instanceof UploadedFile
                ? $this->storage->storeThumbnail($data['thumbnail'])
                : null;
            if ($thumbnail) {
                $stored[] = ['disk' => $this->storage->publicDisk(), 'path' => $thumbnail];
            }

            return DB::transaction(function () use ($data, $actor, $placement, $file, $thumbnail, $wantsPublish) {
                $video = new LearningVideo(array_merge($placement, [
                    'title' => $this->title($data['title'] ?? null),
                    'description' => $this->description($data['description'] ?? null),
                    'tags' => $this->tags($data['tags'] ?? null),
                    'visibility' => $this->visibility($data['visibility'] ?? null),
                    'status' => 'draft',
                    'instructor_id' => $this->instructorId($data, $actor, null),
                    'duration_seconds' => $this->bounded($data['duration_seconds'] ?? null, 86400),
                    'width' => $this->bounded($data['width'] ?? null, 16384),
                    'height' => $this->bounded($data['height'] ?? null, 16384),
                    'thumbnail_path' => $thumbnail,
                    'position' => $this->nextPosition($placement['learning_course_id']),
                ]));

                if ($file) {
                    $video->fill($this->fileAttributes($file));
                }

                $video->forceFill(['created_by' => $actor->id, 'updated_by' => $actor->id])->save();

                ActivityLog::log('learning_video_created', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'course_id' => $video->learning_course_id,
                    'has_file' => $video->hasFile(),
                ]);

                if ($wantsPublish) {
                    $this->publish($video, $actor, (bool) ($data['notify'] ?? true));
                }

                return $video;
            });
        } catch (Throwable $e) {
            $this->discard($stored);
            throw $e;
        }
    }

    /**
     * Update a lesson. Keys missing from $data keep their current value; an
     * upload_token replaces the file (replaceFile()), a thumbnail upload
     * replaces the thumbnail and a status change publishes / unpublishes.
     * Moving a lesson to another course puts it at the end of that course.
     *
     * @param  array<string,mixed>  $data  same keys as create()
     */
    public function update(LearningVideo $video, array $data, User $actor): LearningVideo
    {
        $current = [
            'learning_category_id' => $video->learning_category_id,
            'learning_course_id' => $video->learning_course_id,
            // A new course without a topic choice means "no topic", not the old course's topic.
            'learning_topic_id' => array_key_exists('learning_course_id', $data) ? null : $video->learning_topic_id,
        ];
        $placement = $this->placement(array_merge($current, array_intersect_key($data, $current)));

        $status = $data['status'] ?? $video->status;
        if ($status === 'published' && ! $video->hasFile() && blank($data['upload_token'] ?? null)) {
            throw ValidationException::withMessages(['status' => 'Upload the video file before publishing this lesson.']);
        }

        $thumbnail = ($data['thumbnail'] ?? null) instanceof UploadedFile
            ? $this->storage->storeThumbnail($data['thumbnail'])
            : null;
        $oldThumbnail = $video->thumbnail_path;

        try {
            DB::transaction(function () use ($video, $data, $actor, $placement, $thumbnail) {
                if ((int) $placement['learning_course_id'] !== (int) $video->learning_course_id) {
                    $video->position = $this->nextPosition($placement['learning_course_id']);
                }

                $video->fill($placement);

                if (array_key_exists('title', $data)) {
                    $video->title = $this->title($data['title']);
                }
                if (array_key_exists('description', $data)) {
                    $video->description = $this->description($data['description']);
                }
                if (array_key_exists('tags', $data)) {
                    $video->tags = $this->tags($data['tags']);
                }
                if (array_key_exists('visibility', $data)) {
                    $video->visibility = $this->visibility($data['visibility']);
                }
                // Blank metadata (the uploader's hidden inputs without a new upload) keeps what we know.
                foreach (['duration_seconds' => 86400, 'width' => 16384, 'height' => 16384] as $key => $max) {
                    $value = $this->bounded($data[$key] ?? null, $max);
                    if ($value !== null && blank($data['upload_token'] ?? null)) {
                        $video->{$key} = $value;
                    }
                }

                $video->instructor_id = $this->instructorId($data, $actor, $video);

                if ($thumbnail) {
                    $video->thumbnail_path = $thumbnail;
                }

                $changed = array_keys($video->getDirty());
                $video->forceFill(['updated_by' => $actor->id])->save();

                ActivityLog::log('learning_video_updated', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'changed' => $changed,
                ]);
            });
        } catch (Throwable $e) {
            $this->discard($thumbnail ? [['disk' => $this->storage->publicDisk(), 'path' => $thumbnail]] : []);
            throw $e;
        }

        if ($thumbnail && $oldThumbnail) {
            $this->storage->deleteFile($this->storage->publicDisk(), $oldThumbnail);
        }

        if (filled($data['upload_token'] ?? null)) {
            $this->replaceFile(
                $video,
                (string) $data['upload_token'],
                $actor,
                $this->bounded($data['duration_seconds'] ?? null, 86400),
                $this->bounded($data['width'] ?? null, 16384),
                $this->bounded($data['height'] ?? null, 16384),
            );
        }

        if ($status === 'published' && ! $video->isPublished()) {
            $this->publish($video, $actor, (bool) ($data['notify'] ?? true));
        } elseif ($status === 'draft' && $video->isPublished()) {
            $this->unpublish($video, $actor);
        }

        return $video;
    }

    /**
     * Swap the source file for a completed upload; the old file is deleted
     * after commit. The new file's metadata replaces the old one (null when
     * unknown — the player fills the duration in on first play).
     */
    public function replaceFile(LearningVideo $video, string $uploadToken, User $actor, ?int $duration = null, ?int $width = null, ?int $height = null): LearningVideo
    {
        $file = $this->storage->adoptUpload($actor, $uploadToken, 'video', 'learning/videos');
        $old = ['disk' => $video->disk, 'path' => $video->path];

        try {
            DB::transaction(function () use ($video, $actor, $file, $duration, $width, $height, $old) {
                $video->fill($this->fileAttributes($file));
                $video->fill([
                    'duration_seconds' => $this->bounded($duration, 86400),
                    'width' => $this->bounded($width, 16384),
                    'height' => $this->bounded($height, 16384),
                ]);
                $video->forceFill(['updated_by' => $actor->id])->save();

                ActivityLog::log('learning_video_replaced', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'original_name' => $file['original_name'],
                    'size_bytes' => $file['size_bytes'],
                    'had_file' => $old['path'] !== null,
                ]);

                DB::afterCommit(fn () => $this->storage->deleteFile($old['disk'], $old['path']));
            });
        } catch (Throwable $e) {
            $this->discard([$file]);
            throw $e;
        }

        return $video;
    }

    /**
     * Publish (requires hasFile()); the first publish stamps published_at and
     * notifies the audience once the transaction commits. Publishing an
     * already published lesson does nothing.
     *
     * @throws ValidationException (key 'status') when the lesson has no file yet
     */
    public function publish(LearningVideo $video, User $actor, bool $notify = true): void
    {
        if (! $video->hasFile()) {
            throw ValidationException::withMessages(['status' => 'Upload the video file before publishing this lesson.']);
        }

        if ($video->isPublished()) {
            return;
        }

        $first = $video->published_at === null;

        $video->forceFill([
            'status' => 'published',
            'published_at' => $video->published_at ?? now(),
            'updated_by' => $actor->id,
        ])->save();

        ActivityLog::log('learning_video_published', 'LearningVideo', $video->id, [
            'title' => $video->title,
            'first_publish' => $first,
            'notify' => $first && $notify,
        ]);

        if ($first && $notify) {
            DB::afterCommit(fn () => app(LearningNotifier::class)->notifyNewVideo($video, $actor));
        }
    }

    /** Back to draft; published_at is kept so a later re-publish does not notify again. */
    public function unpublish(LearningVideo $video, User $actor): void
    {
        if (! $video->isPublished()) {
            return;
        }

        $video->forceFill(['status' => 'draft', 'updated_by' => $actor->id])->save();

        ActivityLog::log('learning_video_unpublished', 'LearningVideo', $video->id, ['title' => $video->title]);
    }

    public function removeThumbnail(LearningVideo $video, User $actor): void
    {
        $path = $video->thumbnail_path;
        if ($path === null) {
            return;
        }

        DB::transaction(function () use ($video, $actor, $path) {
            $video->forceFill(['thumbnail_path' => null, 'updated_by' => $actor->id])->save();

            ActivityLog::log('learning_video_thumbnail_removed', 'LearningVideo', $video->id, ['title' => $video->title]);

            DB::afterCommit(fn () => $this->storage->deleteFile($this->storage->publicDisk(), $path));
        });
    }

    /** Attach a pre-encoded quality (replaces an existing rendition of the same quality). */
    public function addRendition(LearningVideo $video, string $uploadToken, string $quality, User $actor): LearningVideoRendition
    {
        if (! array_key_exists($quality, LearningVideo::QUALITIES)) {
            throw ValidationException::withMessages(['quality' => 'Choose one of: '.implode(', ', array_keys(LearningVideo::QUALITIES)).'.']);
        }

        $file = $this->storage->adoptUpload($actor, $uploadToken, 'rendition', 'learning/renditions');

        try {
            return DB::transaction(function () use ($video, $quality, $actor, $file) {
                $rendition = LearningVideoRendition::query()
                    ->where('learning_video_id', $video->id)
                    ->where('quality', $quality)
                    ->lockForUpdate()
                    ->first();

                $old = $rendition ? ['disk' => $rendition->disk, 'path' => $rendition->path] : null;

                $rendition ??= new LearningVideoRendition(['learning_video_id' => $video->id, 'quality' => $quality]);
                $rendition->fill([
                    'height' => LearningVideo::QUALITIES[$quality],
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'mime' => $file['mime'],
                    'size_bytes' => $file['size_bytes'],
                ])->save();

                $video->forceFill(['updated_by' => $actor->id])->save();

                ActivityLog::log('learning_video_rendition_added', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'quality' => $quality,
                    'replaced' => $old !== null,
                    'size_bytes' => $file['size_bytes'],
                ]);

                if ($old) {
                    DB::afterCommit(fn () => $this->storage->deleteFile($old['disk'], $old['path']));
                }

                $video->unsetRelation('renditions');

                return $rendition;
            });
        } catch (Throwable $e) {
            $this->discard([$file]);
            throw $e;
        }
    }

    public function removeRendition(LearningVideoRendition $rendition, User $actor): void
    {
        DB::transaction(function () use ($rendition) {
            $rendition->delete();

            ActivityLog::log('learning_video_rendition_removed', 'LearningVideo', $rendition->learning_video_id, [
                'quality' => $rendition->quality,
            ]);

            DB::afterCommit(fn () => $this->storage->deleteFile($rendition->disk, $rendition->path));
        });
    }

    /**
     * @param  array{title:string,type:string,url?:string,file?:\Illuminate\Http\UploadedFile}  $data
     *
     * @throws ValidationException (keys title / type / url / file)
     */
    public function addResource(LearningVideo $video, array $data, User $actor): LearningVideoResource
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['title' => 'Give the resource a title (up to 255 characters).']);
        }

        $type = $data['type'] ?? null;
        if (! in_array($type, LearningVideoResource::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'A resource is either a file or a link.']);
        }

        $attributes = ['title' => $title, 'type' => $type];

        if ($type === 'link') {
            $url = trim((string) ($data['url'] ?? ''));
            $scheme = Str::lower((string) parse_url($url, PHP_URL_SCHEME));

            if ($url === '' || mb_strlen($url) > 500 || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages(['url' => 'Enter a valid http(s) link (up to 500 characters).']);
            }

            $attributes['url'] = $url;
            $stored = null;
        } else {
            if (! (($data['file'] ?? null) instanceof UploadedFile)) {
                throw ValidationException::withMessages(['file' => 'Choose a file to attach.']);
            }

            $stored = $this->storage->storeResourceFile($data['file']);
            $attributes += $stored;
        }

        try {
            return DB::transaction(function () use ($video, $attributes, $actor) {
                $resource = new LearningVideoResource($attributes + [
                    'learning_video_id' => $video->id,
                    'position' => (int) LearningVideoResource::query()->where('learning_video_id', $video->id)->max('position') + 1,
                ]);
                $resource->save();

                $video->forceFill(['updated_by' => $actor->id])->save();

                ActivityLog::log('learning_video_resource_added', 'LearningVideo', $video->id, [
                    'title' => $video->title,
                    'resource' => $resource->title,
                    'type' => $resource->type,
                ]);

                $video->unsetRelation('resources');

                return $resource;
            });
        } catch (Throwable $e) {
            $this->discard($stored ? [$stored] : []);
            throw $e;
        }
    }

    public function removeResource(LearningVideoResource $resource, User $actor): void
    {
        DB::transaction(function () use ($resource) {
            $resource->delete();

            ActivityLog::log('learning_video_resource_removed', 'LearningVideo', $resource->learning_video_id, [
                'resource' => $resource->title,
                'type' => $resource->type,
            ]);

            if ($resource->isFile()) {
                DB::afterCommit(fn () => $this->storage->deleteFile($resource->disk, $resource->path));
            }
        });
    }

    /**
     * Turn a room recording into a draft lesson in the room's category/course.
     * The file is MOVED, not copied: the video takes the recording's disk/path,
     * the recording's path is cleared and its learning_video_id set.
     *
     * @throws ValidationException (key 'recording') when the recording has no
     *         usable file or was already converted, or the room has no category
     */
    public function createFromRecording(LearningRoomRecording $recording, User $actor): LearningVideo
    {
        return DB::transaction(function () use ($recording, $actor) {
            $recording = LearningRoomRecording::query()->whereKey($recording->id)->lockForUpdate()->firstOrFail();

            if ($recording->learning_video_id !== null) {
                throw ValidationException::withMessages(['recording' => 'This recording has already been turned into a lesson.']);
            }

            if (! $recording->isReady() || blank($recording->path) || blank($recording->disk)) {
                throw ValidationException::withMessages(['recording' => 'This recording has no file ready to publish yet.']);
            }

            $room = $recording->room()->withTrashed()->with(['course', 'topic'])->firstOrFail();
            $course = $room->course; // null when there is none or it sits in the trash
            $categoryId = $course?->learning_category_id ?? $room->learning_category_id;

            if ($categoryId === null || ! LearningCategory::query()->whereKey($categoryId)->exists()) {
                throw ValidationException::withMessages(['recording' => 'Give the room a category or course before publishing its recording as a lesson.']);
            }

            $topicId = $course && $room->topic && (int) $room->topic->learning_course_id === (int) $course->id
                ? $room->topic->id
                : null;

            $recordedAt = $recording->session?->started_at ?? $recording->created_at ?? now();

            $video = new LearningVideo([
                'learning_category_id' => $categoryId,
                'learning_course_id' => $course?->id,
                'learning_topic_id' => $topicId,
                'instructor_id' => $room->host_id,
                'title' => Str::limit($room->title.' — recording '.$recordedAt->format('d M Y'), 255, ''),
                'description' => $room->description,
                'visibility' => 'course',
                'status' => 'draft',
                'disk' => $recording->disk,
                'path' => $recording->path,
                'original_name' => $recording->original_name,
                'mime' => $recording->mime ?: 'video/mp4',
                'size_bytes' => $recording->size_bytes,
                'duration_seconds' => $this->bounded($recording->duration_seconds, 86400),
                'position' => $this->nextPosition($course?->id),
            ]);
            $video->forceFill(['created_by' => $actor->id, 'updated_by' => $actor->id])->save();

            // Hand the file over: from now on only the lesson owns it.
            $recording->forceFill(['path' => null, 'learning_video_id' => $video->id])->save();

            ActivityLog::log('learning_video_created_from_recording', 'LearningVideo', $video->id, [
                'title' => $video->title,
                'room_id' => $room->id,
                'recording_id' => $recording->id,
            ]);

            return $video;
        });
    }

    // --- Internals -----------------------------------------------------
    /**
     * Resolve category / course / topic. A course dictates the category; a
     * topic must belong to the chosen course.
     *
     * @return array{learning_category_id:int,learning_course_id:?int,learning_topic_id:?int}
     */
    private function placement(array $data): array
    {
        $courseId = filled($data['learning_course_id'] ?? null) ? (int) $data['learning_course_id'] : null;
        $topicId = filled($data['learning_topic_id'] ?? null) ? (int) $data['learning_topic_id'] : null;

        if ($courseId !== null) {
            $course = LearningCourse::query()->find($courseId);
            if (! $course) {
                throw ValidationException::withMessages(['learning_course_id' => 'The selected course does not exist.']);
            }
            $categoryId = (int) $course->learning_category_id;
        } else {
            $categoryId = filled($data['learning_category_id'] ?? null) ? (int) $data['learning_category_id'] : null;
        }

        if ($categoryId === null || ! LearningCategory::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['learning_category_id' => 'Choose a category for this lesson.']);
        }

        if ($topicId !== null) {
            $belongs = $courseId !== null && LearningTopic::query()
                ->whereKey($topicId)
                ->where('learning_course_id', $courseId)
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages(['learning_topic_id' => 'The topic must belong to the selected course.']);
            }
        }

        return [
            'learning_category_id' => $categoryId,
            'learning_course_id' => $courseId,
            'learning_topic_id' => $topicId,
        ];
    }

    /**
     * Only learning managers pick the instructor; an instructor uploading
     * their own lesson is always its instructor. A manager who is not an
     * instructor and names nobody leaves it empty.
     */
    private function instructorId(array $data, User $actor, ?LearningVideo $video): ?int
    {
        if (! $actor->hasPermission('learning.manage')) {
            return $video ? $video->instructor_id : $actor->id;
        }

        if (! array_key_exists('instructor_id', $data)) {
            return $video ? $video->instructor_id : ($actor->isInstructor() ? $actor->id : null);
        }

        if (blank($data['instructor_id'])) {
            return null;
        }

        $id = (int) $data['instructor_id'];
        if (! User::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['instructor_id' => 'The selected instructor does not exist.']);
        }

        return $id;
    }

    private function title(mixed $title): string
    {
        $title = trim((string) $title);

        if ($title === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['title' => 'Give the lesson a title (up to 255 characters).']);
        }

        return $title;
    }

    private function description(mixed $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }

    /** Array or "a, b, c" → unique (case-insensitive), trimmed, at most 15 tags of 40 chars; null when empty. */
    private function tags(mixed $tags): ?array
    {
        $list = is_array($tags) ? $tags : explode(',', (string) $tags);
        $clean = [];

        foreach ($list as $tag) {
            $tag = Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $tag)), self::MAX_TAG_LENGTH, '');
            if ($tag !== '' && ! array_key_exists(mb_strtolower($tag), $clean)) {
                $clean[mb_strtolower($tag)] = $tag;
            }
        }

        $clean = array_slice(array_values($clean), 0, self::MAX_TAGS);

        return $clean === [] ? null : $clean;
    }

    private function visibility(mixed $visibility): string
    {
        if ($visibility === null || $visibility === '') {
            return 'course';
        }

        if (! array_key_exists($visibility, LearningVideo::VISIBILITY)) {
            throw ValidationException::withMessages(['visibility' => 'Choose a valid visibility.']);
        }

        return $visibility;
    }

    /** Positive integer up to $max, else null (unknown). */
    private function bounded(mixed $value, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 1 && $value <= $max ? $value : null;
    }

    private function nextPosition(?int $courseId): int
    {
        if ($courseId === null) {
            return 0;
        }

        $max = LearningVideo::withTrashed()->where('learning_course_id', $courseId)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /** @param  array{disk:string,path:string,original_name:string,mime:string,size_bytes:int}  $file */
    private function fileAttributes(array $file): array
    {
        return [
            'disk' => $file['disk'],
            'path' => $file['path'],
            'original_name' => Str::limit($file['original_name'], 255, ''),
            'mime' => $file['mime'],
            'size_bytes' => $file['size_bytes'],
        ];
    }

    /** Remove files stored for a write that did not commit. */
    private function discard(array $files): void
    {
        foreach ($files as $file) {
            $this->storage->deleteFile($file['disk'] ?? null, $file['path'] ?? null);
        }
    }
}
