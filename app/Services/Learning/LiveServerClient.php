<?php

namespace App\Services\Learning;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Laravel → self-hosted LiveKit server API (Twirp: POST
 * {api_url}/twirp/livekit.<Service>/<Method> with a JSON body and a
 * short-lived server token). Used for moderation (remove, mute, change
 * publish rights, end the room) and for recordings (Egress).
 *
 * The database stays the source of truth: every call is best effort. A
 * failure is logged and reported through lastError() — it never throws, so
 * a slow or unreachable SFU cannot break ending a class or removing someone
 * (the join tokens Laravel issues already carry the restriction).
 */
class LiveServerClient
{
    /** LiveKit TrackSource names by our permission key. */
    private const SOURCE_NAMES = [
        'audio' => ['MICROPHONE'],
        'video' => ['CAMERA'],
        'screen' => ['SCREEN_SHARE', 'SCREEN_SHARE_AUDIO'],
    ];

    /** Numeric TrackSource values (protobuf) for payloads that use numbers. */
    private const SOURCE_NUMBERS = ['CAMERA' => 1, 'MICROPHONE' => 2, 'SCREEN_SHARE' => 3, 'SCREEN_SHARE_AUDIO' => 4];

    private ?string $lastError = null;

    public function __construct(protected LiveProvider $provider)
    {
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /** Disconnect one participant (they are also refused a new token by Laravel). */
    public function removeParticipant(string $room, string $identity): bool
    {
        return $this->call('RoomService', 'RemoveParticipant', ['room' => $room, 'identity' => $identity], $this->admin($room)) !== null;
    }

    /** Close the SFU room: every participant is disconnected (and a running Egress stops). */
    public function deleteRoom(string $room): bool
    {
        return $this->call('RoomService', 'DeleteRoom', ['room' => $room], ['roomCreate' => true]) !== null;
    }

    /**
     * Change what a connected participant may publish. Tracks from sources
     * that are no longer allowed are also muted so the change is immediate.
     *
     * @param  array{audio:bool,video:bool,screen:bool}  $permissions
     */
    public function updatePermissions(string $room, string $identity, array $permissions): bool
    {
        $sources = [];
        foreach (self::SOURCE_NAMES as $key => $names) {
            if ($permissions[$key] ?? false) {
                array_push($sources, ...$names);
            }
        }

        $ok = $this->call('RoomService', 'UpdateParticipant', [
            'room' => $room,
            'identity' => $identity,
            'permission' => [
                'can_subscribe' => true,
                'can_publish' => $sources !== [],
                'can_publish_data' => true,
                'can_publish_sources' => $sources,
            ],
        ], $this->admin($room)) !== null;

        foreach (self::SOURCE_NAMES as $key => $names) {
            if (! ($permissions[$key] ?? false)) {
                $this->muteSource($room, $identity, $key);
            }
        }

        return $ok;
    }

    /**
     * Server-side mute of one participant's microphone ("audio"), camera
     * ("video") or screen share ("screen"). They can unmute again only if
     * they are still allowed to publish that source.
     */
    public function muteSource(string $room, string $identity, string $kind): bool
    {
        $participant = collect($this->listParticipants($room))->firstWhere('identity', $identity);
        if (! $participant) {
            return $this->lastError === null; // not connected: nothing to mute
        }

        $ok = true;
        foreach ($this->tracksOf($participant, $kind) as $trackSid) {
            $ok = $this->call('RoomService', 'MutePublishedTrack', [
                'room' => $room,
                'identity' => $identity,
                'track_sid' => $trackSid,
                'muted' => true,
            ], $this->admin($room)) !== null && $ok;
        }

        return $ok;
    }

    /**
     * Mute a source for everyone except the given identities (the hosts).
     *
     * @param  list<string>  $except
     * @return int participants whose tracks were muted
     */
    public function muteEveryone(string $room, string $kind, array $except = []): int
    {
        $count = 0;

        foreach ($this->listParticipants($room) as $participant) {
            $identity = (string) ($participant['identity'] ?? '');
            if ($identity === '' || in_array($identity, $except, true)) {
                continue;
            }

            $tracks = $this->tracksOf($participant, $kind);
            foreach ($tracks as $trackSid) {
                $this->call('RoomService', 'MutePublishedTrack', [
                    'room' => $room,
                    'identity' => $identity,
                    'track_sid' => $trackSid,
                    'muted' => true,
                ], $this->admin($room));
            }
            $count += $tracks === [] ? 0 : 1;
        }

        return $count;
    }

    /** @return list<array<string,mixed>> participants currently connected to the SFU room */
    public function listParticipants(string $room): array
    {
        $response = $this->call('RoomService', 'ListParticipants', ['room' => $room], $this->admin($room));

        return array_values(array_filter((array) ($response['participants'] ?? []), 'is_array'));
    }

    /** Reachability check for status panels: true when the server API answers. */
    public function ping(): bool
    {
        return $this->call('RoomService', 'ListRooms', ['names' => []], ['roomList' => true]) !== null;
    }

    /**
     * Start a composite recording of the room to an MP4 file inside the
     * Egress container. Returns the egress id, or null on failure.
     */
    public function startRecording(string $room): ?string
    {
        $dir = rtrim((string) config('learning.live.recording.egress_output_dir', '/out'), '/');
        $layout = config('learning.live.recording.layout') === 'grid' ? 'grid' : 'speaker';

        $response = $this->call('Egress', 'StartRoomCompositeEgress', [
            'room_name' => $room,
            'layout' => $layout,
            'file_outputs' => [[
                'file_type' => 'MP4',
                'filepath' => $dir.'/'.$room.'-{time}.mp4',
            ]],
        ], ['roomRecord' => true, 'room' => $room]);

        $id = $response['egress_id'] ?? $response['egressId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function stopRecording(string $egressId): bool
    {
        return $this->call('Egress', 'StopEgress', ['egress_id' => $egressId], ['roomRecord' => true]) !== null;
    }

    // --- Internals ------------------------------------------------------
    /** @return array<string,mixed> */
    private function admin(string $room): array
    {
        return ['roomAdmin' => true, 'room' => $room, 'roomList' => true];
    }

    /**
     * Track sids of one participant for a kind (audio | video | screen).
     * Accepts both snake_case and camelCase JSON, and enum names or numbers.
     *
     * @param  array<string,mixed>  $participant
     * @return list<string>
     */
    private function tracksOf(array $participant, string $kind): array
    {
        $wanted = self::SOURCE_NAMES[$kind] ?? [];
        $numbers = array_map(fn ($n) => self::SOURCE_NUMBERS[$n], $wanted);
        $sids = [];

        foreach ((array) ($participant['tracks'] ?? []) as $track) {
            if (! is_array($track)) {
                continue;
            }

            $source = $track['source'] ?? null;
            $matches = is_string($source) ? in_array(strtoupper($source), $wanted, true) : in_array((int) $source, $numbers, true);
            $sid = $track['sid'] ?? null;

            if ($matches && is_string($sid) && $sid !== '' && ! ($track['muted'] ?? false)) {
                $sids[] = $sid;
            }
        }

        return $sids;
    }

    /**
     * POST one Twirp call. Returns the decoded JSON (possibly []) or null on
     * any failure, with the reason in lastError().
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $grants
     * @return array<string,mixed>|null
     */
    private function call(string $service, string $method, array $payload, array $grants): ?array
    {
        $this->lastError = null;

        if (! $this->provider->isConfigured() || ! ($base = $this->provider->apiUrl())) {
            $this->lastError = 'The live video server is not configured.';

            return null;
        }

        try {
            $response = Http::timeout(max(1, (int) config('learning.live.api_timeout_seconds', 5)))
                ->withToken($this->provider->serverToken($grants))
                ->acceptJson()
                ->asJson()
                ->post($base.'/twirp/livekit.'.$service.'/'.$method, $payload);
        } catch (ConnectionException $e) {
            return $this->failed($service, $method, 'The live video server could not be reached.', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->failed($service, $method, 'The live video server request failed.', $e->getMessage());
        }

        if ($response->failed()) {
            $message = (string) ($response->json('msg') ?? $response->json('message') ?? '');

            return $this->failed($service, $method, 'The live video server refused the request'.($message !== '' ? ': '.$message : '.'), 'HTTP '.$response->status());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function failed(string $service, string $method, string $message, string $detail): null
    {
        $this->lastError = $message;
        Log::warning('Live server API call failed', ['call' => $service.'/'.$method, 'detail' => $detail]);

        return null;
    }
}
