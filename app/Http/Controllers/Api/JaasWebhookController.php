<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Learning\DownloadRoomRecording;
use App\Models\ActivityLog;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Services\Learning\LiveProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * JaaS (8x8) webhook receiver. Verifies X-Jaas-Signature via
 * LiveProvider::verifyJaasWebhook() and, on RECORDING_UPLOADED, queues
 * DownloadRoomRecording (idempotent on recordingSessionId, falling back to
 * the event's idempotencyKey). Every other event is acknowledged and ignored,
 * so 8x8 does not keep retrying it.
 */
class JaasWebhookController extends Controller
{
    public function __invoke(Request $request, LiveProvider $live): JsonResponse
    {
        $raw = $request->getContent();

        if (! $live->verifyJaasWebhook($raw, $request->header('X-Jaas-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = json_decode($raw, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        if (($event['eventType'] ?? null) !== 'RECORDING_UPLOADED') {
            return response()->json(['status' => 'ignored']);
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $externalId = trim((string) ($data['recordingSessionId'] ?? $event['idempotencyKey'] ?? ''));
        $link = $data['preAuthenticatedLink'] ?? null;

        if ($externalId === '' || strlen($externalId) > 191 || ! is_string($link) || ! Str::startsWith($link, 'https://')) {
            return response()->json(['message' => 'The recording event is incomplete.'], 422);
        }

        if (LearningRoomRecording::query()->where('external_id', $externalId)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $room = $this->roomFor((string) ($event['fqn'] ?? ''));

        if (! $room) {
            return response()->json(['status' => 'ignored']);
        }

        $session = LearningRoomSession::query()
            ->where('learning_room_id', $room->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        $duration = filter_var($data['durationSec'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        try {
            $recording = LearningRoomRecording::create([
                'learning_room_id' => $room->id,
                'learning_room_session_id' => $session?->id,
                'source' => 'jaas',
                'status' => 'processing',
                'external_id' => $externalId,
                'external_url' => $link,
                'duration_seconds' => $duration === false ? null : $duration,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']); // a concurrent retry got there first
        }

        ActivityLog::log('learning_room_recording_received', 'LearningRoom', $room->id, [
            'recording_id' => $recording->id,
            'external_id' => $externalId,
        ]);

        DownloadRoomRecording::dispatch($recording->id);

        return response()->json(['status' => 'queued', 'recording_id' => $recording->id]);
    }

    /** Our room behind a JaaS fqn "{appId}/{provider_room}", or null when it is not ours. */
    private function roomFor(string $fqn): ?LearningRoom
    {
        $appId = trim((string) config('learning.live.jaas.app_id'));
        $prefix = $appId.'/';

        if ($appId === '' || ! Str::startsWith($fqn, $prefix)) {
            return null;
        }

        $providerRoom = Str::lower(substr($fqn, strlen($prefix)));

        return $providerRoom === ''
            ? null
            : LearningRoom::query()->where('provider_room', $providerRoom)->first();
    }
}
