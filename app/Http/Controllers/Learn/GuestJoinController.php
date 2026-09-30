<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Services\Learning\GuestAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Joining a meeting through its guest link: no account, just a name. The
 * guest then waits at the door until the host lets them in (when the room's
 * waiting room is on) and continues in the normal classroom page.
 */
class GuestJoinController extends Controller
{
    public function __construct(protected GuestAccessService $guests)
    {
    }

    public function show(Request $request, string $token)
    {
        $room = $this->guests->roomForToken($token);

        if (! $room) {
            return response()->view('learn.guest.unavailable', [], 404);
        }

        $user = $request->user();

        if ($user && $user->isGuest()) {
            return (int) $user->guest_room_id === (int) $room->id
                ? $this->next($room, $token)
                : $this->form($room, $token); // a guest of another meeting starts afresh
        }

        // Members with an account just go in (when the room is open to them).
        if ($user && $room->isVisibleTo($user)) {
            return redirect()->route('learn.rooms.live', $room);
        }

        return $this->form($room, $token);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $room = $this->guests->roomForToken($token);
        abort_unless($room, 404);

        $data = $request->validate(['name' => ['required', 'string', 'max:'.GuestAccessService::NAME_MAX]]);

        $guest = $this->guests->createGuest($room, $data['name']);

        // Signs out any earlier guest identity in this browser.
        Auth::guard('web')->login($guest);
        $request->session()->regenerate();

        return $this->next($room, $token);
    }

    public function wait(Request $request, string $token)
    {
        $room = $this->guests->roomForToken($token);
        $user = $request->user();

        if (! $room || ! $user || ! $user->isGuest() || (int) $user->guest_room_id !== (int) $room->id) {
            return redirect()->route('guest.join', $token);
        }

        if ($this->guests->isAdmitted($user)) {
            return redirect()->route('learn.rooms.live', $room);
        }

        return view('learn.guest.wait', [
            'room' => $room,
            'token' => $token,
            'statusUrl' => route('guest.status', $token),
            'liveUrl' => route('learn.rooms.live', $room),
        ]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $room = $this->guests->roomForToken($token);
        $user = $request->user();

        if (! $room || ! $user || ! $user->isGuest() || (int) $user->guest_room_id !== (int) $room->id) {
            return response()->json(['state' => 'ended'], 404);
        }

        // Keeps them on the host's "waiting" list while this page is open.
        $user->timestamps = false;
        $user->forceFill(['last_seen_at' => now()])->save();

        return response()->json($this->guests->status($user->refresh(), $room));
    }

    public function ended()
    {
        return view('learn.guest.ended');
    }

    private function form(LearningRoom $room, string $token)
    {
        return view('learn.guest.join', [
            'room' => $room->loadMissing('host:id,name'),
            'token' => $token,
        ]);
    }

    private function next(LearningRoom $room, string $token): RedirectResponse
    {
        return $this->guests->isAdmitted(request()->user())
            ? redirect()->route('learn.rooms.live', $room)
            : redirect()->route('guest.wait', $token);
    }
}
