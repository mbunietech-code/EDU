<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\GuestAccessService;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomBreakoutService;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The host's controls inside a live class in the app, same services as the
 * web classroom: one participant (rights, mute, hand, remove), the guest
 * waiting room and breakout rooms. Everything needs the "moderate" ability.
 */
class LiveHostController extends Controller
{
    public function __construct(protected RoomService $rooms)
    {
    }

    public function permissions(Request $request, string $slug, User $user): JsonResponse
    {
        $room = $this->room($request, $slug);
        $data = $request->validate([
            'audio' => ['sometimes', 'nullable', 'boolean'],
            'video' => ['sometimes', 'nullable', 'boolean'],
            'screen' => ['sometimes', 'nullable', 'boolean'],
        ]);

        $effective = $this->rooms->setParticipantPermissions($room, $user, $data, $request->user());

        return response()->json([
            'message' => 'Media rights for '.$user->name.' updated.',
            'user_id' => $user->id,
            'permissions' => $effective,
        ]);
    }

    public function mute(Request $request, string $slug, User $user): JsonResponse
    {
        $room = $this->room($request, $slug);
        $data = $request->validate(['kind' => ['required', Rule::in(LiveProvider::SOURCES)]]);

        abort_unless(
            $this->rooms->muteParticipant($room, $user, $data['kind'], $request->user()),
            503,
            'The live video server could not be reached. Try again.',
        );

        return response()->json(['message' => $user->name.' was muted.', 'user_id' => $user->id, 'kind' => $data['kind']]);
    }

    public function lowerHand(Request $request, string $slug, User $user): JsonResponse
    {
        $room = $this->room($request, $slug);
        $this->rooms->lowerHands($room, $user, $request->user());

        return response()->json(['message' => $user->name.'’s hand is lowered.', 'user_id' => $user->id]);
    }

    /** Also disconnects them from the SFU; they cannot rejoin this session. */
    public function remove(Request $request, string $slug, User $user): JsonResponse
    {
        $room = $this->room($request, $slug);
        $this->rooms->removeParticipant($room, $user, $request->user());

        return response()->json(['message' => $user->name.' was removed and cannot rejoin this session.', 'user_id' => $user->id]);
    }

    // --- Guests -----------------------------------------------------------

    public function guestLink(Request $request, string $slug, GuestAccessService $guests): JsonResponse
    {
        $room = $this->room($request, $slug);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'waiting_room' => ['sometimes', 'boolean'],
        ]);

        $guests->configureRoom($room, (bool) $data['enabled'], array_key_exists('waiting_room', $data) ? (bool) $data['waiting_room'] : null);

        return response()->json([
            'message' => $room->guest_token ? 'Guest link is on. Share it with your guests.' : 'Guest link turned off.',
            'link' => $room->guestUrl(),
            'waiting_room' => (bool) $room->guest_waiting_room,
        ]);
    }

    public function admit(Request $request, string $slug, GuestAccessService $guests, ?User $user = null): JsonResponse
    {
        $room = $this->room($request, $slug);
        $count = $guests->admit($room, $user);

        return response()->json(['message' => $count === 1 ? '1 guest let in.' : $count.' guests let in.', 'admitted' => $count]);
    }

    public function deny(Request $request, string $slug, User $user, GuestAccessService $guests): JsonResponse
    {
        $room = $this->room($request, $slug);
        $guests->deny($room, $user);

        return response()->json(['message' => $user->name.' was not let in.', 'denied' => true]);
    }

    // --- Breakout rooms ---------------------------------------------------

    /** Number of rooms plus who goes where ({user_id: room | null}), or shuffle everyone. */
    public function breakouts(Request $request, string $slug, RoomBreakoutService $breakouts): JsonResponse
    {
        $room = $this->room($request, $slug);
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.RoomBreakoutService::MAX_ROOMS],
            'shuffle' => ['sometimes', 'boolean'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*' => ['nullable', 'integer', 'min:0', 'max:'.RoomBreakoutService::MAX_ROOMS],
        ]);

        if (! empty($data['shuffle'])) {
            $breakouts->shuffle($room, (int) $data['count']);
        } else {
            $breakouts->configure($room, (int) $data['count'], $data['assignments'] ?? []);
        }

        return $this->breakoutState($room, $breakouts, 'Breakout rooms saved.');
    }

    public function openBreakouts(Request $request, string $slug, RoomBreakoutService $breakouts): JsonResponse
    {
        $room = $this->room($request, $slug);
        $breakouts->open($room);

        return $this->breakoutState($room, $breakouts, 'Breakout rooms are open.');
    }

    public function closeBreakouts(Request $request, string $slug, RoomBreakoutService $breakouts): JsonResponse
    {
        $room = $this->room($request, $slug);
        $breakouts->close($room);

        return $this->breakoutState($room, $breakouts, 'Everyone is back in the main room.');
    }

    private function breakoutState(LearningRoom $room, RoomBreakoutService $breakouts, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'breakouts' => $breakouts->state($breakouts->openSession($room))]);
    }

    private function room(Request $request, string $slug): LearningRoom
    {
        $room = LearningRoom::query()->where('slug', $slug)->firstOrFail();
        abort_unless(Gate::forUser($request->user())->allows('moderate', $room), 403);

        return $room;
    }
}
