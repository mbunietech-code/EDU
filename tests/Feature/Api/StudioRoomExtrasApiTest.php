<?php

namespace Tests\Feature\Api;

use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/** Studio class extras in the app: materials, recordings, invited members, attendance. */
class StudioRoomExtrasApiTest extends TestCase
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

    private function room(User $host, string $access = 'public'): LearningRoom
    {
        return LearningRoom::create([
            'title' => 'Chemistry live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => $access,
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
    }

    public function test_host_manages_materials_and_recordings(): void
    {
        $host = User::factory()->create(['can_teach' => true]);
        $room = $this->room($host);
        Sanctum::actingAs($host);
        $base = "/api/studio/rooms/{$room->id}";

        $material = $this->post("{$base}/materials", [
            'title' => 'Worksheet',
            'file' => UploadedFile::fake()->create('worksheet.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertStringContainsString('/api/signed/learning/rooms/', $material['url']);
        $this->post("{$base}/materials", ['file' => UploadedFile::fake()->create('virus.exe', 5)], ['Accept' => 'application/json'])
            ->assertStatus(422);

        Storage::disk('private')->put('learning/recordings/a.mp4', 'video');
        $recording = $room->recordings()->create([
            'source' => 'upload', 'status' => 'ready', 'disk' => 'private', 'path' => 'learning/recordings/a.mp4',
            'original_name' => 'a.mp4', 'mime' => 'video/mp4', 'size_bytes' => 5, 'uploaded_by' => $host->id,
        ]);

        $this->getJson("{$base}/extras")->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.materials.0.title', 'Worksheet')
            ->assertJsonPath('data.recordings.0.is_shared', false);

        $this->postJson("{$base}/recordings/{$recording->id}/share", ['is_shared' => true])->assertOk()
            ->assertJsonPath('data.is_shared', true);
        $this->deleteJson("{$base}/recordings/{$recording->id}", [])->assertStatus(422);
        $this->deleteJson("{$base}/recordings/{$recording->id}", ['reason' => 'Wrong file'])->assertOk();
        $this->deleteJson("{$base}/materials/{$material['id']}")->assertOk();
        $this->assertSame([], $this->getJson("{$base}/extras")->json('data.materials'));

        // A learner cannot use the studio controls.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("{$base}/extras")->assertForbidden();
    }

    public function test_members_and_attendance(): void
    {
        $host = User::factory()->create(['can_teach' => true]);
        $learner = User::factory()->create(['name' => 'Asha']);
        $room = $this->room($host, 'private');
        Sanctum::actingAs($host);
        $base = "/api/studio/rooms/{$room->id}";

        $this->getJson("/api/studio/users/search?q=As")->assertOk()->assertJsonPath('0.name', 'Asha');
        $this->postJson("{$base}/members", ['user_ids' => [$learner->id]])->assertOk()->assertJsonPath('added', 1);
        $memberId = $this->getJson("{$base}/extras")->assertJsonPath('data.members.0.name', 'Asha')->json('data.members.0.id');

        app(RoomService::class)->start($room, $host);
        app(RoomService::class)->join($room->refresh(), $host);
        app(RoomService::class)->join($room, $learner);

        $this->getJson("{$base}/attendance")->assertOk()
            ->assertJsonPath('data.totals.attendees', 2)
            ->assertJsonPath('data.rows.0.role', 'host');

        $this->deleteJson("{$base}/members/{$memberId}")->assertOk();
        $this->assertSame([], $this->getJson("{$base}/extras")->json('data.members'));
    }
}
