<?php

namespace Tests\Feature\Learning;

use App\Exceptions\Learning\RoomAccessException;
use App\Jobs\Learning\DownloadRoomRecording;
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
use App\Services\Learning\RoomService;
use App\Support\Jwt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Live side of ROOM: JWT signing, the Jitsi / JaaS provider, the JaaS
 * webhook + recording import, RoomService (lifecycle, attendance, feed,
 * messages, reminders) and the notifier's audiences.
 */
class LiveServicesTest extends TestCase
{
    use RefreshDatabase;

    private const JAAS_APP = 'vpaas-magic-cookie-test123';

    private const WEBHOOK_SECRET = 'whsec-test-secret';

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

    /** @return array{0:string,1:string} private PEM, public PEM */
    private function rsaKeyPair(): array
    {
        // Windows PHP builds ship without a default openssl.cnf; a minimal one is enough.
        $cnf = tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($cnf, "[req]\ndistinguished_name=dn\n[dn]\n");
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnf];

        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key, 'openssl_pkey_new failed');
        openssl_pkey_export($key, $private, null, $options);
        $public = openssl_pkey_get_details($key)['key'];
        @unlink($cnf);

        return [$private, $public];
    }

    private function useJaas(string $privateKey, array $overrides = []): void
    {
        config([
            'learning.live.provider' => 'jaas',
            'learning.live.jaas.app_id' => self::JAAS_APP,
            'learning.live.jaas.api_key_id' => self::JAAS_APP.'/key42',
            // Stored the way .env holds it: one line with literal "\n".
            'learning.live.jaas.private_key' => str_replace("\n", '\n', trim($privateKey)),
            'learning.live.jaas.private_key_path' => null,
            'learning.live.jaas.webhook_secret' => self::WEBHOOK_SECRET,
            ...$overrides,
        ]);
    }

    private function signature(string $body, ?int $timestamp = null, string $secret = self::WEBHOOK_SECRET): string
    {
        $t = $timestamp ?? now()->getTimestamp();

        return 't='.$t.',v1='.base64_encode(hash_hmac('sha256', $t.'.'.$body, $secret, true));
    }

    private function postWebhook(array $event, ?string $signature = null)
    {
        $raw = json_encode($event);

        return $this->call('POST', '/api/webhooks/jaas', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_JAAS_SIGNATURE' => $signature ?? $this->signature($raw),
        ], $raw);
    }

    // --- JWT -------------------------------------------------------------
    public function test_jwt_hs256_is_base64url_and_verifies_with_hash_hmac(): void
    {
        $token = Jwt::encode(['room' => 'a/b+c', 'n' => 'Zoë'], 's3cret', 'HS256', ['kid' => 'k1', 'alg' => 'none']);

        [$header, $payload, $input, $signature] = $this->decode($token);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => 'k1'], $header, 'a caller cannot override alg');
        $this->assertSame(['room' => 'a/b+c', 'n' => 'Zoë'], $payload);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $input, 's3cret', true), $signature));
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $token);
    }

    public function test_jwt_rs256_verifies_with_the_public_key(): void
    {
        [$private, $public] = $this->rsaKeyPair();

        $token = Jwt::encode(['sub' => 'x'], $private, 'RS256', ['kid' => 'app/key']);
        [$header, $payload, $input, $signature] = $this->decode($token);

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('app/key', $header['kid']);
        $this->assertSame(['sub' => 'x'], $payload);
        $this->assertSame(1, openssl_verify($input, $signature, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_jwt_rejects_unsupported_algorithms_and_bad_keys(): void
    {
        foreach ([['HS512', 'k'], ['HS256', ''], ['RS256', 'not a pem']] as [$alg, $key]) {
            try {
                Jwt::encode(['a' => 1], $key, $alg);
                $this->fail("Expected InvalidArgumentException for {$alg}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --- Provider ----------------------------------------------------------
    public function test_public_jitsi_demo_has_no_token_and_warns(): void
    {
        config(['learning.live.provider' => 'jitsi', 'learning.live.jitsi.domain' => 'meet.jit.si']);
        $live = app(LiveProvider::class);
        $host = $this->instructor();
        $room = $this->room($host, ['allow_participant_media' => false]);

        $this->assertTrue($live->isDemo());
        $this->assertFalse($live->usesJwt());
        $this->assertFalse($live->supportsRecording());

        $moderator = $live->clientConfig($host, $room, true);
        $this->assertSame('https://meet.jit.si/external_api.js', $moderator['scriptUrl']);
        $this->assertSame($room->provider_room, $moderator['roomName']);
        $this->assertNull($moderator['jwt']);
        $this->assertFalse($moderator['configOverwrite']['prejoinConfig']['enabled']);
        $this->assertFalse($moderator['configOverwrite']['startWithAudioMuted']);
        $this->assertSame('Algebra live', $moderator['configOverwrite']['subject']);
        $this->assertSame(
            ['camera', 'microphone', 'desktop', 'participants-pane', 'raisehand', 'tileview', 'fullscreen', 'settings'],
            $moderator['configOverwrite']['toolbarButtons']
        );

        $participant = $live->clientConfig($this->learner(), $room, false);
        $this->assertTrue($participant['configOverwrite']['startWithVideoMuted']);
        $this->assertSame(['raisehand', 'tileview', 'fullscreen', 'settings'], $participant['configOverwrite']['toolbarButtons']);

        $room->allow_participant_media = true;
        $withMedia = $live->clientConfig($this->learner(), $room, false)['configOverwrite']['toolbarButtons'];
        $this->assertContains('camera', $withMedia);
        $this->assertContains('desktop', $withMedia);
        $this->assertNotContains('participants-pane', $withMedia);

        $status = $live->status();
        $this->assertTrue($status['demo']);
        $this->assertTrue($status['configured']);
        $this->assertContains(LiveProvider::DEMO_WARNING, $status['issues']);
    }

    public function test_self_hosted_jitsi_signs_hs256_tokens(): void
    {
        config([
            'learning.live.provider' => 'jitsi',
            'learning.live.jitsi.domain' => 'https://meet.example.org/',
            'learning.live.jitsi.app_id' => 'eduhub',
            'learning.live.jitsi.app_secret' => 'jitsi-secret',
            'learning.live.jitsi.recording' => true,
        ]);
        $live = app(LiveProvider::class);
        $host = $this->instructor();
        $room = $this->room($host);

        $this->assertSame('meet.example.org', $live->domain());
        $this->assertFalse($live->isDemo());

        $config = $live->clientConfig($host, $room, true);
        [$header, $claims, $input, $signature] = $this->decode($config['jwt']);

        $this->assertSame('HS256', $header['alg']);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $input, 'jitsi-secret', true), $signature));
        $this->assertSame('eduhub', $claims['aud']);
        $this->assertSame('eduhub', $claims['iss']);
        $this->assertSame('meet.example.org', $claims['sub']);
        $this->assertSame($room->provider_room, $claims['room']);
        $this->assertTrue($claims['moderator']);
        $this->assertTrue($claims['context']['user']['moderator']);
        $this->assertTrue($claims['context']['features']['recording']);
        $this->assertGreaterThan(now()->getTimestamp(), $claims['exp']);
        $this->assertContains('recording', $config['configOverwrite']['toolbarButtons']);

        config(['learning.live.jitsi.app_secret' => null]);
        $this->assertFalse(app(LiveProvider::class)->status()['configured']);
    }

    public function test_jaas_tokens_use_string_claims_and_the_app_prefixed_room(): void
    {
        [$private, $public] = $this->rsaKeyPair();
        $this->useJaas($private);
        $live = app(LiveProvider::class);
        $host = $this->instructor();
        $learner = $this->learner();
        $room = $this->room($host);

        $this->assertSame('8x8.vc', $live->domain());
        $this->assertSame('https://8x8.vc/'.self::JAAS_APP.'/external_api.js', $live->scriptUrl());
        $this->assertSame(self::JAAS_APP.'/'.$room->provider_room, $live->roomName($room));
        $this->assertTrue($live->status()['configured']);
        $this->assertSame([], $live->status()['issues']);

        $config = $live->clientConfig($host, $room, true);
        [$header, $claims, $input, $signature] = $this->decode($config['jwt']);

        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::JAAS_APP.'/key42'], $header);
        $this->assertSame(1, openssl_verify($input, $signature, $public, OPENSSL_ALGO_SHA256));
        $this->assertSame('jitsi', $claims['aud']);
        $this->assertSame('chat', $claims['iss']);
        $this->assertSame(self::JAAS_APP, $claims['sub']);
        $this->assertSame($room->provider_room, $claims['room']);
        $this->assertSame((string) $host->id, $claims['context']['user']['id']);
        $this->assertSame('true', $claims['context']['user']['moderator']);
        $this->assertSame(
            ['livestreaming' => 'false', 'recording' => 'true', 'transcription' => 'false', 'outbound-call' => 'false'],
            $claims['context']['features']
        );
        $this->assertContains('recording', $config['configOverwrite']['toolbarButtons']);

        [, $learnerClaims] = $this->decode($live->token($learner, $room, false));
        $this->assertSame('false', $learnerClaims['context']['user']['moderator']);
        $this->assertSame('false', $learnerClaims['context']['features']['recording']);

        // The key may also come from a file.
        $path = tempnam(sys_get_temp_dir(), 'jaas');
        file_put_contents($path, $private);
        config(['learning.live.jaas.private_key' => null, 'learning.live.jaas.private_key_path' => $path]);
        [, , $input2, $signature2] = $this->decode(app(LiveProvider::class)->token($host, $room, true));
        $this->assertSame(1, openssl_verify($input2, $signature2, $public, OPENSSL_ALGO_SHA256));
        @unlink($path);
    }

    public function test_jaas_status_reports_a_missing_or_unreadable_key(): void
    {
        $this->useJaas('', ['learning.live.jaas.private_key' => null]);
        $status = app(LiveProvider::class)->status();
        $this->assertFalse($status['configured']);
        $this->assertStringContainsString('LEARNING_JAAS_PRIVATE_KEY', implode(' ', $status['issues']));

        config(['learning.live.jaas.private_key_path' => '/definitely/missing/key.pem']);
        $this->assertStringContainsString('not readable', implode(' ', app(LiveProvider::class)->status()['issues']));

        $this->expectException(\RuntimeException::class);
        app(LiveProvider::class)->token($this->learner(), $this->room($this->instructor()), false);
    }

    public function test_jaas_webhook_signature_is_verified_strictly(): void
    {
        config(['learning.live.jaas.webhook_secret' => self::WEBHOOK_SECRET]);
        $live = app(LiveProvider::class);
        $body = '{"eventType":"PING"}';

        $this->assertTrue($live->verifyJaasWebhook($body, $this->signature($body)));
        $this->assertFalse($live->verifyJaasWebhook($body.' ', $this->signature($body)), 'tampered body');
        $this->assertFalse($live->verifyJaasWebhook($body, $this->signature($body, null, 'other-secret')));
        $this->assertFalse($live->verifyJaasWebhook($body, $this->signature($body, now()->getTimestamp() - 301)), 'expired');
        $this->assertFalse($live->verifyJaasWebhook($body, 'v1=abc'));
        $this->assertFalse($live->verifyJaasWebhook($body, null));

        config(['learning.live.jaas.webhook_secret' => null]);
        $this->assertFalse(app(LiveProvider::class)->verifyJaasWebhook($body, $this->signature($body)));
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
        $this->assertSame($room->provider_room, $joined['config']['roomName']);

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
        $this->assertSame(['status' => 'live', 'removed' => false], $service->presence($room, $learner, 'abc123'));

        $this->travel(10)->minutes();
        $service->presence($room, $learner, 'bad id!');

        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->first();
        $this->assertSame(15 + 60, $attendance->total_seconds);
        $this->assertSame('abc123', $attendance->jitsi_participant_id, 'invalid ids are ignored');

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
        $service->presence($room, $learner, 'jid42');

        $this->assertSame('jid42', $service->removeParticipant($room, $learner, $host));
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
        $service->presence($room, $learner, 'learnerJid');

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
        $this->assertNull($mine['jitsi_id'], 'Jitsi ids are for moderators only');

        $hostFeed = $service->feed($room, $host);
        $this->assertTrue($hostFeed['me']['is_host']);
        $this->assertSame('learnerJid', collect($hostFeed['participants'])->firstWhere('user_id', $learner->id)['jitsi_id']);

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

    public function test_close_stale_rooms_only_ends_overdue_empty_rooms(): void
    {
        Notification::fake();
        $host = $this->instructor();
        $service = $this->service();
        $stale = $this->room($host, ['duration_minutes' => 60]);
        $busy = $this->room($host, ['duration_minutes' => 60]);
        $service->start($stale, $host);
        $service->start($busy, $host);

        $this->travel(60 + (int) config('learning.stale_room_grace_minutes') + 1)->minutes();
        $service->join($busy, $this->learner());

        $this->assertSame(1, $service->closeStaleRooms());
        $this->assertSame('completed', $stale->fresh()->status);
        $this->assertSame('live', $busy->fresh()->status);
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

    // --- JaaS webhook & recording import ------------------------------------
    public function test_recording_uploaded_webhook_queues_the_download_once(): void
    {
        Queue::fake();
        config([
            'learning.live.jaas.app_id' => self::JAAS_APP,
            'learning.live.jaas.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
        $room = $this->room($this->instructor(), ['status' => 'completed']);
        $session = LearningRoomSession::create(['learning_room_id' => $room->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);

        $event = [
            'eventType' => 'RECORDING_UPLOADED',
            'idempotencyKey' => 'idem-1',
            'fqn' => self::JAAS_APP.'/'.$room->provider_room,
            'data' => [
                'preAuthenticatedLink' => 'https://recordings.8x8.test/rec.mp4?sig=1',
                'recordingSessionId' => 'rec-session-1',
                'durationSec' => 1830,
            ],
        ];

        $this->postWebhook($event, 't='.now()->getTimestamp().',v1=forged')->assertStatus(401);
        $this->assertSame(0, LearningRoomRecording::count());

        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'queued']);
        $recording = LearningRoomRecording::sole();
        $this->assertSame('jaas', $recording->source);
        $this->assertSame('processing', $recording->status);
        $this->assertSame('rec-session-1', $recording->external_id);
        $this->assertSame(1830, $recording->duration_seconds);
        $this->assertSame((int) $session->id, (int) $recording->learning_room_session_id);
        Queue::assertPushed(DownloadRoomRecording::class, fn ($job) => $job->recordingId === $recording->id);

        $this->postWebhook($event)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertSame(1, LearningRoomRecording::count());
        Queue::assertPushed(DownloadRoomRecording::class, 1);

        $this->postWebhook(['eventType' => 'PARTICIPANT_JOINED'])->assertOk()->assertJson(['status' => 'ignored']);
        $this->postWebhook([...$event, 'fqn' => self::JAAS_APP.'/unknown-room', 'data' => [...$event['data'], 'recordingSessionId' => 'rec-2']])
            ->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame(1, LearningRoomRecording::count());
    }

    public function test_download_job_stores_the_file_and_notifies_the_host(): void
    {
        Storage::fake('private');
        Notification::fake();
        config(['learning.disk' => 'private']);
        Http::fake([
            'recordings.8x8.test/ok.mp4*' => Http::response('FAKE-MP4-BYTES', 200),
            'recordings.8x8.test/gone.mp4*' => Http::response('expired', 403),
        ]);
        $host = $this->instructor();
        $room = $this->room($host, ['status' => 'completed']);

        $recording = LearningRoomRecording::create([
            'learning_room_id' => $room->id,
            'source' => 'jaas',
            'status' => 'processing',
            'external_id' => 'rec-ok',
            'external_url' => 'https://recordings.8x8.test/ok.mp4?sig=1',
        ]);

        app()->call([new DownloadRoomRecording($recording->id), 'handle']);

        $recording->refresh();
        $this->assertSame('ready', $recording->status);
        $this->assertSame('private', $recording->disk);
        $this->assertSame('video/mp4', $recording->mime);
        $this->assertSame(strlen('FAKE-MP4-BYTES'), $recording->size_bytes);
        $this->assertStringStartsWith('learning/recordings/', $recording->path);
        Storage::disk('private')->assertExists($recording->path);
        $this->assertSame('FAKE-MP4-BYTES', Storage::disk('private')->get($recording->path));
        Notification::assertSentTo($host, RecordingReady::class);

        $broken = LearningRoomRecording::create([
            'learning_room_id' => $room->id,
            'source' => 'jaas',
            'status' => 'processing',
            'external_id' => 'rec-gone',
            'external_url' => 'https://recordings.8x8.test/gone.mp4',
        ]);
        $job = new DownloadRoomRecording($broken->id);

        try {
            app()->call([$job, 'handle']);
            $this->fail('A failed download must throw so the queue retries it.');
        } catch (\RuntimeException $e) {
            $job->failed($e);
        }

        $broken->refresh();
        $this->assertSame('failed', $broken->status);
        $this->assertStringContainsString('403', $broken->error);
        $this->assertNull($broken->path);
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
