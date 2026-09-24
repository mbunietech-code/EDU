<?php

namespace App\Http\Controllers\Studio;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\LearningRoomSession;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Start / end / cancel a room, host announcements and session records.
 *
 * JSON callers (the classroom page) get JSON — RoomService's
 * ValidationExceptions render as 422 there; form posts are sent back with the
 * message flashed as an error.
 */
class RoomSessionController extends Controller
{
    private const MAX_ANNOUNCEMENT = 1000;

    public function __construct(protected RoomService $rooms)
    {
    }

    public function start(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('start', $room);

        try {
            $session = $this->rooms->start($room, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'live',
                'session_id' => $session->id,
                'started_at' => $session->started_at?->toIso8601String(),
                'live_url' => route('learn.rooms.live', $room),
            ]);
        }

        return redirect()->route('learn.rooms.live', $room)->with('success', 'The room is live. Learners can join now.');
    }

    public function end(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('end', $room);

        try {
            $this->rooms->end($room, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'completed', 'message' => 'Session ended.']);
        }

        return redirect()->route('studio.rooms.show', $room)->with('success', 'Session ended. Attendance has been saved.');
    }

    public function cancel(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $room);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.max' => 'Keep the reason under 500 characters.',
        ]);

        $wasScheduled = $room->isScheduled();

        try {
            $this->rooms->cancel($room, $request->user(), $data['reason'] ?? null);
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => 'cancelled']);
        }

        return redirect()->route('studio.rooms.show', $room)->with('success', $wasScheduled
            ? 'Room cancelled — learners who could join are being told.'
            : 'Room cancelled.');
    }

    public function announce(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAX_ANNOUNCEMENT],
        ], [
            'body.required' => 'Write the announcement first.',
            'body.max' => 'Announcements may be at most '.self::MAX_ANNOUNCEMENT.' characters.',
        ]);

        if (! $room->isLive()) {
            throw ValidationException::withMessages(['body' => 'Announcements can only be posted while the room is live.']);
        }

        $message = $this->rooms->postMessage($room, $request->user(), 'announcement', $data['body']);

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->rooms->messagePayload($message, $request->user())], 201);
        }

        return back()->with('success', 'Announcement posted to everyone in the room.');
    }

    public function destroy(Request $request, LearningRoom $room, LearningRoomSession $session, LearningDeletionService $deletions): RedirectResponse
    {
        $this->authorize('manage', $room);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Give a reason for deleting this session record.',
            'reason.min' => 'The reason must be at least 3 characters.',
        ]);

        try {
            $deletions->deleteSession($session, trim($data['reason']));
        } catch (LearningDeletionBlocked $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to(route('studio.rooms.show', $room).'#sessions')
            ->with('success', 'Session record deleted with its attendance.');
    }

    /** JSON → rethrow (422); forms → back with the first message as an error flash. */
    private function failed(Request $request, ValidationException $e): RedirectResponse
    {
        if ($request->expectsJson()) {
            throw $e;
        }

        return back()->with('error', collect($e->errors())->flatten()->first() ?? 'That did not work.');
    }
}
