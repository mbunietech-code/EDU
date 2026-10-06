<?php

namespace App\Http\Controllers\Studio\Concerns;

use App\Models\LearningCourse;
use App\Models\LearningVideo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and payload rules for creating / editing a lesson video. Shared
 * by the web Studio (VideoController) and the app API (Api\StudioController).
 */
trait VideoInput
{
    private const THUMBNAIL_MAX_KB = 5120;

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'learning_category_id' => ['nullable', 'required_without:learning_course_id', 'integer',
                Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'learning_course_id' => ['nullable', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'learning_topic_id' => ['nullable', 'integer', Rule::exists('learning_topics', 'id')],
            'tags' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(array_keys(LearningVideo::VISIBILITY))],
            'instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::THUMBNAIL_MAX_KB],
            'auto_thumbnail' => ['nullable', 'file'],
            'notify' => ['nullable', 'boolean'],
        ];

        if ($creating) {
            $rules += [
                'upload_token' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9]{40}\z/'],
                'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
                'width' => ['nullable', 'integer', 'min:0', 'max:16384'],
                'height' => ['nullable', 'integer', 'min:0', 'max:16384'],
                'action' => ['nullable', Rule::in(['draft', 'publish'])],
            ];
        }

        return $rules;
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'learning_category_id.required_without' => 'Choose a category (or a course) for this lesson.',
            'learning_category_id.exists' => 'The selected category does not exist.',
            'learning_course_id.exists' => 'The selected course does not exist.',
            'learning_topic_id.exists' => 'The selected topic does not exist.',
            'upload_token.regex' => 'The upload is not valid. Please upload the file again.',
            'thumbnail.max' => 'The thumbnail may be at most '.(self::THUMBNAIL_MAX_KB / 1024).' MB.',
        ];
    }

    /**
     * Instructors (non-managers) may only use a course they instruct. When
     * editing, a lesson may stay in the course it is already in.
     */
    private function assertCourseAllowed(User $user, mixed $courseId, ?LearningVideo $video): void
    {
        if (blank($courseId) || $user->hasPermission('learning.manage')) {
            return;
        }

        $courseId = (int) $courseId;

        if ($video !== null && (int) $video->learning_course_id === $courseId) {
            return;
        }

        $teaches = LearningCourse::query()->whereKey($courseId)->where('instructor_id', $user->id)->exists();

        if (! $teaches) {
            throw ValidationException::withMessages([
                'learning_course_id' => 'You can only add lessons to courses you teach. Leave the course empty to publish a standalone lesson in any category.',
            ]);
        }
    }

    /** Only learning managers choose the instructor, and only among instructors or admins. */
    private function instructorPayload(Request $request, User $user, array $data): array
    {
        if (! $user->hasPermission('learning.manage') || ! $request->has('instructor_id')) {
            return [];
        }

        $id = $data['instructor_id'] ?? null;

        if ($id !== null) {
            $eligible = User::query()->realUsers()->whereKey($id)
                ->where(fn (Builder $q) => $q->where('can_teach', true)->orWhere('is_admin', true))
                ->exists();

            if (! $eligible) {
                throw ValidationException::withMessages([
                    'instructor_id' => 'The instructor must have instructor access (or be an administrator).',
                ]);
            }
        }

        return ['instructor_id' => $id];
    }

    /** The chosen thumbnail, else the frame the uploader captured from the video. */
    private function thumbnailFrom(Request $request): ?UploadedFile
    {
        $file = $request->file('thumbnail');

        return $file instanceof UploadedFile ? $file : $this->autoThumbnail($request);
    }

    /** The uploader's captured frame, only when it is a sane image (never fails the form). */
    private function autoThumbnail(Request $request): ?UploadedFile
    {
        $file = $request->file('auto_thumbnail');

        if (! $file instanceof UploadedFile || ! $file->isValid() || (int) $file->getSize() > self::THUMBNAIL_MAX_KB * 1024) {
            return null;
        }

        $info = @getimagesize((string) $file->getRealPath());

        return $info !== false && in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            ? $file
            : null;
    }
}
