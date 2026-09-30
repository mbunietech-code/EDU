<?php

namespace App\Http\Middleware;

use App\Models\LearningRoom;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meeting guests (joined through a guest link with just a name) may use their
 * one room's classroom and nothing else on the site. Anything else sends them
 * back to their room; when the link or the whole feature was switched off, or
 * the host turned them away, they are signed out.
 */
class RestrictGuests
{
    /** Route names a guest may use (the room itself is checked by the policies). */
    private const ALLOWED = [
        'guest.*',
        'logout',
        'learn.rooms.live',
        'learn.rooms.join',
        'learn.rooms.token',
        'learn.rooms.presence',
        'learn.rooms.leave',
        'learn.rooms.feed',
        'learn.rooms.hand',
        'learn.rooms.messages.*',
        'learn.rooms.polls.vote',
        'learn.rooms.board',
        'learn.rooms.board.*',
        'learn.rooms.materials.download',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isGuest()) {
            return $next($request);
        }

        $room = LearningRoom::query()->find($user->guest_room_id);

        // Link turned off, feature switched off, room gone or the host said no: sign the guest out.
        if (! $room || ! $room->isVisibleTo($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['reason' => 'guest_ended', 'message' => 'Your guest access to this meeting has ended.'], 403)
                : redirect()->route('guest.ended');
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return $request->expectsJson() || ! $request->isMethod('GET')
            ? response()->json(['message' => 'Guests can only use their meeting.'], 403)
            : redirect()->route('learn.rooms.live', $room);
    }
}
