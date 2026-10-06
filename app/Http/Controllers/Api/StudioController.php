<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Studio\Concerns\RoomInput;
use App\Http\Controllers\Studio\Concerns\VideoInput;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Models\User;
use App\Services\Learning\ChunkedUploadService;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\RoomService;
use App\Services\Learning\VideoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Teaching Studio for the app: schedule / edit / cancel live classes and
 * upload / edit / publish lessons. Same policies and validation as the web
 * Studio (RoomInput / VideoInput traits); video files arrive through the
 * chunked upload endpoints (Studio\UploadController) first.
 */
class StudioController extends Controller
{
    // Both traits name their rules()/messages(); use them by explicit alias.
    use RoomInput, VideoInput {
        RoomInput::rules insteadof VideoInput;
        RoomInput::messages insteadof VideoInput;
        RoomInput::rules as roomRules;
        RoomInput::messages as roomMessages;
        VideoInput::rules as videoRules;
        VideoInput::messages as videoMessages;
    }

    public function __construct(
        protected RoomService $rooms,
        protected VideoService $videos,
    ) {
    }

    public function home(Request $request, ChunkedUploadService $uploads): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canAccessStudio(), 403, 'The Teaching Studio is for instructors.');

        $canHost = $user->canHostRooms() || $user->hasPermission('rooms.view');
        $canUpload = $user->canUploadLessons() || $user->hasPermission('learning.view');
        $seesAllRooms = $user->hasPermission('rooms.view') || $user->hasPermission('rooms.manage');
        $seesAllVideos = $user->hasPermission('learning.view') || $user->hasPermission('learning.manage');

        $rooms = $canHost ? LearningRoom::query()
            ->when(! $seesAllRooms, fn (Builder $q) => $q->where('host_id', $user->id))
            ->with(['host:id,name', 'course:id,title'])
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderByDesc('scheduled_at')->orderByDesc('id')
            ->limit(50)->get() : collect();

        $videos = $canUpload ? LearningVideo::query()
            ->when(! $seesAllVideos, fn (Builder $q) => $q->where('instructor_id', $user->id))
            ->with(['course:id,title', 'category:id,name'])
            ->latest('id')->limit(50)->get() : collect();

        $anyCourse = $user->hasPermission('rooms.manage') || $user->hasPermission('learning.manage');

        return response()->json(['data' => [
            'can_host_rooms' => $user->can('create', LearningRoom::class),
            'can_upload_lessons' => $user->can('create', LearningVideo::class),
            'rooms' => $rooms->map(fn (LearningRoom $r) => $this->roomRow($r, $user))->values(),
            'videos' => $videos->map(fn (LearningVideo $v) => $this->videoRow($v, $user))->values(),
            'options' => [
                'categories' => LearningCategory::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
                'courses' => LearningCourse::query()
                    ->when(! $anyCourse, fn (Builder $q) => $q->where('instructor_id', $user->id))
                    ->orderBy('title')->get(['id', 'title', 'learning_category_id']),
                'room_access' => collect(LearningRoom::ACCESS)->except('private')->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
                'video_visibility' => collect(LearningVideo::VISIBILITY)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
                'duration' => ['min' => RoomService::MIN_DURATION_MINUTES, 'max' => RoomService::MAX_DURATION_MINUTES],
                'upload' => $canUpload ? $uploads->limits() : null,
            ],
            'web_url' => route('studio.home'),
        ]]);
    }

    // --- Live classes --------------------------------------------------------

    public function storeRoom(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('create', LearningRoom::class);

        $data = $request->validate($this->roomRules(), $this->roomMessages());
        $action = ($data['action'] ?? 'draft') === 'schedule' ? 'schedule' : 'draft';
        $this->assertValidInput($request, $data, $user, null, $action);

        $payload = $this->placementPayload($data) + [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? '',
            'scheduled_time' => $data['scheduled_time'] ?? '',
            'duration_minutes' => $data['duration_minutes'],
            'access' => $data['access'],
            'status' => $action === 'schedule' ? 'scheduled' : 'draft',
            'notify' => $request->boolean('notify'),
        ] + $this->togglePayload($request, creating: true);

        if ($data['access'] === 'private') {
            $payload['member_ids'] = $data['user_ids'] ?? [];
        }

        if ($this->canPickHost($user) && filled($data['host_id'] ?? null)) {
            $payload['host_id'] = (int) $data['host_id'];
        }

        $room = $this->rooms->create($payload, $user);

        return response()->json([
            'data' => $this->roomRow($room, $user),
            'message' => $room->isScheduled() ? 'Class scheduled.' : 'Class saved as a draft.',
        ], 201);
    }

    public function room(Request $request, LearningRoom $room): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $room);

        return response()->json(['data' => $this->roomRow($room, $user) + [
            'description' => $room->description,
            'learning_category_id' => $room->learning_category_id,
            'learning_course_id' => $room->learning_course_id,
            'scheduled_date' => $room->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d'),
            'scheduled_time' => $room->scheduled_at?->timezone(config('app.timezone'))->format('H:i'),
            'chat_enabled' => (bool) $room->chat_enabled,
            'questions_enabled' => (bool) $room->questions_enabled,
            'allow_participant_media' => (bool) $room->allow_participant_media,
            'allow_screen_share' => (bool) $room->allow_screen_share,
        ]]);
    }

    public function updateRoom(Request $request, LearningRoom $room): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $room);

        if ($room->isLive()) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:20000'],
            ]);
            $this->rooms->update($room, ['title' => $data['title'], 'description' => $data['description'] ?? null]
                + $this->togglePayload($request, creating: false), $user);

            return response()->json(['data' => $this->roomRow($room->refresh(), $user), 'message' => 'Class updated.']);
        }

        $data = $request->validate($this->roomRules(), $this->roomMessages());
        $action = in_array($data['action'] ?? 'save', ['schedule', 'draft', 'save'], true) ? ($data['action'] ?? 'save') : 'save';
        if ($action === 'schedule' && ! in_array($room->status, ['draft', 'cancelled'], true)) {
            $action = 'save';
        }
        $this->assertValidInput($request, $data, $user, $room, $action);

        $payload = $this->placementPayload($data) + [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? '',
            'scheduled_time' => $data['scheduled_time'] ?? '',
            'duration_minutes' => $data['duration_minutes'],
            'access' => $data['access'],
        ] + $this->togglePayload($request, creating: false);

        if ($data['access'] === 'private' && $request->boolean('members_present')) {
            $payload['member_ids'] = $data['user_ids'] ?? [];
        }

        $notify = $request->boolean('notify');
        DB::transaction(function () use ($room, $payload, $user, $action, $notify) {
            $this->rooms->update($room, $payload, $user);
            if ($action === 'schedule') {
                $this->rooms->publish($room, $user, $notify);
            }
        });

        return response()->json(['data' => $this->roomRow($room->refresh(), $user), 'message' => $action === 'schedule' ? 'Class scheduled.' : 'Class saved.']);
    }

    public function publishRoom(Request $request, LearningRoom $room): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $room);

        try {
            $this->rooms->publish($room, $request->user(), $request->boolean('notify', true));
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? 'The class could not be scheduled.'], 422);
        }

        return response()->json(['data' => $this->roomRow($room->refresh(), $request->user()), 'message' => 'Class scheduled.']);
    }

    public function cancelRoom(Request $request, LearningRoom $room): JsonResponse
    {
        Gate::forUser($request->user())->authorize('cancel', $room);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->rooms->cancel($room, $request->user(), $data['reason'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first() ?? 'The class could not be cancelled.'], 422);
        }

        return response()->json(['data' => $this->roomRow($room->refresh(), $request->user()), 'message' => 'Class cancelled.']);
    }

    public function destroyRoom(Request $request, LearningRoom $room, LearningDeletionService $deletions): JsonResponse
    {
        Gate::forUser($request->user())->authorize('delete', $room);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $deletions->deleteRoom($room, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Class moved to the trash.']);
    }

    // --- Lessons -------------------------------------------------------------

    public function storeVideo(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('create', LearningVideo::class);

        $data = $request->validate($this->videoRules(creating: true), $this->videoMessages());
        $this->assertCourseAllowed($user, $data['learning_course_id'] ?? null, null);
        $publish = ($data['action'] ?? 'draft') === 'publish';

        $video = $this->videos->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'visibility' => $data['visibility'],
            'status' => $publish ? 'published' : 'draft',
            'upload_token' => $data['upload_token'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'thumbnail' => $this->thumbnailFrom($request),
            'notify' => $request->boolean('notify'),
        ] + $this->instructorPayload($request, $user, $data), $user);

        return response()->json([
            'data' => $this->videoRow($video, $user),
            'message' => $video->isPublished() ? 'Lesson published.' : 'Lesson saved as a draft.',
        ], 201);
    }

    public function video(Request $request, LearningVideo $video): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $video);

        return response()->json(['data' => $this->videoRow($video, $request->user()) + [
            'description' => $video->description,
            'learning_category_id' => $video->learning_category_id,
            'learning_course_id' => $video->learning_course_id,
            'tags' => is_array($video->tags) ? implode(', ', $video->tags) : $video->tags,
        ]]);
    }

    public function updateVideo(Request $request, LearningVideo $video): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('update', $video);

        $data = $request->validate($this->videoRules(creating: false), $this->videoMessages());
        $this->assertCourseAllowed($user, $data['learning_course_id'] ?? null, $video);

        $payload = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'learning_category_id' => $data['learning_category_id'] ?? null,
            'learning_course_id' => $data['learning_course_id'] ?? null,
            'learning_topic_id' => $data['learning_topic_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'visibility' => $data['visibility'],
        ] + $this->instructorPayload($request, $user, $data);

        if ($thumbnail = $this->thumbnailFrom($request)) {
            $payload['thumbnail'] = $thumbnail;
        }

        $this->videos->update($video, $payload, $user);

        return response()->json(['data' => $this->videoRow($video->refresh(), $user), 'message' => 'Lesson saved.']);
    }

    public function publishVideo(Request $request, LearningVideo $video): JsonResponse
    {
        Gate::forUser($request->user())->authorize('publish', $video);
        $this->videos->publish($video, $request->user(), $request->boolean('notify', true));

        return response()->json(['data' => $this->videoRow($video->refresh(), $request->user()), 'message' => 'Lesson published.']);
    }

    public function unpublishVideo(Request $request, LearningVideo $video): JsonResponse
    {
        Gate::forUser($request->user())->authorize('publish', $video);
        $this->videos->unpublish($video, $request->user());

        return response()->json(['data' => $this->videoRow($video->refresh(), $request->user()), 'message' => 'Lesson moved back to drafts.']);
    }

    public function destroyVideo(Request $request, LearningVideo $video, LearningDeletionService $deletions): JsonResponse
    {
        Gate::forUser($request->user())->authorize('delete', $video);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            $deletions->deleteVideo($video, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Lesson moved to the trash.']);
    }

    // --- Rows ----------------------------------------------------------------

    protected function roomRow(LearningRoom $r, User $user): array
    {
        return [
            'id' => $r->id,
            'slug' => $r->slug,
            'title' => $r->title,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'access' => $r->access,
            'scheduled_at' => $r->scheduled_at?->toIso8601String(),
            'duration_minutes' => (int) $r->duration_minutes,
            'host' => $r->host?->name,
            'course' => $r->course?->title,
            'can_edit' => $user->can('update', $r),
            'can_start' => $user->can('start', $r),
            'can_cancel' => $user->can('cancel', $r),
        ];
    }

    protected function videoRow(LearningVideo $v, User $user): array
    {
        return [
            'id' => $v->id,
            'slug' => $v->slug,
            'title' => $v->title,
            'status' => $v->status,
            'visibility' => $v->visibility,
            'duration_label' => $v->durationLabel(),
            'thumbnail_url' => $v->thumbnailUrl(),
            'has_file' => $v->hasFile(),
            'course' => $v->course?->title,
            'category' => $v->category?->name,
            'can_publish' => $user->can('publish', $v),
        ];
    }
}
