<?php

namespace App\Http\Controllers\Studio\Concerns;

use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningTopic;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and payload rules for creating / editing a live room. Shared by
 * the web Studio (RoomController) and the app API (Api\StudioController).
 */
trait RoomInput
{
    private const TIME_PATTERN = '/\A([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?\z/';

    private function canPickHost(User $user): bool
    {
        return $user->hasPermission('rooms.manage');
    }

    private function usesAnyCourse(User $user): bool
    {
        return $user->hasPermission('rooms.manage') || $user->hasPermission('learning.manage');
    }

    /** @return array<string,mixed> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'learning_category_id' => ['nullable', 'integer', Rule::exists('learning_categories', 'id')->whereNull('deleted_at')],
            'learning_course_id' => ['nullable', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'learning_topic_id' => ['nullable', 'integer', Rule::exists('learning_topics', 'id')],
            'host_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'scheduled_date' => ['nullable', 'date_format:Y-m-d', 'required_with:scheduled_time'],
            'scheduled_time' => ['nullable', 'string', 'regex:'.self::TIME_PATTERN, 'required_with:scheduled_date'],
            'duration_minutes' => ['required', 'integer', 'min:'.RoomService::MIN_DURATION_MINUTES, 'max:'.RoomService::MAX_DURATION_MINUTES],
            'access' => ['required', Rule::in(array_keys(LearningRoom::ACCESS))],
            'user_ids' => ['nullable', 'array', 'max:500'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'members_present' => ['nullable', 'boolean'],
            'chat_enabled' => ['nullable', 'boolean'],
            'questions_enabled' => ['nullable', 'boolean'],
            'allow_participant_media' => ['nullable', 'boolean'],
            'allow_screen_share' => ['nullable', 'boolean'],
            'notify' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['draft', 'schedule', 'save'])],
        ];
    }

    /** @return array<string,string> */
    private function messages(): array
    {
        return [
            'learning_category_id.exists' => 'The selected category does not exist.',
            'learning_course_id.exists' => 'The selected course does not exist.',
            'learning_topic_id.exists' => 'The selected topic does not exist.',
            'scheduled_date.date_format' => 'Give a valid date.',
            'scheduled_date.required_with' => 'Pick a date to go with the start time.',
            'scheduled_time.regex' => 'Give a valid start time (HH:MM).',
            'scheduled_time.required_with' => 'Pick a start time to go with the date.',
            'duration_minutes.min' => 'A session lasts at least '.RoomService::MIN_DURATION_MINUTES.' minutes.',
            'duration_minutes.max' => 'A session lasts at most '.RoomService::MAX_DURATION_MINUTES.' minutes (24 hours).',
            'access.in' => 'Choose who may join the room.',
            'user_ids.*.exists' => 'One of the invited members no longer exists.',
        ];
    }

    /**
     * Rules the plain validator cannot express: the start time, placement
     * consistency, the course product rule, audiences and invited members.
     */
    private function assertValidInput(Request $request, array $data, User $user, ?LearningRoom $room, string $action): void
    {
        $errors = [];

        // --- When -------------------------------------------------------
        $at = null;
        if (filled($data['scheduled_date'] ?? null) && filled($data['scheduled_time'] ?? null)) {
            [$h, $m] = array_map('intval', explode(':', (string) $data['scheduled_time']));
            $at = Carbon::createFromFormat('Y-m-d', (string) $data['scheduled_date'], (string) config('app.timezone'))
                ->setTime($h, $m, 0);
        }

        if ($action === 'schedule' && $at === null) {
            $errors['scheduled_date'] = 'Pick a date and start time to schedule the room.';
        }

        // A date may stay in the past only when it is the one the room already has
        // (e.g. renaming a completed room); anything new must be now or later.
        $unchanged = $at !== null && $room?->scheduled_at !== null && $room->scheduled_at->equalTo($at);
        if ($at !== null && $at->lt(now()->subMinute()) && ($action === 'schedule' || ! $unchanged)) {
            $errors['scheduled_date'] = 'The start time has already passed — pick a time from now on.';
        }

        // --- Where ------------------------------------------------------
        $categoryId = filled($data['learning_category_id'] ?? null) ? (int) $data['learning_category_id'] : null;
        $courseId = filled($data['learning_course_id'] ?? null) ? (int) $data['learning_course_id'] : null;
        $topicId = filled($data['learning_topic_id'] ?? null) ? (int) $data['learning_topic_id'] : null;

        $course = $courseId ? LearningCourse::query()->find($courseId) : null;
        if ($course && $categoryId && (int) $course->learning_category_id !== $categoryId) {
            $errors['learning_course_id'] = 'The selected course is not in the selected category.';
        }

        if ($topicId) {
            $topic = LearningTopic::query()->find($topicId);
            if (! $course) {
                $errors['learning_topic_id'] = 'Choose the course first — a topic belongs to a course.';
            } elseif ($topic && (int) $topic->learning_course_id !== (int) $course->id) {
                $errors['learning_topic_id'] = 'The topic does not belong to the selected course.';
            }
        }

        if ($course && ! $this->usesAnyCourse($user)
            && (int) $course->instructor_id !== (int) $user->id
            && ($room === null || (int) $room->learning_course_id !== (int) $course->id)) {
            $errors['learning_course_id'] = 'You can only link a room to a course you teach. Leave the course empty to use any category.';
        }

        // --- Who --------------------------------------------------------
        $access = (string) $data['access'];
        if ($access === 'course' && ! $course) {
            $errors['learning_course_id'] ??= 'Pick the course whose learners may join.';
        }

        if ($access === 'category' && ! $categoryId && ! $course) {
            $errors['learning_category_id'] = 'Pick the category whose learners may join.';
        }

        if ($access === 'private') {
            $hostId = $this->canPickHost($user) && filled($data['host_id'] ?? null)
                ? (int) $data['host_id']
                : (int) ($room?->host_id ?? $user->id);

            if ($room === null || $request->boolean('members_present')) {
                $invited = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $hostId);
                $hasMembers = $invited->isNotEmpty();
            } else {
                $hasMembers = $room->members()->where('user_id', '!=', $hostId)->exists();
            }

            if (! $hasMembers) {
                $errors['user_ids'] = 'Invite at least one member to a private room.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string,mixed> */
    private function placementPayload(array $data): array
    {
        return [
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
        ];
    }

    /**
     * The three switches. The form posts a hidden 0 before each checkbox; a
     * create request that leaves one out keeps the default (on).
     *
     * @return array<string,bool>
     */
    private function togglePayload(Request $request, bool $creating): array
    {
        $out = [];
        // Screen sharing by participants is opt-in; the other switches start on.
        foreach (['chat_enabled' => true, 'questions_enabled' => true, 'allow_participant_media' => true, 'allow_screen_share' => false] as $toggle => $default) {
            if ($request->has($toggle)) {
                $out[$toggle] = $request->boolean($toggle);
            } elseif ($creating) {
                $out[$toggle] = $default;
            }
        }

        return $out;
    }
}
