<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Services\Learning\RoomBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The host opens / closes the whiteboard, lets everyone draw, or clears it (JSON for the classroom). */
class RoomBoardController extends Controller
{
    public function __construct(protected RoomBoardService $board)
    {
    }

    public function update(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'all_can_draw' => ['sometimes', 'boolean'],
            'clear' => ['sometimes', 'boolean'],
        ]);

        $this->board->update($room, $data);

        return response()->json($this->board->state($room));
    }
}
