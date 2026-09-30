<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\User;
use App\Services\Learning\LearningNotifier;
use App\Services\Learning\RoomService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Guest links: an admin switches the feature on, the host shares a link,
 * anyone joins with a name, waits until let in, and can use that one meeting
 * only.
 */
class GuestLinksTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->useLiveServer();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function turnGuestLinks(bool $on): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('admin.learning.live.guest-links'), ['enabled' => $on])
            ->assertRedirect();
    }

    /** @return array{0:User,1:LearningRoom,2:string} host, live room, guest link token */
    private function liveRoomWithGuestLink(bool $waitingRoom = true): array
    {
        $host = User::factory()->create(['can_teach' => true]);
        $room = LearningRoom::create([
            'title' => 'Board meeting',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'private',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
        app(RoomService::class)->start($room, $host);
        app(RoomService::class)->join($room->refresh(), $host);

        $this->turnGuestLinks(true);
        $link = $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => true, 'waiting_room' => $waitingRoom])
            ->assertOk()->json('guest_link');

        return [$host, $room->refresh(), basename($link)];
    }

    private function joinAsGuest(string $token, string $name = 'Neema Guest'): User
    {
        auth()->guard('web')->logout();
        $this->post(route('guest.store', $token), ['name' => $name])->assertRedirect();

        return User::query()->where('is_guest', true)->latest('id')->firstOrFail();
    }

    public function test_guest_links_are_off_until_an_admin_turns_them_on(): void
    {
        $host = User::factory()->create(['can_teach' => true]);
        $room = LearningRoom::create([
            'title' => 'x', 'host_id' => $host->id, 'created_by' => $host->id, 'status' => 'scheduled',
            'access' => 'public', 'scheduled_at' => now()->addHour(), 'duration_minutes' => 60,
        ]);

        $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => true])->assertUnprocessable();

        // Only admins with rooms.manage may switch it.
        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['rooms.view']]))
            ->post(route('admin.learning.live.guest-links'), ['enabled' => true])->assertForbidden();

        $this->actingAs($this->superAdmin())->get(route('admin.learning.live.index'))
            ->assertOk()->assertSee('Guest links')->assertSee('Turn on');

        $this->turnGuestLinks(true);
        $link = $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => true])->assertOk()->json('guest_link');
        $this->assertStringContainsString('/join/', $link);

        $this->turnGuestLinks(false);
        auth()->guard('web')->logout();
        $this->get($link)->assertNotFound()->assertSee('This meeting link does not work');
    }

    public function test_the_host_manages_the_link_from_the_studio_room_page_before_the_class(): void
    {
        $host = User::factory()->create(['can_teach' => true]);
        $room = LearningRoom::create([
            'title' => 'Sunday service', 'host_id' => $host->id, 'created_by' => $host->id, 'status' => 'scheduled',
            'access' => 'private', 'scheduled_at' => now()->addDay(), 'duration_minutes' => 60,
        ]);

        $this->actingAs($host)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Guest links are turned off');

        $this->turnGuestLinks(true);
        $this->actingAs($host)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Create guest link');

        $this->actingAs($host)->post(route('studio.rooms.guest-link', $room), ['enabled' => 1])->assertRedirect();
        $link = $room->fresh()->guestUrl();
        $this->assertNotNull($link);
        $this->actingAs($host)->get(route('studio.rooms.show', $room))->assertOk()->assertSee($link)->assertSee('Waiting room: On');

        $this->actingAs($host)->post(route('studio.rooms.guest-link', $room), ['enabled' => 0])->assertRedirect();
        $this->assertNull($room->fresh()->guest_token);
    }

    public function test_a_guest_waits_until_the_host_lets_them_in(): void
    {
        [$host, $room, $token] = $this->liveRoomWithGuestLink();

        auth()->guard('web')->logout();
        $this->get(route('guest.join', $token))->assertOk()->assertSee('Board meeting')->assertSee('Your name');
        $guest = $this->joinAsGuest($token);
        $this->assertTrue($guest->isGuest());
        $this->assertStringEndsWith('@guest.invalid', $guest->email);

        // At the door: no classroom, no token.
        $this->get(route('learn.rooms.live', $room))->assertRedirect(route('guest.wait', $token));
        $this->postJson(route('learn.rooms.token', $room))->assertForbidden();
        $this->getJson(route('guest.status', $token))->assertOk()->assertJson(['state' => 'waiting']);

        // The host sees them and lets them in.
        $waiting = $this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->json('guests.waiting');
        $this->assertSame(['Neema Guest'], array_column($waiting, 'name'));
        $this->actingAs($host)->postJson(route('studio.rooms.guests.admit', [$room->id, $guest->id]))->assertOk();

        $this->actingAs($guest->fresh());
        $this->getJson(route('guest.status', $token))->assertJson(['state' => 'admitted']);
        $this->get(route('learn.rooms.live', $room))->assertOk()->assertDontSee(route('learn.rooms.show', $room), false);
        $this->postJson(route('learn.rooms.token', $room))->assertOk()->assertJsonPath('config.user.name', 'Neema Guest');
        $this->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => 'Habari'])->assertCreated();

        $people = $this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->json('participants');
        $this->assertTrue(collect($people)->firstWhere('user_id', $guest->id)['is_guest']);
    }

    public function test_with_the_waiting_room_off_guests_go_straight_in(): void
    {
        [, $room, $token] = $this->liveRoomWithGuestLink(waitingRoom: false);

        auth()->guard('web')->logout();
        $this->post(route('guest.store', $token), ['name' => 'Juma'])->assertRedirect(route('learn.rooms.live', $room));
    }

    public function test_a_guest_can_use_only_their_meeting(): void
    {
        [$host, $room, $token] = $this->liveRoomWithGuestLink(waitingRoom: false);
        $other = LearningRoom::create([
            'title' => 'Other', 'host_id' => $host->id, 'created_by' => $host->id, 'status' => 'scheduled',
            'access' => 'public', 'scheduled_at' => now()->addHour(), 'duration_minutes' => 60,
        ]);
        $guest = $this->joinAsGuest($token);
        $this->actingAs($guest);

        $this->get(route('dashboard'))->assertRedirect(route('learn.rooms.live', $room));
        $this->get(route('learn.rooms.index'))->assertRedirect(route('learn.rooms.live', $room));
        $this->get(route('learn.rooms.live', $other))->assertForbidden();
        $this->postJson(route('learn.rooms.token', $other))->assertForbidden();
        $this->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => false])->assertForbidden();
    }

    public function test_denied_guests_and_switched_off_links_sign_the_guest_out(): void
    {
        [$host, $room, $token] = $this->liveRoomWithGuestLink();
        $guest = $this->joinAsGuest($token);

        $this->actingAs($host)->postJson(route('studio.rooms.guests.deny', [$room->id, $guest->id]))->assertOk();
        $this->actingAs($guest->fresh())->get(route('guest.wait', $token))->assertRedirect(route('guest.ended'));
        $this->assertGuest();

        // A new guest, then the host turns the link off.
        $second = $this->joinAsGuest($token, 'Second');
        $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => false])->assertOk()->assertJson(['guest_link' => null]);
        $this->actingAs($second->fresh())->getJson(route('learn.rooms.feed', $room))->assertForbidden()->assertJson(['reason' => 'guest_ended']);
        $this->assertGuest();
    }

    public function test_admit_all_and_a_new_link_after_turning_it_off(): void
    {
        [$host, $room, $token] = $this->liveRoomWithGuestLink();
        $a = $this->joinAsGuest($token, 'Ali');
        $b = $this->joinAsGuest($token, 'Bibi');

        $this->actingAs($host)->postJson(route('studio.rooms.guests.admit-all', $room->id))->assertOk()->assertJson(['admitted' => 2]);
        $this->assertNotNull($a->fresh()->guest_admitted_at);
        $this->assertNotNull($b->fresh()->guest_admitted_at);

        $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => false]);
        $new = $this->actingAs($host)->postJson(route('studio.rooms.guest-link', $room->id), ['enabled' => true])->json('guest_link');
        $this->assertNotSame($token, basename($new), 'the old link never comes back');
    }

    public function test_guest_names_are_checked_and_guests_are_never_emailed(): void
    {
        [, $room, $token] = $this->liveRoomWithGuestLink();

        auth()->guard('web')->logout();
        $this->post(route('guest.store', $token), ['name' => 'x'])->assertSessionHasErrors('name');
        $this->post(route('guest.store', $token), ['name' => str_repeat('a', 61)])->assertSessionHasErrors('name');

        $guest = $this->joinAsGuest($token, '<b>Amina</b>');
        $this->assertSame('Amina', $guest->name);
        $this->assertNull($guest->routeNotificationForMail());

        $room->update(['access' => 'public']);
        $this->assertFalse(app(LearningNotifier::class)->roomAudience($room)->whereKey($guest->id)->exists());
    }
}
