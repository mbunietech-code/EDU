<?php

namespace App\Http\Controllers\Learn;

use App\Exceptions\Learning\RoomAccessException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Studio\RoomMaterialController;
use App\Models\LearningRoom;
use App\Models\LearningRoomMaterial;
use App\Models\User;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The live classroom page and its JSON endpoints (join / token, presence,
 * leave, feed). Media flows between the browser and our self-hosted SFU;
 * this controller only authorises, issues short-lived tokens and serves the
 * chat / people / materials feed.
 */
class LiveRoomController extends Controller
{
    public function __construct(protected RoomService $rooms, protected LiveProvider $live)
    {
    }

    public function show(Request $request, LearningRoom $room)
    {
        $this->authorize('view', $room);

        $room->load(['host:id,name,can_teach', 'category:id,name,slug', 'course:id,title,slug']);

        $config = $this->classroomConfig($room, $request->user());

        return response()->view('learn.rooms.live', [
            'room' => $room,
            'config' => $config,
            'isManager' => $config['viewer']['is_manager'],
            'descriptionHtml' => $room->description
                ? Str::markdown($room->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
                : null,
        ])
            // Our own origin may use the camera, microphone, screen capture and full screen — nobody else.
            ->header('Permissions-Policy', 'camera=(self), microphone=(self), display-capture=(self), fullscreen=(self), autoplay=(self)')
            ->header('Cache-Control', 'no-store, private');
    }

    /** Also serves learn.rooms.token: a reconnect simply joins again with a fresh token. */
    public function join(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('join', $room);

        $user = $request->user();
        $status = $this->live->status();

        // Refuse before touching attendance when the video server is not set up.
        if (! $status['configured']) {
            return $this->providerUnavailable($room, $user, implode(' ', $status['issues']));
        }

        try {
            $result = $this->rooms->join($room, $user);
        } catch (RoomAccessException $e) {
            return response()->json([
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ], $e->reason === 'not_live' ? 409 : 403);
        } catch (\RuntimeException $e) {
            // Database errors are RuntimeExceptions too — only provider problems become a 503.
            if ($e instanceof \PDOException) {
                throw $e;
            }
            report($e);

            return $this->providerUnavailable($room, $user, $e->getMessage());
        }

        return response()->json([
            'config' => $result['config'],
            'attendance_id' => $result['attendance']->id,
        ]);
    }

    public function presence(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('join', $room);

        return response()->json($this->rooms->presence($room, $request->user()));
    }

    /** Also the sendBeacon target on pagehide (FormData with _token). */
    public function leave(Request $request, LearningRoom $room)
    {
        $this->authorize('view', $room);

        $this->rooms->leave($room, $request->user());

        return response()->noContent();
    }

    public function feed(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('view', $room);

        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
        ]);

        $feed = $this->rooms->feed(
            $room,
            $request->user(),
            (int) ($data['after'] ?? 0),
            $data['since'] ?? null,
        );

        $feed['materials'] = $this->materials($room);

        return response()->json($feed);
    }

    /**
     * Everything the classroom Alpine component needs. Manager-only URLs are
     * left out entirely for other viewers (the endpoints authorise anyway).
     *
     * @return array<string,mixed>
     */
    protected function classroomConfig(LearningRoom $room, User $user): array
    {
        $isManager = $room->isManageableBy($user);
        $status = $this->live->status();
        $userTemplate = fn (string $name) => str_replace('999999999', '__ID__',
            route($name, ['room' => $room->id, 'user' => 999999999]));

        $urls = [
            'room' => route('learn.rooms.show', $room),
            'rooms' => route('learn.rooms.index'),
            'join' => route('learn.rooms.join', $room),
            'token' => route('learn.rooms.token', $room),
            'presence' => route('learn.rooms.presence', $room),
            'leave' => route('learn.rooms.leave', $room),
            'feed' => route('learn.rooms.feed', $room),
            'messages' => [
                'store' => route('learn.rooms.messages.store', $room),
                'answer' => route('learn.rooms.messages.answer', ['room' => $room, 'message' => '__ID__']),
                'destroy' => route('learn.rooms.messages.destroy', ['room' => $room, 'message' => '__ID__']),
            ],
        ];

        if ($isManager) {
            $urls['studio'] = [
                'show' => route('studio.rooms.show', $room->id),
                'start' => route('studio.rooms.start', $room->id),
                'end' => route('studio.rooms.end', $room->id),
                'announce' => route('studio.rooms.announce', $room->id),
                'lock' => route('studio.rooms.lock', $room->id),
                'media' => route('studio.rooms.media', $room->id),
                'muteAll' => route('studio.rooms.mute-all', $room->id),
                'recordingStart' => route('studio.rooms.recording.start', $room->id),
                'recordingStop' => route('studio.rooms.recording.stop', $room->id),
                // Browser recording (no Egress): flag + chunked upload + save as a room recording.
                'recordingBrowser' => route('studio.rooms.recording.browser', $room->id),
                'extend' => route('studio.rooms.extend', $room->id),
                'recordingStore' => route('studio.rooms.recordings.store', $room->id),
                'uploadInit' => route('studio.uploads.init'),
                'uploadChunk' => route('studio.uploads.chunk', ['token' => '__TOKEN__']),
                'uploadComplete' => route('studio.uploads.complete', ['token' => '__TOKEN__']),
                'uploadAbort' => route('studio.uploads.abort', ['token' => '__TOKEN__']),
                'materials' => route('studio.rooms.materials.store', $room->id),
                'materialDestroy' => route('studio.rooms.materials.destroy', ['room' => $room->id, 'material' => '__ID__']),
                'remove' => $userTemplate('studio.rooms.participants.remove'),
                'permissions' => $userTemplate('studio.rooms.participants.permissions'),
                'mute' => $userTemplate('studio.rooms.participants.mute'),
            ];
        }

        return [
            'urls' => $urls,
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'status' => $room->status,
                'status_label' => $room->statusLabel(),
                'started_at' => $room->started_at?->toIso8601String(),
                'scheduled_at' => $room->scheduled_at?->toIso8601String(),
                'scheduled_label' => $room->scheduled_at?->format('D, d M Y · H:i'),
                'duration_minutes' => (int) $room->duration_minutes,
                'ends_at' => app(RoomService::class)->endsAt($room)?->toIso8601String(),
                'allow_participant_media' => (bool) $room->allow_participant_media,
                'allow_screen_share' => (bool) $room->allow_screen_share,
                'is_locked' => (bool) $room->is_locked,
                'is_recording' => false,
                'chat_enabled' => (bool) $room->chat_enabled,
                'questions_enabled' => (bool) $room->questions_enabled,
                'cancel_reason' => $room->isCancelled() ? $room->cancel_reason : null,
            ],
            'viewer' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_manager' => $isManager,
                'identity' => $this->live->identityFor($user),
            ],
            'pollMs' => max(1000, (int) config('learning.poll_interval_ms', 4000)),
            'presenceMs' => max(5000, (int) config('learning.presence_interval_ms', 15000)),
            'provider' => [
                'label' => $this->live->label(),
                'configured' => $status['configured'],
                'supportsRecording' => $isManager && $this->live->supportsRecording(),
                // Setup problems are for the people who can fix them.
                'setupWarning' => $isManager && ! $status['configured']
                    ? 'The live video server is not configured yet: '.implode(' ', $status['issues'])
                    : null,
            ],
            'materials' => $this->materials($room),
            'maxMessageLength' => RoomService::MAX_MESSAGE_LENGTH,
            'maxMaterialMb' => max(1, (int) config('learning.max_resource_mb', 50)),
            'materialExtensions' => array_values((array) config('learning.resource_extensions', [])),
        ];
    }

    /** @return list<array<string,mixed>> */
    protected function materials(LearningRoom $room): array
    {
        return $room->materials()->limit(50)->get()
            ->each(fn (LearningRoomMaterial $m) => $m->setRelation('room', $room))
            ->map(fn (LearningRoomMaterial $m) => RoomMaterialController::payload($m))
            ->values()->all();
    }

    protected function providerUnavailable(LearningRoom $room, User $user, string $details): JsonResponse
    {
        // Configuration details are for the people who can fix them.
        $message = $room->isManageableBy($user) || $user->hasPermission('rooms.manage')
            ? 'The live video server is not configured: '.$details
            : 'Live classes are temporarily unavailable. Please try again later or contact the host.';

        return response()->json([
            'reason' => 'provider_unavailable',
            'message' => $message,
        ], 503);
    }
}
