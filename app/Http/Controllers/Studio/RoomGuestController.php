<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\GuestAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The host's guest link (on / off, waiting room) and the waiting room door (JSON for the classroom). */
class RoomGuestController extends Controller
{
    public function __construct(protected GuestAccessService $guests)
    {
    }

    public function link(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'waiting_room' => ['sometimes', 'boolean'],
        ]);

        $this->guests->configureRoom($room, (bool) $data['enabled'], array_key_exists('waiting_room', $data) ? (bool) $data['waiting_room'] : null);

        if (! $request->expectsJson()) {
            return back()->with('success', $room->guest_token ? 'Guest link is on. Copy it and share it.' : 'Guest link turned off.');
        }

        return response()->json([
            'guest_link' => $room->guestUrl(),
            'guest_waiting_room' => (bool) $room->guest_waiting_room,
        ]);
    }

    public function admit(Request $request, LearningRoom $room, User $user): JsonResponse
    {
        $this->authorize('moderate', $room);

        return response()->json(['admitted' => $this->guests->admit($room, $user)]);
    }

    public function admitAll(Request $request, LearningRoom $room): JsonResponse
    {
        $this->authorize('moderate', $room);

        return response()->json(['admitted' => $this->guests->admit($room)]);
    }

    public function deny(Request $request, LearningRoom $room, User $user): JsonResponse
    {
        $this->authorize('moderate', $room);

        $this->guests->deny($room, $user);

        return response()->json(['denied' => true]);
    }
}
