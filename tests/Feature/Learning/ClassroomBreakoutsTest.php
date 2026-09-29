<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\User;
use App\Services\Learning\RoomService;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Breakout rooms: the host assigns and opens them, Laravel puts each person in
 * the right SFU room through their token, chat stays inside each room, and
 * moderation / webhooks / ending the class reach every room.
 */
class ClassroomBreakoutsTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->useLiveServer();
    }

    /** @return array{0:User,1:User,2:User,3:LearningRoom} host, two learners in the call, room */
    private function classInProgress(): array
    {
        $host = User::factory()->create(['can_teach' => true]);
        [$asha, $baraka] = User::factory()->count(2)->create()->all();
        $room = LearningRoom::create([
            'title' => 'Biology live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
        app(RoomService::class)->start($room, $host);
        foreach ([$host, $asha, $baraka] as $user) {
            app(RoomService::class)->join($room->refresh(), $user);
        }

        return [$host, $asha, $baraka, $room];
    }

    private function sfuRoomInToken(User $user, LearningRoom $room, array $body = []): string
    {
        $config = $this->actingAs($user)->postJson(route('learn.rooms.token', $room), $body)->assertOk()->json('config');

        return Jwt::decode($config['token'], self::LIVE_SECRET)['video']['room'];
    }

    private function setUpRooms(User $host, LearningRoom $room, array $assignments, bool $open = true): void
    {
        $this->actingAs($host)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 2, 'assignments' => $assignments])->assertOk();
        if ($open) {
            $this->actingAs($host)->postJson(route('studio.rooms.breakouts.open', $room->id))->assertOk()->assertJson(['open' => true, 'count' => 2]);
        }
    }

    public function test_tokens_follow_the_assignment_while_rooms_are_open(): void
    {
        [$host, $asha, $baraka, $room] = $this->classInProgress();
        $main = $room->provider_room;

        $this->setUpRooms($host, $room, [$asha->id => 1, $baraka->id => 2], open: false);
        $this->assertSame($main, $this->sfuRoomInToken($asha, $room), 'assigned but not open yet');

        $this->actingAs($host)->postJson(route('studio.rooms.breakouts.open', $room->id))->assertOk();
        $this->assertSame($main.'-b1', $this->sfuRoomInToken($asha, $room));
        $this->assertSame($main.'-b2', $this->sfuRoomInToken($baraka, $room));
        // Asking for another room does not work for a participant.
        $this->assertSame($main.'-b1', $this->sfuRoomInToken($asha, $room, ['breakout' => 2]));

        $this->actingAs($host)->postJson(route('studio.rooms.breakouts.close', $room->id))->assertOk()->assertJson(['open' => false]);
        $this->assertSame($main, $this->sfuRoomInToken($asha, $room));
    }

    public function test_the_host_visits_any_room_and_goes_back_to_the_main_room(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();
        $main = $room->provider_room;
        $this->setUpRooms($host, $room, [$asha->id => 1]);

        $this->assertSame($main.'-b2', $this->sfuRoomInToken($host, $room, ['breakout' => 2]));
        $this->assertSame(2, $this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->json('me.breakout'));
        $this->assertSame($main, $this->sfuRoomInToken($host, $room, ['breakout' => 7]), 'no such room');
        $this->assertSame($main, $this->sfuRoomInToken($host, $room));
    }

    public function test_only_the_host_sees_the_rooms_tab(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();

        $this->actingAs($host)->get(route('learn.rooms.live', $room))->assertOk()->assertSee('id="panel-rooms"', false);
        $this->actingAs($asha)->get(route('learn.rooms.live', $room))->assertOk()->assertDontSee('id="panel-rooms"', false);
    }

    public function test_only_the_host_manages_breakout_rooms_and_input_is_checked(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();

        $this->actingAs($asha)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 2])->assertForbidden();
        $this->actingAs($asha)->postJson(route('studio.rooms.breakouts.open', $room->id))->assertForbidden();

        $this->actingAs($host)->postJson(route('studio.rooms.breakouts.open', $room->id))->assertUnprocessable(); // no rooms yet
        $this->actingAs($host)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 21])->assertUnprocessable();
        $this->actingAs($host)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 2, 'assignments' => [$asha->id => 3]])->assertUnprocessable();
    }

    public function test_shuffle_spreads_everyone_but_the_host_evenly(): void
    {
        [$host, $asha, $baraka, $room] = $this->classInProgress();
        $third = User::factory()->create();
        app(RoomService::class)->join($room, $third);

        $this->actingAs($host)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 2, 'shuffle' => true])->assertOk();

        $rooms = LearningRoomAttendance::query()->whereIn('user_id', [$asha->id, $baraka->id, $third->id])->pluck('breakout_number')->sort()->values()->all();
        $this->assertCount(3, array_filter($rooms));
        $this->assertContains($rooms, [[1, 1, 2], [1, 2, 2]]);
        $this->assertNull(LearningRoomAttendance::where('user_id', $host->id)->value('breakout_number'));

        // Fewer rooms: people of the removed room return to the main room.
        $this->actingAs($host)->postJson(route('studio.rooms.breakouts', $room->id), ['count' => 1])->assertOk();
        $this->assertSame(0, LearningRoomAttendance::where('breakout_number', 2)->count());
    }

    public function test_chat_stays_in_each_room_but_questions_and_announcements_reach_everyone(): void
    {
        [$host, $asha, $baraka, $room] = $this->classInProgress();
        $this->setUpRooms($host, $room, [$asha->id => 1, $baraka->id => 2]);

        $say = fn (User $user, string $type, string $body) => $this->actingAs($user)
            ->postJson(route('learn.rooms.messages.store', $room), ['type' => $type, 'body' => $body])->assertCreated();
        $say($asha, 'chat', 'hello room one');
        $say($baraka, 'chat', 'hello room two');
        $say($baraka, 'question', 'can we get more time?');
        $this->actingAs($host)->postJson(route('studio.rooms.announce', $room->id), ['body' => 'Five minutes left'])->assertSuccessful();

        $bodies = fn (User $viewer) => collect($this->actingAs($viewer)->getJson(route('learn.rooms.feed', $room))->json('messages'))->pluck('body')->all();

        $this->assertEqualsCanonicalizing(['hello room one', 'can we get more time?', 'Five minutes left'], $bodies($asha));
        $this->assertEqualsCanonicalizing(['hello room two', 'can we get more time?', 'Five minutes left'], $bodies($baraka));
        $this->assertEqualsCanonicalizing(['can we get more time?', 'Five minutes left'], $bodies($host), 'the host is in the main room');

        $this->sfuRoomInToken($host, $room, ['breakout' => 1]);
        $this->assertContains('hello room one', $bodies($host), 'visiting room 1 shows its chat');
    }

    public function test_moderation_reaches_people_in_their_breakout_room(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();
        $this->setUpRooms($host, $room, [$asha->id => 1]);

        $this->actingAs($host)->postJson(route('studio.rooms.participants.mute', [$room->id, $asha->id]), ['kind' => 'audio']);
        // Muting starts by looking the person up in the SFU room they are in.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'RoomService/ListParticipants') && $r['room'] === $room->provider_room.'-b1');

        $this->actingAs($host)->postJson(route('studio.rooms.participants.remove', [$room->id, $asha->id]))->assertSuccessful();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'RoomService/RemoveParticipant') && $r['room'] === $room->provider_room.'-b1');
    }

    public function test_ending_the_class_closes_every_breakout_room_too(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();
        $this->setUpRooms($host, $room, [$asha->id => 1]);

        app(RoomService::class)->end($room, $host);

        foreach (['', '-b1', '-b2'] as $suffix) {
            Http::assertSent(fn ($r) => str_ends_with($r->url(), 'RoomService/DeleteRoom') && $r['room'] === $room->provider_room.$suffix);
        }
    }

    public function test_leaving_one_sfu_room_to_go_to_another_is_not_leaving_the_class(): void
    {
        [$host, $asha, , $room] = $this->classInProgress();
        $this->setUpRooms($host, $room, [$asha->id => 1]);
        $service = app(RoomService::class);

        $service->participantDisconnected($room, $asha, null); // left the main room on the way to room 1
        $this->assertNull(LearningRoomAttendance::where('user_id', $asha->id)->value('left_at'));

        $service->participantDisconnected($room, $asha, 1); // really gone
        $this->assertNotNull(LearningRoomAttendance::where('user_id', $asha->id)->value('left_at'));
    }
}
