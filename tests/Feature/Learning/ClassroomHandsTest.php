<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/** Raise hand in the live classroom: stored per attendance, shown in the feed, lowered by the host. */
class ClassroomHandsTest extends TestCase
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
    private function classInProgress(): array
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

        return [$host, $learner, $room];
    }

    private function feedFor(User $viewer, LearningRoom $room): array
    {
        return $this->actingAs($viewer)->getJson(route('learn.rooms.feed', $room))->assertOk()->json();
    }

    public function test_a_learner_raises_and_lowers_their_hand_and_everyone_sees_it(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->actingAs($learner)->postJson(route('learn.rooms.hand', $room), ['raised' => true])
            ->assertOk()->assertJson(['raised' => true]);

        $feed = $this->feedFor($host, $room);
        $this->assertSame(1, $feed['counts']['hands']);
        $mine = collect($feed['participants'])->firstWhere('user_id', $learner->id);
        $this->assertNotNull($mine['hand_raised_at']);
        $this->assertArrayNotHasKey('hand_raised_at', collect($feed['participants'])->firstWhere('user_id', $host->id));

        $this->actingAs($learner)->postJson(route('learn.rooms.hand', $room), ['raised' => false])->assertOk();
        $this->assertSame(0, $this->feedFor($host, $room)['counts']['hands']);
    }

    public function test_raising_again_keeps_the_place_in_the_queue(): void
    {
        [, $learner, $room] = $this->classInProgress();
        $this->actingAs($learner)->postJson(route('learn.rooms.hand', $room), ['raised' => true])->assertOk();
        $first = LearningRoomAttendance::where('user_id', $learner->id)->value('hand_raised_at');

        $this->travel(2)->minutes();
        $this->actingAs($learner)->postJson(route('learn.rooms.hand', $room), ['raised' => true])->assertOk();

        $this->assertEquals($first, LearningRoomAttendance::where('user_id', $learner->id)->value('hand_raised_at'));
    }

    public function test_only_people_in_the_live_class_can_raise_a_hand(): void
    {
        [, , $room] = $this->classInProgress();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->postJson(route('learn.rooms.hand', $room), ['raised' => true])->assertUnprocessable();
    }

    public function test_the_host_lowers_one_hand_or_all_hands_and_learners_cannot(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $other = User::factory()->create();
        app(RoomService::class)->join($room, $other);
        foreach ([$learner, $other] as $user) {
            $this->actingAs($user)->postJson(route('learn.rooms.hand', $room), ['raised' => true])->assertOk();
        }

        $this->actingAs($learner)->postJson(route('studio.rooms.participants.lower-hand', [$room->id, $other->id]))->assertForbidden();
        $this->actingAs($learner)->postJson(route('studio.rooms.lower-hands', $room->id))->assertForbidden();

        $this->actingAs($host)->postJson(route('studio.rooms.participants.lower-hand', [$room->id, $learner->id]))->assertOk();
        $this->assertSame(1, $this->feedFor($host, $room)['counts']['hands']);

        $this->actingAs($host)->postJson(route('studio.rooms.lower-hands', $room->id))->assertOk()->assertJson(['count' => 1]);
        $this->assertSame(0, $this->feedFor($host, $room)['counts']['hands']);
    }

    public function test_leaving_the_class_lowers_the_hand(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->actingAs($learner)->postJson(route('learn.rooms.hand', $room), ['raised' => true])->assertOk();

        $this->actingAs($learner)->post(route('learn.rooms.leave', $room))->assertNoContent();

        $this->assertNull(LearningRoomAttendance::where('user_id', $learner->id)->value('hand_raised_at'));
    }

    public function test_the_classroom_page_ships_the_hand_urls(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $urls = fn (User $viewer) => $this->actingAs($viewer)->get(route('learn.rooms.live', $room))
            ->assertOk()->viewData('config')['urls'];

        $learnerUrls = $urls($learner);
        $this->assertSame(route('learn.rooms.hand', $room), $learnerUrls['hand']);
        $this->assertArrayNotHasKey('studio', $learnerUrls);

        $this->assertSame(route('studio.rooms.lower-hands', $room->id), $urls($host)['studio']['lowerHands']);
    }

    public function test_camera_backgrounds_are_served_from_our_own_site(): void
    {
        [, $learner, $room] = $this->classInProgress();

        $backgrounds = $this->actingAs($learner)->get(route('learn.rooms.live', $room))->assertOk()->viewData('config')['backgrounds'];

        $this->assertSame(asset('vendor/mediapipe/wasm'), $backgrounds['wasm']);
        $this->assertFileExists(public_path('vendor/mediapipe/wasm/vision_wasm_internal.wasm'));
        $this->assertFileExists(public_path('vendor/mediapipe/selfie_segmenter.tflite'));
        $this->assertCount(3, $backgrounds['images']);
        foreach ($backgrounds['images'] as $image) {
            $this->assertStringStartsWith(asset('images/classroom-backgrounds/'), $image['url']);
            $this->assertFileExists(public_path(str_replace(asset(''), '', $image['url'])));
        }
    }
}
