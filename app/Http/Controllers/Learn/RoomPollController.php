<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomPoll;
use App\Services\Learning\RoomPollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A participant's vote on a live-class poll (JSON). Hosts ask and close polls in Studio\RoomPollController. */
class RoomPollController extends Controller
{
    public function __construct(protected RoomPollService $polls)
    {
    }

    public function vote(Request $request, LearningRoom $room, LearningRoomPoll $poll): JsonResponse
    {
        $this->authorize('join', $room);
        abort_unless((int) $poll->learning_room_id === (int) $room->id, 404);

        $data = $request->validate(['option' => ['required', 'integer', 'min:0']]);

        $this->polls->vote($poll->setRelation('room', $room), $request->user(), (int) $data['option']);

        return response()->json(['polls' => $this->polls->feed($room, $request->user())], 201);
    }
}
