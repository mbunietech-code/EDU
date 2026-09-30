<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomBoardStroke;
use App\Services\Learning\RoomBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The live class whiteboard (JSON): read the strokes, save a stroke, undo one. The host's switches are in Studio\RoomBoardController. */
class RoomBoardController extends Controller
{
    public function __construct(protected RoomBoardService $board)
    {
    }

    public function index(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('view', $room);

        $after = (int) ($request->validate(['after' => ['nullable', 'integer', 'min:0']])['after'] ?? 0);

        return response()->json($this->board->strokes($room, $after));
    }

    public function store(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('join', $room);

        $stroke = $this->board->addStroke($room, $request->user(), $request->only(['uid', 'c', 'w', 'p', 's']));

        return response()->json(['id' => $stroke->id, 'uid' => $stroke->uid], 201);
    }

    public function destroy(Request $request, LearningRoom $room, LearningRoomBoardStroke $stroke): JsonResponse
    {
        $this->authorize('join', $room);

        $this->board->deleteStroke($room, $request->user(), $stroke);

        return response()->json($this->board->state($room));
    }
}
