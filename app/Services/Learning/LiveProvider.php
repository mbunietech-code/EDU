<?php

namespace App\Services\Learning;

use App\Models\LearningRoom;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Support\Str;

/**
 * Our self-hosted live video stack, seen from Laravel:
 *
 *   browser ──HTTPS──▶ Laravel (auth, permissions, chat, attendance, tokens)
 *      │
 *      └──WSS + WebRTC──▶ LiveKit SFU on our server ◀──TURN── coturn on our server
 *
 * Laravel never relays media. It signs a short-lived LiveKit access token
 * for each join, carrying exactly the rights this user has (which sources
 * they may publish; never room-admin rights — moderation always goes through
 * Laravel and LiveServerClient). It also hands out STUN/TURN servers with
 * expiring TURN credentials and verifies the SFU's webhooks.
 *
 * Configuration: config('learning.live'), documented in deploy/live-server/README.md.
 */
class LiveProvider
{
    /** Sources a participant may be allowed to publish. */
    public const SOURCES = ['audio', 'video', 'screen'];

    /** Tokens become valid slightly in the past so a client clock running behind is not rejected. */
    private const TOKEN_LEEWAY_SECONDS = 10;

    /** Server-API tokens only need to live for one request. */
    private const SERVER_TOKEN_TTL_SECONDS = 60;

    public function name(): string
    {
        return 'livekit';
    }

    /** Human label for status panels, e.g. "Self-hosted LiveKit (live.example.com)". */
    public function label(): string
    {
        $host = parse_url((string) $this->serverUrl(), PHP_URL_HOST);

        return 'Self-hosted LiveKit'.($host ? ' ('.$host.')' : '');
    }

    /** Browser-facing signalling URL: wss://live.example.com (ws:// only for local development). */
    public function serverUrl(): ?string
    {
        $url = rtrim(trim((string) config('learning.live.server_url')), '/');

        return $url !== '' ? $url : null;
    }

    /**
     * The signalling URL as the current visitor must use it. Local development
     * convenience: when LIVE_SERVER_URL points at this machine (127.0.0.1 /
     * localhost) and the page was opened through a private LAN address (e.g.
     * http://192.168.1.176:8000 from a phone), the same host is used for the
     * SFU — "127.0.0.1" on another device would mean that device itself.
     */
    public function browserServerUrl(): ?string
    {
        $url = $this->serverUrl();
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        if (! $url || ! in_array($host, ['127.0.0.1', 'localhost', '[::1]', '::1'], true) || ! app()->bound('request')) {
            return $url;
        }

        $visitorHost = strtolower((string) request()->getHost());
        $isLan = preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)\d{1,3}\.\d{1,3}$/', $visitorHost) === 1
            || str_ends_with($visitorHost, '.local');

        return $isLan ? (string) preg_replace('#^(wss?://)[^/:]+#i', '${1}'.$visitorHost, $url) : $url;
    }

    /** Base URL of the SFU's server API (Twirp over HTTP(S)). */
    public function apiUrl(): ?string
    {
        $url = rtrim(trim((string) config('learning.live.api_url')), '/');

        if ($url === '' && ($server = $this->serverUrl())) {
            $url = preg_replace('#^ws(s?)://#i', 'http$1://', $server);
        }

        return $url !== '' ? $url : null;
    }

    public function isConfigured(): bool
    {
        return $this->configurationIssues() === [];
    }

    /** True when LiveKit Egress is enabled for server-side recording. */
    public function supportsRecording(): bool
    {
        return $this->isConfigured() && (bool) config('learning.live.recording.enabled');
    }

    /** The SFU room for a learning room (its random provider_room name). */
    public function roomName(LearningRoom $room): string
    {
        return (string) $room->provider_room;
    }

    /** Stable participant identity for a user — the key the SFU uses for moderation. */
    public function identityFor(User|int $user): string
    {
        return 'user-'.(is_int($user) ? $user : $user->id);
    }

    /** The user id behind an identity we issued, or null for anything else. */
    public function userIdFromIdentity(?string $identity): ?int
    {
        return preg_match('/^user-([1-9]\d{0,18})$/', (string) $identity, $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Signed join token for this user and room.
     *
     * @param  array{audio:bool,video:bool,screen:bool}  $permissions  what the user may publish
     *
     * @throws \RuntimeException when the live server is not configured
     */
    public function token(User $user, LearningRoom $room, array $permissions, bool $moderator): string
    {
        $this->assertConfigured();

        $now = now()->getTimestamp();
        $ttl = max(1, (int) config('learning.live.token_ttl_minutes', 10)) * 60;
        $sources = $this->publishSources($permissions);

        return Jwt::encode([
            'iss' => (string) config('learning.live.api_key'),
            'sub' => $this->identityFor($user),
            'jti' => (string) Str::uuid(),
            'nbf' => $now - self::TOKEN_LEEWAY_SECONDS,
            'exp' => $now + $ttl,
            'name' => Str::limit((string) $user->name, 100, ''),
            'metadata' => json_encode([
                'user_id' => $user->id,
                'role' => $moderator ? 'host' : 'participant',
            ]),
            'video' => [
                'room' => $this->roomName($room),
                'roomJoin' => true,
                'canSubscribe' => true,
                'canPublish' => $sources !== [],
                'canPublishSources' => $sources,
                // Chat is stored by Laravel; data messages only nudge clients to refresh.
                'canPublishData' => true,
                'canUpdateOwnMetadata' => false,
            ],
        ], (string) config('learning.live.api_secret'));
    }

    /**
     * Short-lived token for Laravel's own calls to the SFU server API.
     *
     * @param  array<string,mixed>  $video  grants, e.g. ['roomAdmin' => true, 'room' => 'x']
     */
    public function serverToken(array $video): string
    {
        $this->assertConfigured();

        $now = now()->getTimestamp();

        return Jwt::encode([
            'iss' => (string) config('learning.live.api_key'),
            'sub' => 'laravel',
            'nbf' => $now - self::TOKEN_LEEWAY_SECONDS,
            'exp' => $now + self::SERVER_TOKEN_TTL_SECONDS,
            'video' => $video,
        ], (string) config('learning.live.api_secret'));
    }

    /**
     * RTCIceServer list for this user: our STUN server(s) and our coturn
     * TURN server(s). With TURN_SERVER_SECRET set, credentials follow the
     * TURN REST scheme (username "expiry:identity", HMAC-SHA1 password) so
     * they expire and are never shared between users.
     *
     * @return list<array{urls:list<string>,username?:string,credential?:string}>
     */
    public function iceServers(User $user): array
    {
        $servers = [];
        $stun = $this->urlList('learning.live.ice.stun_urls', ['stun:']);
        $turn = $this->urlList('learning.live.ice.turn_urls', ['turn:', 'turns:']);

        if ($stun !== []) {
            $servers[] = ['urls' => $stun];
        }

        if ($turn !== []) {
            $secret = (string) config('learning.live.ice.turn_secret');

            if ($secret !== '') {
                $expires = now()->addMinutes(max(1, (int) config('learning.live.ice.turn_ttl_minutes', 720)))->getTimestamp();
                $username = $expires.':'.$this->identityFor($user);
                $servers[] = [
                    'urls' => $turn,
                    'username' => $username,
                    'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
                ];
            } elseif ((string) config('learning.live.ice.turn_username') !== '') {
                $servers[] = [
                    'urls' => $turn,
                    'username' => (string) config('learning.live.ice.turn_username'),
                    'credential' => (string) config('learning.live.ice.turn_credential'),
                ];
            }
        }

        return $servers;
    }

    public function iceTransportPolicy(): string
    {
        return config('learning.live.ice.transport_policy') === 'relay' ? 'relay' : 'all';
    }

    /**
     * Everything the classroom needs to connect:
     * ['provider','server_url','token','identity','room_name','ice_servers','ice_transport_policy',
     *  'permissions' => ['audio','video','screen'],'moderator','supports_recording','user' => ['id','name']].
     *
     * @param  array{audio:bool,video:bool,screen:bool}  $permissions
     * @return array<string,mixed>
     */
    public function clientConfig(User $user, LearningRoom $room, array $permissions, bool $moderator): array
    {
        return [
            'provider' => $this->name(),
            'server_url' => $this->browserServerUrl(),
            'token' => $this->token($user, $room, $permissions, $moderator),
            'identity' => $this->identityFor($user),
            'room_name' => $this->roomName($room),
            'ice_servers' => $this->iceServers($user),
            'ice_transport_policy' => $this->iceTransportPolicy(),
            'permissions' => [
                'audio' => (bool) ($permissions['audio'] ?? false),
                'video' => (bool) ($permissions['video'] ?? false),
                'screen' => (bool) ($permissions['screen'] ?? false),
            ],
            'moderator' => $moderator,
            'supports_recording' => $moderator && $this->supportsRecording(),
            'user' => ['id' => $user->id, 'name' => $user->name],
        ];
    }

    /**
     * Health summary for the admin / studio screens:
     * ['provider','label','server_url','configured'=>bool,'recording'=>bool,'turn'=>bool,'issues'=>list<string>,'warnings'=>list<string>].
     *
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $issues = $this->configurationIssues();
        $warnings = [];

        $server = (string) $this->serverUrl();
        if ($server !== '' && str_starts_with(strtolower($server), 'ws://') && ! $this->isLocalUrl($server)) {
            $warnings[] = 'LIVE_SERVER_URL uses ws:// — browsers need wss:// (TLS) for a public server.';
        }

        $hasTurn = $this->urlList('learning.live.ice.turn_urls', ['turn:', 'turns:']) !== [];
        if (! $hasTurn) {
            $warnings[] = 'No TURN server (TURN_SERVER_URL): learners behind strict firewalls or mobile networks may not connect.';
        } elseif ((string) config('learning.live.ice.turn_secret') === '' && (string) config('learning.live.ice.turn_username') === '') {
            $warnings[] = 'TURN_SERVER_URL is set but neither TURN_SERVER_SECRET nor TURN_SERVER_USERNAME — TURN will be skipped.';
        }

        if ((bool) config('learning.live.recording.enabled') && (string) config('learning.live.recording.import_dir') === '') {
            $warnings[] = 'Recording is enabled but LIVE_RECORDING_IMPORT_DIR is empty — finished recordings cannot be imported.';
        }

        return [
            'provider' => $this->name(),
            'label' => $this->label(),
            'server_url' => $this->serverUrl(),
            'configured' => $issues === [],
            'recording' => $this->supportsRecording(),
            'turn' => $hasTurn,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    /**
     * Verify a LiveKit webhook: the Authorization header holds an HS256 token
     * signed with our API secret, issued by our API key, whose "sha256" claim
     * is the base64 SHA-256 of the exact request body.
     */
    public function verifyWebhook(string $rawBody, ?string $authorization): bool
    {
        if (! $this->isConfigured() || $authorization === null || trim($authorization) === '') {
            return false;
        }

        $token = trim(preg_replace('/^Bearer\s+/i', '', trim($authorization)));
        $claims = Jwt::decode($token, (string) config('learning.live.api_secret'));

        if ($claims === null || ($claims['iss'] ?? null) !== (string) config('learning.live.api_key')) {
            return false;
        }

        $expected = base64_encode(hash('sha256', $rawBody, true));

        return is_string($claims['sha256'] ?? null) && hash_equals($expected, $claims['sha256']);
    }

    // --- Internals ------------------------------------------------------
    /** @return list<string> */
    private function configurationIssues(): array
    {
        $issues = [];
        $server = (string) $this->serverUrl();

        if ($server === '') {
            $issues[] = 'LIVE_SERVER_URL is not set (e.g. wss://live.example.com).';
        } elseif (preg_match('#^wss?://[^\s/]+#i', $server) !== 1) {
            $issues[] = 'LIVE_SERVER_URL must start with wss:// (or ws:// for local development).';
        }

        if (trim((string) config('learning.live.api_key')) === '') {
            $issues[] = 'LIVE_SERVER_API_KEY is not set.';
        }

        if ((string) config('learning.live.api_secret') === '') {
            $issues[] = 'LIVE_SERVER_API_SECRET is not set.';
        } elseif (strlen((string) config('learning.live.api_secret')) < 32 && ! $this->isLocalUrl($server)) {
            $issues[] = 'LIVE_SERVER_API_SECRET must be at least 32 characters.';
        }

        return $issues;
    }

    private function assertConfigured(): void
    {
        $issues = $this->configurationIssues();

        if ($issues !== []) {
            throw new \RuntimeException('The live video server is not configured: '.implode(' ', $issues));
        }
    }

    /**
     * @param  array{audio?:bool,video?:bool,screen?:bool}  $permissions
     * @return list<string> LiveKit source names
     */
    private function publishSources(array $permissions): array
    {
        $sources = [];

        if ($permissions['audio'] ?? false) {
            $sources[] = 'microphone';
        }
        if ($permissions['video'] ?? false) {
            $sources[] = 'camera';
        }
        if ($permissions['screen'] ?? false) {
            $sources[] = 'screen_share';
            $sources[] = 'screen_share_audio';
        }

        return $sources;
    }

    /**
     * @param  list<string>  $schemes  accepted URL prefixes
     * @return list<string>
     */
    private function urlList(string $key, array $schemes): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) config($key))),
            fn (string $url) => $url !== '' && Str::startsWith(strtolower($url), $schemes),
        ));
    }

    private function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host) === 1
            || str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }
}
