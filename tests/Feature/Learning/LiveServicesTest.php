<?php

namespace Tests\Feature\Learning;

use App\Exceptions\Learning\RoomAccessException;
use App\Jobs\Learning\ImportLiveRecording;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\User;
use App\Notifications\Learning\LiveSessionScheduled;
use App\Notifications\Learning\LiveSessionStarted;
use App\Notifications\Learning\LiveSessionStartingSoon;
use App\Notifications\Learning\RecordingReady;
use App\Notifications\Learning\RoomAnnouncement;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\LiveProvider;
use App\Services\Learning\LiveServerClient;
use App\Services\Learning\RoomService;
use App\Support\Jwt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Live side of ROOM: JWT signing / verification, our self-hosted video stack
 * (LiveKit tokens, coturn TURN credentials, the SFU server API, webhooks and
 * the recording import), RoomService (lifecycle, attendance, feed, messages,
 * reminders) and the notifier's audiences.
 */
class LiveServicesTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useLiveServer();
    }

    // --- People & fixtures ---------------------------------------------
    private function learner(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function instructor(): User
    {
        return User::factory()->create(['can_teach' => true]);
    }

    private function roomManager(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['rooms.manage']]);
    }

    private function course(array $attributes = []): LearningCourse
    {
        $category = LearningCategory::create(['name' => 'Category '.uniqid()]);

        return LearningCourse::create([
            'learning_category_id' => $category->id,
            'title' => 'Course '.uniqid(),
            'status' => 'published',
            'access' => 'enrolled',
            ...$attributes,
        ]);
    }

    private function room(User $host, array $attributes = []): LearningRoom
    {
        return LearningRoom::create([
            'title' => 'Algebra live',
            'host_id' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
            ...$attributes,
        ]);
    }

    private function service(): RoomService
    {
        return app(RoomService::class);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:string,3:string} header, payload, signing input, raw signature */
    private function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $b64 = fn (string $s) => base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));

        return [
            json_decode($b64($parts[0]), true),
            json_decode($b64($parts[1]), true),
            $parts[0].'.'.$parts[1],
            $b64($parts[2]),
        ];
    }

    /** Authorization token the SFU sends with a webhook: HS256 over our API key with the body's SHA-256. */
    private function webhookToken(string $body, string $key = self::LIVE_KEY, string $secret = self::LIVE_SECRET, int $expires = 300): string
    {
        return Jwt::encode([
            'iss' => $key,
            'nbf' => now()->getTimestamp() - 5,
            'exp' => now()->getTimestamp() + $expires,
            'sha256' => base64_encode(hash('sha256', $body, true)),
        ], $secret);
    }

    private function postLiveWebhook(array $event)
    {
        $raw = json_encode($event);

        return $this->call('POST', '/api/webhooks/livekit', [], [], [], [
            'CONTENT_TYPE' => 'application/webhook+json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $this->webhookToken($raw),
        ], $raw);
    }

    // --- JWT -------------------------------------------------------------
    public function test_jwt_hs256_is_base64url_and_verifies_with_hash_hmac(): void
    {
        $token = Jwt::encode(['room' => 'a/b+c', 'n' => 'Zoë'], 's3cret', ['kid' => 'k1', 'alg' => 'none']);

        [$header, $payload, $input, $signature] = $this->decode($token);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'k1'], $header, 'a caller cannot override alg');
        $this->assertSame(['room' => 'a/b+c', 'n' => 'Zoë'], $payload);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $input, 's3cret', true), $signature));
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $token);

        $this->expectException(\InvalidArgumentException::class);
        Jwt::encode(['a' => 1], '');
    }

    public function test_jwt_decode_rejects_tampering_other_algorithms_and_expiry(): void
    {
        $now = now()->getTimestamp();
        $token = Jwt::encode(['sub' => 'x', 'exp' => $now + 60, 'nbf' => $now - 5], 'k');

        $this->assertSame('x', Jwt::decode($token, 'k')['sub']);
        $this->assertNull(Jwt::decode($token, 'other-key'), 'wrong key');
        $this->assertNull(Jwt::decode($token.'x', 'k'), 'tampered signature');
        $this->assertNull(Jwt::decode('a.b', 'k'), 'malformed');

        [$head, $body] = explode('.', $token);
        $none = Jwt::base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT'])).'.'.$body.'.';
        $this->assertNull(Jwt::decode($none, 'k'), 'alg none');

        $expired = Jwt::encode(['exp' => $now - 120], 'k');
        $this->assertNull(Jwt::decode($expired, 'k'), 'expired beyond the leeway');
        $future = Jwt::encode(['nbf' => $now + 600], 'k');
        $this->assertNull(Jwt::decode($future, 'k'), 'not valid yet');
    }

    // --- Self-hosted live server (LiveKit + coturn) ---------------------------
    public function test_status_reports_what_is_missing(): void
    {
        $this->withoutLiveServer();
        $status = app(LiveProvider::class)->status();

        $this->assertFalse($status['configured']);
        $this->assertStringContainsString('LIVE_SERVER_URL', implode(' ', $status['issues']));
        $this->assertStringContainsString('LIVE_SERVER_API_KEY', implode(' ', $status['issues']));
        $this->assertFalse(app(LiveProvider::class)->supportsRecording());

        $this->useLiveServer(['learning.live.server_url' => 'wss://live.example.com', 'learning.live.api_secret' => 'short']);
        $this->assertStringContainsString('32 characters', implode(' ', app(LiveProvider::class)->status()['issues']));

        $this->useLiveServer(['learning.live.server_url' => 'ws://live.example.com', 'learning.live.ice.turn_urls' => null]);
        $status = app(LiveProvider::class)->status();
        $this->assertTrue($status['configured']);
        $this->assertFalse($status['turn']);
        $warnings = implode(' ', $status['warnings']);
        $this->assertStringContainsString('wss://', $warnings);
        $this->assertStringContainsString('TURN', $warnings);

        // Local development may use ws:// and the dev key pair without warnings about TLS.
        $this->useLiveServer(['learning.live.server_url' => 'ws://127.0.0.1:7880', 'learning.live.api_url' => null, 'learning.live.api_secret' => 'secret']);
        $status = app(LiveProvider::class)->status();
        $this->assertTrue($status['configured']);
        $this->assertStringNotContainsString('wss://', implode(' ', $status['warnings']));
        $this->assertSame('http://127.0.0.1:7880', app(LiveProvider::class)->apiUrl());
    }

    public function test_local_server_url_follows_the_lan_address_the_page_was_opened_with(): void
    {
        $this->useLiveServer(['learning.live.server_url' => 'ws://127.0.0.1:7880']);
        $live = app(LiveProvider::class);

        $this->app->instance('request', \Illuminate\Http\Request::create('http://192.168.1.176:8000/learn/rooms/x/live'));
        $this->assertSame('ws://192.168.1.176:7880', $live->browserServerUrl());

        $this->app->instance('request', \Illuminate\Http\Request::create('http://127.0.0.1:8000/learn'));
        $this->assertSame('ws://127.0.0.1:7880', $live->browserServerUrl());

        // Public hosts never rewrite, and a real server URL is left alone.
        $this->app->instance('request', \Illuminate\Http\Request::create('https://evil.example.com/learn'));
        $this->assertSame('ws://127.0.0.1:7880', $live->browserServerUrl());
        $this->useLiveServer(['learning.live.server_url' => 'wss://live.example.com']);
        $this->app->instance('request', \Illuminate\Http\Request::create('http://192.168.1.176:8000/learn'));
        $this->assertSame('wss://live.example.com', $live->browserServerUrl());
    }

    public function test_join_tokens_carry_exactly_the_allowed_sources(): void
    {
        $this->useLiveServer();
        $live = app(LiveProvider::class);
        $host = $this->instructor();
        $learner = $this->learner(['name' => 'Asha Learner']);
        $room = $this->room($host);

        $claims = $this->liveClaims($live->token($learner, $room, ['audio' => true, 'video' => false, 'screen' => false], false));
        $this->assertSame(self::LIVE_KEY, $claims['iss']);
        $this->assertSame('user-'.$learner->id, $claims['sub']);
        $this->assertSame('Asha Learner', $claims['name']);
        $this->assertSame(['user_id' => $learner->id, 'role' => 'participant'], json_decode($claims['metadata'], true));
        $this->assertSame($room->provider_room, $claims['video']['room']);
        $this->assertTrue($claims['video']['canPublish']);
        $this->assertSame(['microphone'], $claims['video']['canPublishSources']);
        $this->assertTrue($claims['video']['canSubscribe']);
        $this->assertFalse($claims['video']['canUpdateOwnMetadata']);
        $this->assertArrayNotHasKey('roomAdmin', $claims['video'], 'clients never get admin rights on the SFU');
        $this->assertSame(10 * 60 + 10, $claims['exp'] - $claims['nbf']);

        $watcher = $this->liveClaims($live->token($learner, $room, ['audio' => false, 'video' => false, 'screen' => false], false));
        $this->assertFalse($watcher['video']['canPublish']);
        $this->assertSame([], $watcher['video']['canPublishSources']);

        $hostClaims = $this->liveClaims($live->token($host, $room, ['audio' => true, 'video' => true, 'screen' => true], true));
        $this->assertSame(['microphone', 'camera', 'screen_share', 'screen_share_audio'], $hostClaims['video']['canPublishSources']);
        $this->assertSame('host', json_decode($hostClaims['metadata'], true)['role']);

        $this->assertSame($learner->id, $live->userIdFromIdentity('user-'.$learner->id));
        $this->assertNull($live->userIdFromIdentity('user-0'));
        $this->assertNull($live->userIdFromIdentity('admin'));

        $this->withoutLiveServer();
        $this->expectException(\RuntimeException::class);
        $live->token($learner, $room, ['audio' => true, 'video' => true, 'screen' => false], false);
    }

    public function test_ice_servers_use_expiring_turn_credentials(): void
    {
        $this->useLiveServer();
        $learner = $this->learner();
        $this->freezeTime();

        $servers = app(LiveProvider::class)->iceServers($learner);

        $this->assertSame(['stun:turn.example.test:3478'], $servers[0]['urls']);
        $this->assertSame(['turn:turn.example.test:3478?transport=udp', 'turns:turn.example.test:5349?transport=tcp'], $servers[1]['urls']);
        [$expires, $identity] = explode(':', $servers[1]['username'], 2);
        $this->assertSame('user-'.$learner->id, $identity);
        $this->assertSame(now()->addMinutes(720)->getTimestamp(), (int) $expires);
        $this->assertSame(base64_encode(hash_hmac('sha1', $servers[1]['username'], self::TURN_SECRET, true)), $servers[1]['credential']);

        // Static coturn user as a fallback; invalid URLs are dropped.
        $this->useLiveServer([
            'learning.live.ice.turn_secret' => null,
            'learning.live.ice.turn_username' => 'classroom',
            'learning.live.ice.turn_credential' => 'pa55',
            'learning.live.ice.stun_urls' => 'stun:ok.test:3478, https://not-a-stun-server',
        ]);
        $servers = app(LiveProvider::class)->iceServers($learner);
        $this->assertSame(['stun:ok.test:3478'], $servers[0]['urls']);
        $this->assertSame(['classroom', 'pa55'], [$servers[1]['username'], $servers[1]['credential']]);

        $this->useLiveServer(['learning.live.ice.transport_policy' => 'relay']);
        $this->assertSame('relay', app(LiveProvider::class)->iceTransportPolicy());
    }

    public function test_webhook_signatures_are_verified_strictly(): void
    {
        $this->useLiveServer();
        $live = app(LiveProvider::class);
        $body = '{"event":"room_started"}';

        $this->assertTrue($live->verifyWebhook($body, $this->webhookToken($body)));
        $this->assertTrue($live->verifyWebhook($body, 'Bearer '.$this->webhookToken($body)));
        $this->assertFalse($live->verifyWebhook($body.' ', $this->webhookToken($body)), 'tampered body');
        $this->assertFalse($live->verifyWebhook($body, $this->webhookToken($body, secret: 'another-secret-another-secret-123')));
        $this->assertFalse($live->verifyWebhook($body, $this->webhookToken($body, key: 'someone-else')), 'foreign API key');
        $this->assertFalse($live->verifyWebhook($body, $this->webhookToken($body, expires: -120)), 'expired');
        $this->assertFalse($live->verifyWebhook($body, null));
        $this->assertFalse($live->verifyWebhook($body, ''));
    }

    public function test_server_api_calls_are_signed_and_failures_never_throw(): void
    {
        $this->liveApi['RoomService/ListParticipants'] = ['participants' => [[
            'identity' => 'user-7',
            'tracks' => [
                ['sid' => 'TR_mic', 'source' => 'MICROPHONE', 'muted' => false],
                ['sid' => 'TR_cam', 'source' => 'CAMERA', 'muted' => false],
                ['sid' => 'TR_old', 'source' => 'MICROPHONE', 'muted' => true],
            ],
        ]]];
        $client = app(LiveServerClient::class);

        $this->assertTrue($client->muteSource('room-a', 'user-7', 'audio'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/MutePublishedTrack') && $r['track_sid'] === 'TR_mic' && $r['muted'] === true);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/MutePublishedTrack') && in_array($r['track_sid'], ['TR_cam', 'TR_old'], true));

        $this->assertTrue($client->updatePermissions('room-a', 'user-7', ['audio' => false, 'video' => true, 'screen' => false]));
        Http::assertSent(function ($r) {
            if (! str_ends_with($r->url(), '/UpdateParticipant')) {
                return false;
            }
            $claims = \App\Support\Jwt::decode(substr($r->header('Authorization')[0], 7), self::LIVE_SECRET);

            return $r['permission']['can_publish_sources'] === ['CAMERA'] && $r['permission']['can_publish'] === true
                && $claims['iss'] === self::LIVE_KEY && $claims['video']['roomAdmin'] === true && $claims['video']['room'] === 'room-a';
        });

        $this->liveApi['RoomService/DeleteRoom'] = fn () => Http::response(['msg' => 'room not found'], 404);
        $this->assertFalse($client->deleteRoom('missing'));
        $this->assertStringContainsString('room not found', $client->lastError());

        $this->withoutLiveServer();
        $this->assertFalse($client->removeParticipant('room-a', 'user-7'));
        $this->assertNotNull($client->lastError());
    }

    // --- RoomService: lifecycle ------------------------------------------
    public function test_create_parses_the_schedule_in_app_time_and_guards_the_host(): void
    {
        Notification::fake();
        $instructor = $this->instructor();
        $member = $this->learner();

        $room = $this->service()->create([
            'title' => '  Physics  ',
            'scheduled_date' => '2026-10-01',
            'scheduled_time' => '09:30',
            'duration_minutes' => '90',
            'access' => 'private',
            'member_ids' => [$member->id, $instructor->id, 999999],
            'status' => 'scheduled',
        ], $instructor);

        $this->assertSame('Physics', $room->title);
        $this->assertSame((int) $instructor->id, (int) $room->host_id);
        $this->assertSame('2026-10-01 09:30', $room->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d H:i'));
        $this->assertSame(config('app.timezone'), $room->scheduled_at->getTimezone()->getName());
        $this->assertSame(90, $room->duration_minutes);
        $this->assertSame([(int) $member->id], LearningRoomMember::where('learning_room_id', $room->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_room_created', 'entity_id' => $room->id]);
        Notification::assertSentTo($member, LiveSessionScheduled::class);

        $other = $this->instructor();

        try {
            $this->service()->create(['title' => 'X', 'host_id' => $other->id], $instructor);
            $this->fail('An instructor must not assign another host.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('host_id', $e->errors());
        }

        $assigned = $this->service()->create(['title' => 'Y', 'host_id' => $other->id], $this->roomManager());
        $this->assertSame((int) $other->id, (int) $assigned->host_id);

        $this->expectException(ValidationException::class);
        $this->service()->create(['title' => 'Z', 'host_id' => $this->learner()->id], $this->roomManager());
    }

    public function test_start_and_end_manage_sessions_and_state(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $room = $this->room($host);
        $service = $this->service();

        $session = $service->start($room, $host);
        $this->assertTrue($room->isLive());
        $this->assertNotNull($room->started_at);
        $this->assertSame($session->id, $service->start($room, $host)->id, 'starting a live room is idempotent');
        $this->assertSame(1, LearningRoomSession::where('learning_room_id', $room->id)->count());

        $learner = $this->learner();
        $service->join($room, $learner);
        $this->travel(20)->seconds();
        $service->end($room, $host);

        $this->assertSame('completed', $room->status);
        $this->assertNotNull($session->fresh()->ended_at);
        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->first();
        $this->assertNotNull($attendance->left_at);
        $this->assertSame(20, $attendance->total_seconds);
        $this->assertFalse($attendance->isPresent());
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_room_ended', 'entity_id' => $room->id]);

        try {
            $service->end($room, $host);
            $this->fail('Ending a room that is not live must fail.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // Completed rooms can run again, in a new session.
        $this->assertNotSame($session->id, $service->start($room, $host)->id);

        $cancelled = $this->room($host, ['status' => 'cancelled']);
        $this->expectException(ValidationException::class);
        $service->start($cancelled, $host);
    }

    public function test_publish_and_cancel_respect_the_state_machine(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $learner = $this->learner();
        $service = $this->service();

        $draft = $this->room($host, ['status' => 'draft', 'scheduled_at' => null]);
        try {
            $service->publish($draft, $host);
            $this->fail('Publishing without a time must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scheduled_date', $e->errors());
        }

        $draft->forceFill(['scheduled_at' => now()->addDay()])->save();
        $service->publish($draft, $host);
        $this->assertSame('scheduled', $draft->status);
        Notification::assertSentTo($learner, LiveSessionScheduled::class);

        $service->cancel($draft, $host, 'Teacher is ill');
        $this->assertSame('cancelled', $draft->status);
        $this->assertSame('Teacher is ill', $draft->cancel_reason);

        $this->expectException(ValidationException::class);
        $service->cancel($draft, $host, null);
    }

    // --- RoomService: attendance -----------------------------------------
    public function test_join_requires_a_live_room_and_an_active_account(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $room = $this->room($host);
        $service = $this->service();

        try {
            $service->join($room, $this->learner());
            $this->fail('Joining before the start must fail.');
        } catch (RoomAccessException $e) {
            $this->assertSame('not_live', $e->reason);
        }

        $service->start($room, $host);

        try {
            $service->join($room, $this->learner(['status' => 'suspended']));
            $this->fail('Suspended accounts must not join.');
        } catch (RoomAccessException $e) {
            $this->assertSame('inactive', $e->reason);
        }

        $joined = $service->join($room, $host);
        $this->assertSame('host', $joined['attendance']->role);
        $this->assertTrue($joined['config']['moderator']);
        $this->assertSame($room->provider_room, $joined['config']['room_name']);
        $this->assertSame(['audio' => true, 'video' => true, 'screen' => true], $joined['config']['permissions']);

        $learner = $this->learner();
        $result = $service->join($room, $learner);
        $this->assertSame('participant', $result['attendance']->role);
        $this->assertFalse($result['config']['moderator']);
        $this->assertSame(1, $result['attendance']->join_count);
        $this->assertSame(2, $room->currentSession()->first()->peak_participants);
    }

    public function test_presence_never_credits_more_than_sixty_seconds_per_ping(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $room = $this->room($host);
        $service = $this->service();
        $service->start($room, $host);
        $learner = $this->learner();
        $service->join($room, $learner);

        $this->travel(15)->seconds();
        $this->assertSame(['status' => 'live', 'removed' => false], $service->presence($room, $learner));

        $this->travel(10)->minutes();
        $service->presence($room, $learner);

        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->first();
        $this->assertSame(15 + 60, $attendance->total_seconds);

        $this->travel(5)->seconds();
        $service->leave($room, $learner);
        $attendance->refresh();
        $this->assertSame(80, $attendance->total_seconds);
        $this->assertFalse($attendance->isPresent());
        $this->assertCount(0, $service->presentParticipants($room));

        // Presence is not tracked for someone who never joined.
        $this->assertSame(['status' => 'live', 'removed' => false], $service->presence($room, $this->learner()));
        $this->assertSame(1, LearningRoomAttendance::count());
    }

    public function test_removed_participants_cannot_rejoin_the_session(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $room = $this->room($host);
        $service = $this->service();
        $service->start($room, $host);
        $learner = $this->learner();
        $service->join($room, $learner);
        $service->presence($room, $learner);

        $service->removeParticipant($room, $learner, $host);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/RemoveParticipant') && $r['identity'] === 'user-'.$learner->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_room_participant_removed', 'entity_id' => $room->id]);
        $this->assertSame(['status' => 'live', 'removed' => true], $service->presence($room, $learner));
        $this->assertTrue($service->feed($room, $learner)['me']['removed']);

        try {
            $service->join($room, $learner);
            $this->fail('A removed participant must not rejoin.');
        } catch (RoomAccessException $e) {
            $this->assertSame('removed', $e->reason);
        }

        try {
            $service->postMessage($room, $learner, 'chat', 'let me back in');
            $this->fail('A removed participant must not chat.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $service->removeParticipant($room, $host, $this->roomManager());
            $this->fail('The host cannot be removed.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // A new session starts with a clean slate.
        $service->end($room, $host);
        $service->start($room, $host);
        $this->assertSame('participant', $service->join($room, $learner)['attendance']->role);
    }

    // --- RoomService: messages & feed --------------------------------------
    public function test_message_rules_follow_the_room_toggles_and_roles(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $learner = $this->learner();
        $room = $this->room($host, ['chat_enabled' => false]);
        $service = $this->service();

        $this->assertMessageRejected(fn () => $service->postMessage($room, $learner, 'question', 'Too early?'));

        $service->start($room, $host);

        $this->assertMessageRejected(fn () => $service->postMessage($room, $learner, 'chat', 'hi'));
        $this->assertMessageRejected(fn () => $service->postMessage($room, $learner, 'announcement', 'Hear ye'));
        $this->assertMessageRejected(fn () => $service->postMessage($room, $learner, 'question', str_repeat('x', 2001)));
        $this->assertMessageRejected(fn () => $service->postMessage($room, $learner, 'question', '   '));

        $hostChat = $service->postMessage($room, $host, 'chat', 'Chat is off for you, not me');
        $question = $service->postMessage($room, $learner, 'question', 'What is x?');
        $service->postMessage($room, $host, 'announcement', 'Break in 5 minutes');
        Notification::assertSentTo($learner, RoomAnnouncement::class);
        Notification::assertNotSentTo($host, RoomAnnouncement::class);

        $learnerView = $service->messagePayload($question, $learner);
        $this->assertTrue($learnerView['can_delete']);
        $this->assertFalse($learnerView['can_answer']);
        $this->assertTrue($service->messagePayload($hostChat, $learner)['is_host']);
        $this->assertTrue($service->messagePayload($question, $host)['can_answer']);

        try {
            $service->answerQuestion($question, $learner);
            $this->fail('Learners cannot answer questions.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $service->answerQuestion($question, $host);
        $this->assertTrue($question->fresh()->is_answered);

        try {
            $service->deleteMessage($hostChat, $learner);
            $this->fail('Learners cannot delete other people\'s messages.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $service->deleteMessage($question, $learner);
        $deleted = $service->messagePayload($question->fresh(), $host);
        $this->assertTrue($deleted['is_deleted']);
        $this->assertSame('', $deleted['body']);
        $this->assertFalse($deleted['can_delete']);
    }

    public function test_feed_pages_messages_reports_updates_and_participants(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $learner = $this->learner();
        $room = $this->room($host);
        $service = $this->service();
        $service->start($room, $host);
        $service->join($room, $host);
        $service->join($room, $learner);
        $service->presence($room, $learner);

        $ids = [];
        foreach (range(1, 105) as $i) {
            $ids[] = $service->postMessage($room, $learner, $i % 5 === 0 ? 'question' : 'chat', 'message '.$i)->id;
        }

        // Cursors have one-second resolution: keep the untouched messages clearly before it.
        $this->travel(2)->seconds();
        $first = $service->feed($room, $learner);
        $this->assertCount(100, $first['messages']);
        $this->assertSame($ids[5], $first['messages'][0]['id'], 'last 100, oldest first');
        $this->assertSame(end($ids), $first['messages'][99]['id']);
        $this->assertSame('live', $first['room']['status']);
        $this->assertFalse($first['me']['is_host']);
        $this->assertSame(2, $first['counts']['participants']);
        $this->assertSame(21, $first['counts']['questions_open']);
        $mine = collect($first['participants'])->firstWhere('is_me', true);
        $this->assertSame((int) $learner->id, (int) $mine['user_id']);
        $this->assertSame('user-'.$learner->id, $mine['identity']);
        $this->assertArrayNotHasKey('permissions', $mine, 'publish rights of participants are for managers only');
        $this->assertSame(['audio' => true, 'video' => true, 'screen' => false], $first['me']['permissions']);

        $hostFeed = $service->feed($room, $host);
        $this->assertTrue($hostFeed['me']['is_host']);
        $this->assertSame(['audio' => true, 'video' => true, 'screen' => true], $hostFeed['me']['permissions']);
        $this->assertSame(['audio' => true, 'video' => true, 'screen' => false],
            collect($hostFeed['participants'])->firstWhere('user_id', $learner->id)['permissions']);

        $this->travel(2)->seconds();
        $service->deleteMessage(LearningRoomMessage::find($ids[50]), $host);
        $new = $service->postMessage($room, $host, 'chat', 'newest');

        $next = $service->feed($room, $learner, end($ids), $first['cursor']);
        $this->assertSame([$new->id], array_column($next['messages'], 'id'));
        $this->assertSame([$ids[50]], array_column($next['updates'], 'id'));
        $this->assertTrue($next['updates'][0]['is_deleted']);
    }

    // --- Scheduled jobs ----------------------------------------------------
    public function test_send_reminders_notifies_once_for_rooms_starting_soon(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $learner = $this->learner();
        $soon = $this->room($host, ['scheduled_at' => now()->addMinutes(10)]);
        $later = $this->room($host, ['scheduled_at' => now()->addHour()]);
        $this->room($host, ['status' => 'draft', 'scheduled_at' => now()->addMinutes(5)]);

        $this->assertSame(1, $this->service()->sendReminders());
        $this->assertNotNull($soon->fresh()->reminder_sent_at);
        $this->assertNull($later->fresh()->reminder_sent_at);
        Notification::assertSentTo($learner, LiveSessionStartingSoon::class, fn ($n) => $n->room->is($soon));
        Notification::assertNotSentTo($host, LiveSessionStartingSoon::class);

        $this->assertSame(0, $this->service()->sendReminders(), 'reminders are sent once');
        Notification::assertSentToTimes($learner, LiveSessionStartingSoon::class, 1);
    }

    public function test_rooms_past_their_planned_time_are_ended_not_joined(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $service = $this->service();
        $stale = $this->room($host, ['duration_minutes' => 60]);
        $late = $this->room($host, ['duration_minutes' => 60]);
        $service->start($stale, $host);
        $service->start($late, $host);

        $this->travel(60 + (int) config('learning.stale_room_grace_minutes') + 1)->minutes();

        // Time is up: joining ends the class for everyone instead of letting someone in.
        try {
            $service->join($late, $this->learner());
            $this->fail('Joining a class whose time is up must be refused.');
        } catch (RoomAccessException $e) {
            $this->assertSame('not_live', $e->reason);
        }
        $this->assertSame('completed', $late->fresh()->status);

        // The stale-room sweep still closes anything left over.
        $this->assertSame(1, $service->closeStaleRooms());
        $this->assertSame('completed', $stale->fresh()->status);
    }

    // --- Notifier audiences --------------------------------------------------
    public function test_notifier_audiences_follow_room_access_and_skip_the_actor(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $member = $this->learner();
        $inactiveMember = $this->learner(['status' => 'inactive']);
        $outsider = $this->learner();
        $admin = $this->roomManager();

        $private = $this->room($host, ['access' => 'private']);
        foreach ([$member, $inactiveMember, $admin] as $user) {
            LearningRoomMember::create(['learning_room_id' => $private->id, 'user_id' => $user->id]);
        }

        $this->service()->start($private, $admin);

        Notification::assertSentTo($member, LiveSessionStarted::class);
        Notification::assertNotSentTo($inactiveMember, LiveSessionStarted::class);
        Notification::assertNotSentTo($outsider, LiveSessionStarted::class);
        Notification::assertNotSentTo($host, LiveSessionStarted::class);
        Notification::assertNotSentTo($admin, LiveSessionStarted::class, 'the actor is never notified');

        $course = $this->course();
        $enrolled = $this->learner();
        LearningEnrollment::create(['user_id' => $enrolled->id, 'learning_course_id' => $course->id, 'enrolled_at' => now()]);
        $courseRoom = $this->room($host, ['access' => 'course', 'learning_course_id' => $course->id, 'learning_category_id' => $course->learning_category_id]);
        $categoryRoom = $this->room($host, ['access' => 'category', 'learning_category_id' => $course->learning_category_id]);

        $notifier = app(LearningNotifier::class);
        $this->assertSame([(int) $enrolled->id], $notifier->roomAudience($courseRoom)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([(int) $enrolled->id], $notifier->roomAudience($categoryRoom)->pluck('id')->map(fn ($id) => (int) $id)->all());

        $course->delete(); // trashed courses no longer grant access
        $this->assertSame(0, $notifier->roomAudience($courseRoom)->count());

        $openCourse = $this->course(['access' => 'open', 'instructor_id' => $host->id]);
        $this->assertFalse($notifier->courseAudience($openCourse)->whereKey($host->id)->exists(), 'instructor excluded');
        $this->assertTrue($notifier->courseAudience($openCourse)->whereKey($outsider->id)->exists());
        $this->assertSame(0, $notifier->courseAudience($this->course(['status' => 'draft', 'access' => 'open']))->count());
    }

    // --- LiveKit webhooks & recording import ------------------------------------
    public function test_egress_ended_webhook_queues_the_import_once(): void
    {
        Queue::fake();
        $this->useLiveServer(['learning.live.recording.enabled' => true, 'learning.live.recording.import_dir' => '/srv/recordings']);
        $host = $this->instructor();
        $room = $this->room($host, ['status' => 'completed']);
        $session = LearningRoomSession::create(['learning_room_id' => $room->id, 'started_at' => now()->subHour(), 'ended_at' => now(), 'egress_id' => 'EG_one']);
        $recording = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'learning_room_session_id' => $session->id,
            'source' => 'livekit', 'status' => 'processing', 'external_id' => 'EG_one',
        ]);

        $event = [
            'event' => 'egress_ended',
            'egressInfo' => [
                'egressId' => 'EG_one',
                'roomName' => $room->provider_room,
                'status' => 'EGRESS_COMPLETE',
                'fileResults' => [['filename' => '/out/'.$room->provider_room.'-2026.mp4', 'duration' => '125000000000', 'size' => '1024']],
            ],
        ];

        $this->postLiveWebhook($event)->assertOk()->assertJson(['status' => 'queued', 'recording_id' => $recording->id]);
        $recording->refresh();
        $this->assertSame('/out/'.$room->provider_room.'-2026.mp4', $recording->external_url);
        $this->assertSame(125, $recording->duration_seconds);
        $this->assertNull($session->fresh()->egress_id);
        Queue::assertPushed(ImportLiveRecording::class, fn ($job) => $job->recordingId === $recording->id);

        // Failed egress → recording marked failed; unknown rooms are ignored; bad signatures refused.
        LearningRoomRecording::create(['learning_room_id' => $room->id, 'source' => 'livekit', 'status' => 'processing', 'external_id' => 'EG_bad']);
        $this->postLiveWebhook(['event' => 'egress_ended', 'egressInfo' => ['egressId' => 'EG_bad', 'status' => 'EGRESS_FAILED', 'error' => 'out of disk']])
            ->assertOk()->assertJson(['status' => 'failed']);
        $this->assertSame('out of disk', LearningRoomRecording::where('external_id', 'EG_bad')->value('error'));

        $this->postLiveWebhook(['event' => 'egress_ended', 'egressInfo' => ['egressId' => 'EG_x', 'roomName' => 'not-ours', 'status' => 'EGRESS_COMPLETE']])
            ->assertOk()->assertJson(['status' => 'ignored']);
        $this->postLiveWebhook(['event' => 'room_started'])->assertOk()->assertJson(['status' => 'ignored']);

        $raw = json_encode($event);
        $this->call('POST', '/api/webhooks/livekit', [], [], [], [
            'CONTENT_TYPE' => 'application/webhook+json',
            'HTTP_AUTHORIZATION' => $this->webhookToken($raw.'tampered'),
        ], $raw)->assertStatus(401);

        Queue::assertPushed(ImportLiveRecording::class, 1);
    }

    public function test_participant_left_webhook_closes_the_attendance(): void
    {
        Notification::fake();
        $this->useLiveServer();
        $host = $this->instructor();
        $learner = $this->learner();
        $room = $this->room($host);
        $service = $this->service();
        $service->start($room, $host);
        $service->join($room, $learner);

        $this->postLiveWebhook([
            'event' => 'participant_left',
            'room' => ['name' => $room->provider_room],
            'participant' => ['identity' => 'user-'.$learner->id, 'sid' => 'PA_1'],
        ])->assertOk()->assertJson(['status' => 'ok']);

        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->firstOrFail();
        $this->assertNotNull($attendance->left_at);
        $this->assertFalse($attendance->isPresent());

        $this->postLiveWebhook(['event' => 'participant_left', 'room' => ['name' => $room->provider_room], 'participant' => ['identity' => 'hacker']])
            ->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_import_job_moves_the_egress_file_and_notifies_the_host(): void
    {
        Storage::fake('private');
        Notification::fake();
        $dir = storage_path('framework/testing/egress-'.getmypid());
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/room-a-2026.mp4', 'FAKE-MP4-BYTES');
        config(['learning.disk' => 'private', 'learning.live.recording.import_dir' => $dir]);

        $host = $this->instructor();
        $room = $this->room($host, ['status' => 'completed']);
        $recording = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'source' => 'livekit', 'status' => 'processing',
            'external_id' => 'EG_ok', 'external_url' => '/out/room-a-2026.mp4',
        ]);

        try {
            app()->call([new ImportLiveRecording($recording->id), 'handle']);

            $recording->refresh();
            $this->assertSame('ready', $recording->status);
            $this->assertSame('private', $recording->disk);
            $this->assertSame(strlen('FAKE-MP4-BYTES'), $recording->size_bytes);
            $this->assertStringStartsWith('learning/recordings/'.$room->id.'/', $recording->path);
            $this->assertSame('FAKE-MP4-BYTES', Storage::disk('private')->get($recording->path));
            $this->assertFileDoesNotExist($dir.'/room-a-2026.mp4', 'the source is removed after import');
            Notification::assertSentTo($host, RecordingReady::class);

            // A file name that tries to leave the import folder is refused.
            $evil = LearningRoomRecording::create([
                'learning_room_id' => $room->id, 'source' => 'livekit', 'status' => 'processing',
                'external_id' => 'EG_evil', 'external_url' => '../../.env',
            ]);
            app()->call([new ImportLiveRecording($evil->id), 'handle']);
            $this->assertSame('failed', $evil->fresh()->status);

            // Not written yet → the job throws so the queue retries it.
            $late = LearningRoomRecording::create([
                'learning_room_id' => $room->id, 'source' => 'livekit', 'status' => 'processing',
                'external_id' => 'EG_late', 'external_url' => '/out/not-there-yet.mp4',
            ]);
            $this->expectException(\RuntimeException::class);
            app()->call([new ImportLiveRecording($late->id), 'handle']);
        } finally {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        }
    }

    private function assertMessageRejected(\Closure $post): void
    {
        try {
            $post();
            $this->fail('The message should have been rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
