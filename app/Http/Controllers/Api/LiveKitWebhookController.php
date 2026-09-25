<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Learning\ImportLiveRecording;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\User;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhooks from our self-hosted LiveKit server (livekit.yaml → webhook.urls).
 * Authenticated by the signed Authorization token (LiveProvider::verifyWebhook),
 * not by a session.
 *
 *  - participant_left: close the attendance of that user (exact time they left).
 *  - egress_ended: complete the recording row and queue ImportLiveRecording.
 *
 * Everything else is acknowledged and ignored, so the server does not retry it.
 * Payload fields are read in both camelCase and snake_case.
 */
class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, LiveProvider $live, RoomService $rooms): JsonResponse
    {
        $raw = $request->getContent();

        if (! $live->verifyWebhook($raw, $request->header('Authorization'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($raw, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        return match ($event['event'] ?? null) {
            'participant_left' => $this->participantLeft($event, $live, $rooms),
            'egress_ended' => $this->egressEnded($event),
            default => response()->json(['status' => 'ignored']),
        };
    }

    /** @param  array<string,mixed>  $event */
    private function participantLeft(array $event, LiveProvider $live, RoomService $rooms): JsonResponse
    {
        $room = $this->roomByName((string) data_get($event, 'room.name', ''));
        $userId = $live->userIdFromIdentity(data_get($event, 'participant.identity'));
        $user = $userId ? User::query()->find($userId) : null;

        if (! $room || ! $user) {
            return response()->json(['status' => 'ignored']);
        }

        $rooms->participantDisconnected($room, $user);

        return response()->json(['status' => 'ok']);
    }

    /** @param  array<string,mixed>  $event */
    private function egressEnded(array $event): JsonResponse
    {
        $info = $event['egressInfo'] ?? $event['egress_info'] ?? null;
        if (! is_array($info)) {
            return response()->json(['message' => 'The egress event is incomplete.'], 422);
        }

        $egressId = trim((string) ($info['egressId'] ?? $info['egress_id'] ?? ''));
        if ($egressId === '' || strlen($egressId) > 100) {
            return response()->json(['message' => 'The egress event is incomplete.'], 422);
        }

        $recording = LearningRoomRecording::query()->where('external_id', $egressId)->first();
        if (! $recording) {
            // Started outside Laravel (e.g. by hand on the server): adopt it if the room is ours.
            $room = $this->roomByName((string) ($info['roomName'] ?? $info['room_name'] ?? ''));
            if (! $room) {
                return response()->json(['status' => 'ignored']);
            }

            $recording = LearningRoomRecording::create([
                'learning_room_id' => $room->id,
                'learning_room_session_id' => LearningRoomSession::query()->where('learning_room_id', $room->id)->orderByDesc('started_at')->value('id'),
                'source' => 'livekit',
                'status' => 'processing',
                'external_id' => $egressId,
            ]);
        }

        LearningRoomSession::query()->where('egress_id', $egressId)->update(['egress_id' => null]);

        if ($recording->isReady()) {
            return response()->json(['status' => 'duplicate']);
        }

        $status = strtoupper((string) ($info['status'] ?? ''));
        $file = $this->firstFile($info);

        if (! in_array($status, ['EGRESS_COMPLETE', '3'], true) || $file === null) {
            $recording->forceFill([
                'status' => 'failed',
                'error' => Str::limit((string) ($info['error'] ?? 'The recording did not finish ('.($status ?: 'unknown status').').'), 1000),
            ])->save();

            return response()->json(['status' => 'failed']);
        }

        // LiveKit reports duration in nanoseconds (int64 as a JSON string).
        $durationNs = $file['duration'] ?? null;
        $recording->forceFill([
            'external_url' => Str::limit((string) ($file['filename'] ?? ''), 1000, ''),
            'duration_seconds' => is_numeric($durationNs) ? (int) round(((float) $durationNs) / 1e9) : $recording->duration_seconds,
            'error' => null,
        ])->save();

        ActivityLog::log('learning_room_recording_received', 'LearningRoom', $recording->learning_room_id, [
            'recording_id' => $recording->id,
            'egress_id' => $egressId,
        ]);

        ImportLiveRecording::dispatch($recording->id);

        return response()->json(['status' => 'queued', 'recording_id' => $recording->id]);
    }

    /**
     * @param  array<string,mixed>  $info
     * @return array<string,mixed>|null
     */
    private function firstFile(array $info): ?array
    {
        $files = $info['fileResults'] ?? $info['file_results'] ?? null;
        $file = is_array($files) && isset($files[0]) && is_array($files[0]) ? $files[0] : ($info['file'] ?? null);

        return is_array($file) && ($file['filename'] ?? '') !== '' ? $file : null;
    }

    private function roomByName(string $name): ?LearningRoom
    {
        return $name !== '' ? LearningRoom::query()->where('provider_room', $name)->first() : null;
    }
}
