<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Services\Learning\RoomBreakoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The host sets up, opens and closes breakout rooms during a live class (JSON for the classroom). */
class RoomBreakoutController extends Controller
{
    public function __construct(protected RoomBreakoutService $breakouts)
    {
    }

    /** Number of rooms plus who goes where ({user_id: room | null}), or shuffle everyone. */
    public function update(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.RoomBreakoutService::MAX_ROOMS],
            'shuffle' => ['sometimes', 'boolean'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*' => ['nullable', 'integer', 'min:0', 'max:'.RoomBreakoutService::MAX_ROOMS],
        ]);

        if (! empty($data['shuffle'])) {
            $this->breakouts->shuffle($room, (int) $data['count']);
        } else {
            $this->breakouts->configure($room, (int) $data['count'], $data['assignments'] ?? []);
        }

        return $this->state($room);
    }

    public function open(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        $this->breakouts->open($room);

        return $this->state($room);
    }

    public function close(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        $this->breakouts->close($room);

        return $this->state($room);
    }

    private function state(LearningRoom $room): JsonResponse
    {
        return response()->json($this->breakouts->state($this->breakouts->openSession($room)));
    }
}
