<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMaterial;
use App\Models\LearningRoomRecording;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Self-hosted live classroom moderation: every host action is authorised by
 * Laravel, stored in the database and then executed on the SFU (faked here).
 * Also covers classroom materials, the admin Live sessions pages and the
 * mobile API endpoints for tokens / leave / start / end.
 */
class LiveModerationTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('private');
        config(['learning.disk' => 'private']);
        $this->useLiveServer();
    }

    private function instructor(): User
    {
        return User::factory()->create(['can_teach' => true]);
    }

    private function liveRoom(User $host, array $attrs = []): LearningRoom
    {
        $room = LearningRoom::create(array_merge([
            'title' => 'Physics live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ], $attrs));
        app(RoomService::class)->start($room, $host);

        return $room->refresh();
    }

    /** @return array{0:User,1:User,2:LearningRoom} host, learner (in the call), room */
    private function classInProgress(array $attrs = []): array
    {
        $host = $this->instructor();
        $learner = User::factory()->create();
        $room = $this->liveRoom($host, $attrs);
        app(RoomService::class)->join($room, $host);
        app(RoomService::class)->join($room, $learner);

        return [$host, $learner, $room];
    }

    private function sfuCalled(string $method, ?\Closure $check = null): void
    {
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/twirp/livekit.'.$method) && (! $check || $check($r)));
    }

    public function test_learners_cannot_use_any_moderation_endpoint(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $endpoints = [
            [route('studio.rooms.lock', $room->id), ['locked' => true]],
            [route('studio.rooms.media', $room->id), ['allow_participant_media' => false]],
            [route('studio.rooms.participants.permissions', [$room->id, $host->id]), ['audio' => false]],
            [route('studio.rooms.participants.mute', [$room->id, $host->id]), ['kind' => 'audio']],
            [route('studio.rooms.mute-all', $room->id), ['kind' => 'audio']],
            [route('studio.rooms.recording.start', $room->id), []],
            [route('studio.rooms.participants.remove', [$room->id, $host->id]), []],
            [route('studio.rooms.end', $room->id), []],
        ];

        foreach ($endpoints as [$url, $body]) {
            $this->assertContains($this->actingAs($learner)->postJson($url, $body)->status(), [403], $url);
        }

        $this->assertFalse($room->fresh()->is_locked);
        $this->assertTrue($room->fresh()->isLive());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/twirp/'));
    }

    public function test_lock_keeps_newcomers_out(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->actingAs($host)->postJson(route('studio.rooms.lock', $room->id), ['locked' => true])
            ->assertOk()->assertJson(['is_locked' => true]);
        $this->assertTrue($room->fresh()->is_locked);

        $newcomer = User::factory()->create();
        $this->actingAs($newcomer)->postJson(route('learn.rooms.join', $room))->assertForbidden()->assertJsonPath('reason', 'locked');
        $this->actingAs($learner)->postJson(route('learn.rooms.token', $room))->assertOk();

        $this->actingAs($host)->postJson(route('studio.rooms.lock', $room->id), ['locked' => false])->assertOk();
        $this->actingAs($newcomer)->postJson(route('learn.rooms.join', $room))->assertOk();
        $this->actingAs($host)->postJson(route('studio.rooms.lock', $room->id), ['locked' => 'maybe'])->assertStatus(422);
    }

    public function test_room_media_switches_update_everyone_on_the_sfu(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->actingAs($host)->postJson(route('studio.rooms.media', $room->id), ['allow_participant_media' => false])
            ->assertOk()->assertJson(['allow_participant_media' => false, 'allow_screen_share' => false]);

        $this->sfuCalled('RoomService/UpdateParticipant', fn ($r) => $r['identity'] === 'user-'.$learner->id
            && $r['permission']['can_publish'] === false && $r['permission']['can_publish_sources'] === []);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/UpdateParticipant') && $r['identity'] === 'user-'.$host->id);

        // New tokens follow the switch.
        $claims = $this->liveClaims($this->actingAs($learner)->postJson(route('learn.rooms.token', $room))->json('config.token'));
        $this->assertFalse($claims['video']['canPublish']);

        $this->actingAs($host)->postJson(route('studio.rooms.media', $room->id), ['allow_screen_share' => true])->assertOk();
        $this->assertSame(['audio' => false, 'video' => false, 'screen' => true], app(RoomService::class)->permissionsFor($room->fresh(), $learner));
    }

    public function test_personal_rights_override_the_room_and_can_be_reset(): void
    {
        [$host, $learner, $room] = $this->classInProgress(['allow_participant_media' => false]);

        $this->actingAs($host)->postJson(route('studio.rooms.participants.permissions', [$room->id, $learner->id]), ['audio' => true, 'screen' => true])
            ->assertOk()->assertJsonPath('permissions', ['audio' => true, 'video' => false, 'screen' => true]);

        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->firstOrFail();
        $this->assertSame([true, null, true], [$attendance->can_publish_audio, $attendance->can_publish_video, $attendance->can_share_screen]);
        $this->sfuCalled('RoomService/UpdateParticipant', fn ($r) => $r['permission']['can_publish_sources'] === ['MICROPHONE', 'SCREEN_SHARE', 'SCREEN_SHARE_AUDIO']);

        $claims = $this->liveClaims($this->actingAs($learner)->postJson(route('learn.rooms.token', $room))->json('config.token'));
        $this->assertSame(['microphone', 'screen_share', 'screen_share_audio'], $claims['video']['canPublishSources']);

        $this->actingAs($host)->postJson(route('studio.rooms.participants.permissions', [$room->id, $learner->id]), ['audio' => null, 'video' => null, 'screen' => null])
            ->assertOk()->assertJsonPath('permissions', ['audio' => false, 'video' => false, 'screen' => false]);

        // Hosts always keep full rights; people outside the session cannot be targeted.
        $this->actingAs($host)->postJson(route('studio.rooms.participants.permissions', [$room->id, $host->id]), ['audio' => false])->assertStatus(422);
        $stranger = User::factory()->create();
        $this->actingAs($host)->postJson(route('studio.rooms.participants.permissions', [$room->id, $stranger->id]), ['audio' => true])->assertStatus(422);
    }

    public function test_mute_one_and_mute_everyone_go_through_the_sfu(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->liveApi['RoomService/ListParticipants'] = ['participants' => [
            ['identity' => 'user-'.$host->id, 'tracks' => [['sid' => 'TR_host', 'source' => 'MICROPHONE']]],
            ['identity' => 'user-'.$learner->id, 'tracks' => [['sid' => 'TR_learner', 'source' => 2]]],
        ]];

        $this->actingAs($host)->postJson(route('studio.rooms.participants.mute', [$room->id, $learner->id]), ['kind' => 'audio'])->assertOk();
        $this->sfuCalled('RoomService/MutePublishedTrack', fn ($r) => $r['track_sid'] === 'TR_learner');

        $this->actingAs($host)->postJson(route('studio.rooms.mute-all', $room->id), ['kind' => 'audio'])->assertOk()->assertJson(['count' => 1]);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/MutePublishedTrack') && $r['track_sid'] === 'TR_host');

        $this->actingAs($host)->postJson(route('studio.rooms.mute-all', $room->id), ['kind' => 'shout'])->assertStatus(422);

        // SFU down → 503 with a clear reason, nothing crashes.
        $this->liveApi['RoomService/ListParticipants'] = 500;
        $this->actingAs($host)->postJson(route('studio.rooms.participants.mute', [$room->id, $learner->id]), ['kind' => 'video'])
            ->assertStatus(503)->assertJsonPath('reason', 'provider_unavailable');
    }

    public function test_end_and_remove_close_connections_on_the_sfu(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->actingAs($host)->postJson(route('studio.rooms.participants.remove', [$room->id, $learner->id]))->assertOk();
        $this->sfuCalled('RoomService/RemoveParticipant', fn ($r) => $r['identity'] === 'user-'.$learner->id);
        $this->actingAs($learner)->postJson(route('learn.rooms.token', $room))->assertForbidden()->assertJsonPath('reason', 'removed');

        $this->actingAs($host)->postJson(route('studio.rooms.end', $room->id))->assertOk();
        $this->sfuCalled('RoomService/DeleteRoom', fn ($r) => $r['room'] === $room->provider_room);
        $this->assertSame('completed', $room->fresh()->status);

        // Ending still works when the SFU is unreachable (the database is the source of truth).
        $again = $this->liveRoom($host);
        $this->liveApiDown = true;
        $this->actingAs($host)->postJson(route('studio.rooms.end', $again->id))->assertOk();
        $this->assertSame('completed', $again->fresh()->status);
    }

    public function test_server_recording_start_and_stop(): void
    {
        [$host, , $room] = $this->classInProgress();

        $this->actingAs($host)->postJson(route('studio.rooms.recording.start', $room->id))
            ->assertStatus(422)->assertJsonValidationErrors('recording');

        $this->useLiveServer(['learning.live.recording.enabled' => true, 'learning.live.recording.import_dir' => '/srv/rec']);
        $this->liveApi['Egress/StartRoomCompositeEgress'] = ['egress_id' => 'EG_123', 'status' => 'EGRESS_STARTING'];

        $this->actingAs($host)->postJson(route('studio.rooms.recording.start', $room->id))->assertOk()->assertJson(['is_recording' => true]);
        $this->sfuCalled('Egress/StartRoomCompositeEgress', fn ($r) => $r['room_name'] === $room->provider_room
            && str_starts_with($r['file_outputs'][0]['filepath'], '/out/'.$room->provider_room));

        $recording = LearningRoomRecording::where('external_id', 'EG_123')->firstOrFail();
        $this->assertSame(['livekit', 'processing'], [$recording->source, $recording->status]);
        $this->assertTrue($this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->json('room.is_recording'));

        // Starting twice does not start a second egress.
        $this->actingAs($host)->postJson(route('studio.rooms.recording.start', $room->id))->assertOk();
        $this->assertSame(1, LearningRoomRecording::count());

        $this->actingAs($host)->postJson(route('studio.rooms.recording.stop', $room->id))->assertOk()->assertJson(['is_recording' => false]);
        $this->sfuCalled('Egress/StopEgress', fn ($r) => $r['egress_id'] === 'EG_123');
        $this->actingAs($host)->postJson(route('studio.rooms.recording.stop', $room->id))->assertStatus(422);
    }

    public function test_materials_upload_download_and_delete(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->actingAs($learner)->post(route('studio.rooms.materials.store', $room->id), [
            'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $response = $this->actingAs($host)->postJson(route('studio.rooms.materials.store', $room->id), [
            'title' => 'Week 1 slides',
            'file' => UploadedFile::fake()->create('slides.pdf', 20, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('material.title', 'Week 1 slides');

        $material = LearningRoomMaterial::firstOrFail();
        Storage::disk('private')->assertExists($material->path);

        $this->actingAs($host)->postJson(route('studio.rooms.materials.store', $room->id), [
            'file' => UploadedFile::fake()->create('virus.exe', 5),
        ])->assertStatus(422);

        // Learners see it in the classroom feed and can download it; outsiders cannot.
        $feed = $this->actingAs($learner)->getJson(route('learn.rooms.feed', $room))->assertOk();
        $this->assertSame([$material->id], array_column($feed->json('materials'), 'id'));
        $this->actingAs($learner)->get($response->json('material.url'))->assertOk()->assertHeader('content-disposition');

        $private = $this->liveRoom($host, ['access' => 'private']);
        $other = $private->materials()->create([
            'title' => 'Secret', 'disk' => 'private', 'path' => 'learning/resources/x.pdf', 'original_name' => 'x.pdf', 'uploaded_by' => $host->id,
        ]);
        $this->actingAs($learner)->get(route('learn.rooms.materials.download', [$private, $other]))->assertForbidden();
        $this->actingAs($learner)->get(route('learn.rooms.materials.download', [$room, $other]))->assertNotFound();

        $this->actingAs($host)->deleteJson(route('studio.rooms.materials.destroy', [$room->id, $material->id]))->assertOk();
        Storage::disk('private')->assertMissing($material->path);
        $this->assertNull(LearningRoomMaterial::find($material->id));
    }

    public function test_admin_live_sessions_pages_and_chat_moderation(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $message = app(RoomService::class)->postMessage($room, $learner, 'chat', 'Rude words here');

        $viewer = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['rooms.view']]);
        $manager = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['rooms.view', 'rooms.manage']]);

        $this->actingAs($viewer)->get(route('admin.learning.live.index'))->assertOk()->assertSee('Physics live')->assertSee('Live now');
        $this->actingAs($viewer)->get(route('admin.learning.live.show', $room->id))->assertOk()
            ->assertSee($learner->name)->assertSee('Rude words here')->assertDontSee('Moderation');
        $this->actingAs($viewer)->delete(route('admin.learning.live.messages.destroy', [$room->id, $message->id]))->assertForbidden();

        $this->actingAs($manager)->get(route('admin.learning.live.show', $room->id))->assertOk()->assertSee('Moderation');
        $this->actingAs($manager)->delete(route('admin.learning.live.messages.destroy', [$room->id, $message->id]))->assertRedirect();
        $this->assertTrue($message->fresh()->is_deleted);

        // Server check only runs on request and reports reachability.
        $this->actingAs($manager)->get(route('admin.learning.live.index', ['check' => 1]))->assertOk()->assertSee('Server answering');
        $this->sfuCalled('RoomService/ListRooms');

        $this->actingAs(User::factory()->create())->get(route('admin.learning.live.index'))->assertStatus(403);
    }

    public function test_api_token_leave_start_and_end(): void
    {
        $host = $this->instructor();
        $learner = User::factory()->create();
        $room = LearningRoom::create([
            'title' => 'API class', 'host_id' => $host->id, 'created_by' => $host->id,
            'status' => 'scheduled', 'access' => 'public', 'scheduled_at' => now()->addHour(), 'duration_minutes' => 30,
        ]);

        Sanctum::actingAs($learner);
        $this->postJson(route('api.learning.rooms.start', $room->slug))->assertForbidden();
        $this->postJson(route('api.learning.rooms.token', $room->slug))->assertStatus(409);

        Sanctum::actingAs($host);
        $this->postJson(route('api.learning.rooms.start', $room->slug))->assertOk()->assertJsonPath('data.status', 'live');

        Sanctum::actingAs($learner);
        $config = $this->postJson(route('api.learning.rooms.token', $room->slug))->assertOk()->json('config');
        $this->assertSame('wss://live.example.test', $config['server_url']);
        $this->assertNotEmpty($config['ice_servers']);
        $this->postJson(route('api.learning.rooms.leave', $room->slug))->assertOk()->assertJson(['left' => true]);
        $this->postJson(route('api.learning.rooms.end', $room->slug))->assertForbidden();

        Sanctum::actingAs($host);
        $this->postJson(route('api.learning.rooms.end', $room->slug))->assertOk()->assertJsonPath('data.status', 'completed');
    }
}
