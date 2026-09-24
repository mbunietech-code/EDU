<?php

namespace App\Http\Controllers\Learn;

use App\Exceptions\Learning\RoomAccessException;
use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The live classroom page and its JSON endpoints (join, presence, leave, feed).
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

        return view('learn.rooms.live', [
            'room' => $room,
            'config' => $config,
            'isManager' => $config['viewer']['is_manager'],
            'descriptionHtml' => $room->description
                ? Str::markdown($room->description, ['html_input' => 'escape', 'allow_unsafe_links' => false])
                : null,
        ]);
    }

    public function join(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('join', $room);

        $user = $request->user();
        $status = $this->live->status();

        // Refuse before touching attendance when the provider cannot issue a call.
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

        $data = $request->validate([
            'jitsi_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        return response()->json($this->rooms->presence($room, $request->user(), $data['jitsi_id'] ?? null));
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

        return response()->json($this->rooms->feed(
            $room,
            $request->user(),
            (int) ($data['after'] ?? 0),
            $data['since'] ?? null,
        ));
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

        $urls = [
            'room' => route('learn.rooms.show', $room),
            'rooms' => route('learn.rooms.index'),
            'join' => route('learn.rooms.join', $room),
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
                'remove' => str_replace('999999999', '__ID__',
                    route('studio.rooms.participants.remove', ['room' => $room->id, 'user' => 999999999])),
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
                'allow_participant_media' => (bool) $room->allow_participant_media,
                'chat_enabled' => (bool) $room->chat_enabled,
                'questions_enabled' => (bool) $room->questions_enabled,
                'cancel_reason' => $room->isCancelled() ? $room->cancel_reason : null,
            ],
            'viewer' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_manager' => $isManager,
            ],
            'pollMs' => max(1000, (int) config('learning.poll_interval_ms', 4000)),
            'presenceMs' => max(5000, (int) config('learning.presence_interval_ms', 15000)),
            'provider' => [
                'label' => $this->live->label(),
                'supportsRecording' => $this->live->supportsRecording(),
                'isDemo' => $this->live->isDemo(),
                'demoWarning' => $isManager && $this->live->isDemo() ? LiveProvider::DEMO_WARNING.'.' : null,
            ],
            'maxMessageLength' => RoomService::MAX_MESSAGE_LENGTH,
        ];
    }

    protected function providerUnavailable(LearningRoom $room, User $user, string $details): JsonResponse
    {
        // Configuration details are for the people who can fix them.
        $message = $room->isManageableBy($user) || $user->hasPermission('rooms.manage')
            ? 'The live class provider is not configured: '.$details
            : 'Live classes are temporarily unavailable. Please try again later or contact the host.';

        return response()->json([
            'reason' => 'provider_unavailable',
            'message' => $message,
        ], 503);
    }
}
