<?php

namespace Tests\Feature\Api;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\Setting;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/** The host's live-class controls in the app: one participant, guests, breakout rooms. */
class LiveHostApiTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->useLiveServer();
    }

    /** @return array{0:User,1:User,2:LearningRoom} host, learner (in the call), room */
    private function liveClass(): array
    {
        $host = User::factory()->create(['can_teach' => true]);
        $learner = User::factory()->create();
        $room = LearningRoom::create([
            'title' => 'Physics live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
        app(RoomService::class)->start($room, $host);
        app(RoomService::class)->join($room->refresh(), $host);
        app(RoomService::class)->join($room, $learner);

        return [$host, $learner, $room->refresh()];
    }

    public function test_host_manages_one_participant(): void
    {
        [$host, $learner, $room] = $this->liveClass();
        $base = "/api/learning/rooms/{$room->slug}/participants/{$learner->id}";

        // Learners cannot use the host controls.
        Sanctum::actingAs($learner);
        $this->postJson("{$base}/remove")->assertForbidden();

        Sanctum::actingAs($host);
        $this->postJson("{$base}/permissions", ['audio' => true])->assertOk()
            ->assertJsonPath('permissions.audio', true);
        $this->postJson("{$base}/mute", ['kind' => 'audio'])->assertOk();
        $this->postJson("{$base}/mute", ['kind' => 'nose'])->assertStatus(422);

        Sanctum::actingAs($learner);
        $this->postJson("/api/learning/rooms/{$room->slug}/hand", ['raised' => true])->assertOk();
        Sanctum::actingAs($host);
        $this->postJson("{$base}/lower-hand")->assertOk();
        $this->assertNull(LearningRoomAttendance::where('user_id', $learner->id)->value('hand_raised_at'));

        $this->postJson("{$base}/remove")->assertOk();
        $this->assertNotNull(LearningRoomAttendance::where('user_id', $learner->id)->value('removed_at'));
    }

    public function test_host_runs_breakout_rooms(): void
    {
        [$host, $learner, $room] = $this->liveClass();
        Sanctum::actingAs($host);
        $base = "/api/learning/rooms/{$room->slug}/breakouts";

        $this->postJson($base, ['count' => 2, 'assignments' => [$learner->id => 1]])->assertOk()
            ->assertJsonPath('breakouts.count', 2);
        $this->postJson("{$base}/open")->assertOk()->assertJsonPath('breakouts.open', true);

        // The learner's feed puts them in room 1.
        Sanctum::actingAs($learner);
        $this->getJson("/api/learning/rooms/{$room->slug}/feed")->assertOk()->assertJsonPath('me.breakout', 1)
            ->assertJsonMissingPath('guests');

        Sanctum::actingAs($host);
        $this->postJson("{$base}/close")->assertOk()->assertJsonPath('breakouts.open', false);
    }

    public function test_host_opens_the_guest_link_and_lets_guests_in(): void
    {
        [$host, , $room] = $this->liveClass();
        Sanctum::actingAs($host);
        $base = "/api/learning/rooms/{$room->slug}";

        // Off for the platform until an admin turns it on.
        $this->postJson("{$base}/guest-link", ['enabled' => true])->assertStatus(422);

        Setting::set('learning.guest_links', '1');
        $link = $this->postJson("{$base}/guest-link", ['enabled' => true, 'waiting_room' => true])->assertOk()
            ->assertJsonPath('waiting_room', true)->json('link');
        $this->assertNotNull($link);

        // Two guests knock.
        foreach (['Neema', 'Juma'] as $name) {
            $this->app['auth']->forgetGuards();
            $this->post(route('guest.store', basename($link)), ['name' => $name])->assertRedirect();
        }
        [$neema, $juma] = User::where('is_guest', true)->orderBy('id')->get()->all();

        Sanctum::actingAs($host);
        $this->getJson("{$base}/feed")->assertOk()
            ->assertJsonPath('guests.enabled', true)
            ->assertJsonPath('guests.waiting.0.name', 'Neema');

        $this->postJson("{$base}/guests/{$neema->id}/deny")->assertOk();
        $this->postJson("{$base}/guests/admit-all")->assertOk()->assertJsonPath('admitted', 1);
        $this->assertSame([], $this->getJson("{$base}/feed")->json('guests.waiting'));
        $this->assertNotNull($juma);

        $this->postJson("{$base}/guest-link", ['enabled' => false])->assertOk()->assertJsonPath('link', null);
    }
}
