<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomMessage;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-room chat and questions (JSON). Announcements are posted by room
 * managers through studio.rooms.announce, never through this endpoint.
 */
class RoomMessageController extends Controller
{
    public function __construct(protected RoomService $rooms)
    {
    }

    public function store(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('view', $room);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:chat,question'],
            'body' => ['required', 'string', 'max:'.RoomService::MAX_MESSAGE_LENGTH],
        ]);

        $user = $request->user();
        $message = $this->rooms->postMessage($room, $user, $data['type'], $data['body']);

        return response()->json($this->rooms->messagePayload($message->setRelation('room', $room), $user), 201);
    }

    public function answer(Request $request, LearningRoom $room, LearningRoomMessage $message): JsonResponse
    {
        $this->authorize('view', $room);
        $message = $this->scoped($room, $message);

        $this->rooms->answerQuestion($message, $request->user());

        return response()->json($this->rooms->messagePayload($message->refresh()->setRelation('room', $room), $request->user()));
    }

    public function destroy(Request $request, LearningRoom $room, LearningRoomMessage $message): JsonResponse
    {
        $this->authorize('view', $room);
        $message = $this->scoped($room, $message);

        $this->rooms->deleteMessage($message, $request->user());

        return response()->json($this->rooms->messagePayload($message->refresh()->setRelation('room', $room), $request->user()));
    }

    /** scopeBindings() already 404s a message of another room; keep the guard explicit. */
    protected function scoped(LearningRoom $room, LearningRoomMessage $message): LearningRoomMessage
    {
        abort_unless((int) $message->learning_room_id === (int) $room->id, 404);

        return $message->setRelation('room', $room)->loadMissing('user:id,name');
    }
}
