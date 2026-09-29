<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomPoll;
use App\Services\Learning\RoomPollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The host asks and closes polls / quizzes during a live class (JSON for the classroom). */
class RoomPollController extends Controller
{
    public function __construct(protected RoomPollService $polls)
    {
    }

    public function store(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'question' => ['required', 'string'],
            'options' => ['required', 'array'],
            'options.*' => ['nullable', 'string'],
            'correct_option' => ['nullable', 'integer'],
        ]);

        $this->polls->create(
            $room,
            $request->user(),
            $data['question'],
            $data['options'],
            isset($data['correct_option']) ? (int) $data['correct_option'] : null,
        );

        return response()->json(['polls' => $this->polls->feed($room, $request->user())], 201);
    }

    public function close(Request $request, LearningRoom $room, LearningRoomPoll $poll): JsonResponse
    {
        $this->authorize('moderate', $room);
        abort_unless((int) $poll->learning_room_id === (int) $room->id, 404);

        $this->polls->close($poll);

        return response()->json(['polls' => $this->polls->feed($room, $request->user())]);
    }
}
