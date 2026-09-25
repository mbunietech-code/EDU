<?php

namespace Tests\Feature\Learning\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Configures the self-hosted live video stack for tests (LiveKit SFU on a
 * local address + coturn) and fakes its server API, so no test ever talks to
 * a real server. Recorded calls can be asserted with Http::assertSent().
 *
 * Per-call answers: set $this->liveApi['RoomService/ListParticipants'] = [...]
 * (an array body, an HTTP status int, or a Closure(Request)); set
 * $this->liveApiDown = true to simulate an unreachable server.
 */
trait UsesLiveServer
{
    protected const LIVE_KEY = 'APItestkey';

    protected const LIVE_SECRET = 'test-secret-that-is-at-least-32-characters-long';

    protected const TURN_SECRET = 'turn-static-auth-secret';

    /** @var array<string,mixed> Twirp "Service/Method" => response */
    protected array $liveApi = [];

    protected bool $liveApiDown = false;

    private bool $liveApiFaked = false;

    /** @param  array<string,mixed>  $overrides */
    protected function useLiveServer(array $overrides = []): void
    {
        config(array_merge([
            'learning.live.server_url' => 'wss://live.example.test',
            'learning.live.api_url' => 'https://live.example.test',
            'learning.live.api_key' => self::LIVE_KEY,
            'learning.live.api_secret' => self::LIVE_SECRET,
            'learning.live.token_ttl_minutes' => 10,
            'learning.live.ice.stun_urls' => 'stun:turn.example.test:3478',
            'learning.live.ice.turn_urls' => 'turn:turn.example.test:3478?transport=udp,turns:turn.example.test:5349?transport=tcp',
            'learning.live.ice.turn_secret' => self::TURN_SECRET,
            'learning.live.ice.turn_username' => null,
            'learning.live.ice.turn_credential' => null,
            'learning.live.ice.transport_policy' => 'all',
            'learning.live.recording.enabled' => false,
            'learning.live.recording.import_dir' => null,
        ], $overrides));

        if (! $this->liveApiFaked) {
            $this->liveApiFaked = true;
            Http::fake(function (Request $request) {
                if ($this->liveApiDown) {
                    throw new ConnectionException('The live server is down (test).');
                }

                $method = preg_replace('#^.*/twirp/livekit\.#', '', $request->url());
                $answer = $this->liveApi[$method] ?? [];

                if ($answer instanceof \Closure) {
                    return $answer($request);
                }

                return is_int($answer) ? Http::response(['msg' => 'error'], $answer) : Http::response($answer, 200);
            });
        }
    }

    /** No live server at all (fresh install). */
    protected function withoutLiveServer(): void
    {
        config([
            'learning.live.server_url' => null,
            'learning.live.api_url' => null,
            'learning.live.api_key' => null,
            'learning.live.api_secret' => null,
        ]);
    }

    /** @return array<string,mixed> the decoded, verified payload of an HS256 token signed with the test secret */
    protected function liveClaims(string $token): array
    {
        $claims = \App\Support\Jwt::decode($token, self::LIVE_SECRET);
        $this->assertIsArray($claims, 'The token is not a valid HS256 token signed with the API secret.');

        return $claims;
    }
}
