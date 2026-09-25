<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Live moderation for hosts and room managers: lock the room, room-wide and
 * personal publish rights, server-side mute, and server recording. Every
 * action is authorised here ("moderate") and executed by RoomService, which
 * updates the database first and then tells the self-hosted SFU. JSON for
 * the classroom, redirect-back for the studio / admin pages.
 */
class RoomModerationController extends Controller
{
    public function __construct(protected RoomService $rooms)
    {
    }

    public function lock(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $this->rooms->setLocked($room, (bool) $data['locked'], $request->user());

        $message = $room->is_locked ? 'The room is locked — no new people can join.' : 'The room is unlocked.';

        return $this->done($request, $message, ['is_locked' => (bool) $room->is_locked]);
    }

    /** Room-wide switches: participants' microphone + camera, and screen sharing. */
    public function media(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'allow_participant_media' => ['sometimes', 'boolean'],
            'allow_screen_share' => ['sometimes', 'boolean'],
        ]);

        $this->rooms->setRoomMedia($room, $data, $request->user());

        return $this->done($request, 'Participant media settings updated.', [
            'allow_participant_media' => (bool) $room->allow_participant_media,
            'allow_screen_share' => (bool) $room->allow_screen_share,
        ]);
    }

    /** Personal rights: audio / video / screen = allow (true), deny (false) or room default (null). */
    public function permissions(Request $request, LearningRoom $room, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate([
            'audio' => ['sometimes', 'nullable', 'boolean'],
            'video' => ['sometimes', 'nullable', 'boolean'],
            'screen' => ['sometimes', 'nullable', 'boolean'],
        ]);

        try {
            $effective = $this->rooms->setParticipantPermissions($room, $user, $data, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        return $this->done($request, 'Media rights for '.$user->name.' updated.', [
            'user_id' => $user->id,
            'permissions' => $effective,
        ]);
    }

    public function mute(Request $request, LearningRoom $room, User $user): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate(['kind' => ['required', Rule::in(LiveProvider::SOURCES)]]);

        try {
            $ok = $this->rooms->muteParticipant($room, $user, $data['kind'], $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        if (! $ok) {
            return $this->unavailable($request);
        }

        return $this->done($request, $user->name.' was muted.', ['user_id' => $user->id, 'kind' => $data['kind']]);
    }

    public function muteAll(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        $data = $request->validate(['kind' => ['required', Rule::in(LiveProvider::SOURCES)]]);

        try {
            $count = $this->rooms->muteEveryone($room, $data['kind'], $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        if ($count === null) {
            return $this->unavailable($request);
        }

        $label = ['audio' => 'microphones', 'video' => 'cameras', 'screen' => 'screen shares'][$data['kind']];

        return $this->done($request, 'All participant '.$label.' were turned off.', ['count' => $count]);
    }

    public function startRecording(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        try {
            $this->rooms->startRecording($room, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        return $this->done($request, 'Recording started.', ['is_recording' => true]);
    }

    public function stopRecording(Request $request, LearningRoom $room): JsonResponse|RedirectResponse
    {
        $this->authorize('moderate', $room);

        try {
            $this->rooms->stopRecording($room, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        return $this->done($request, 'Recording stopped — it will appear under Recordings when the file is ready.', ['is_recording' => false]);
    }

    /** @param  array<string,mixed>  $data */
    private function done(Request $request, string $message, array $data = []): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message] + $data)
            : back()->with('success', $message);
    }

    private function failed(Request $request, ValidationException $e): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            throw $e;
        }

        return back()->with('error', collect($e->errors())->flatten()->first() ?? 'That action could not be completed.');
    }

    private function unavailable(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'The live video server could not be reached. Try again in a moment.';

        return $request->expectsJson()
            ? response()->json(['reason' => 'provider_unavailable', 'message' => $message], 503)
            : back()->with('error', $message);
    }
}
