<?php

namespace Tests\Feature;

use App\Models\LearningRoom;
use App\Models\User;
use App\Models\VideoHelpRequest;
use App\Notifications\Admin\VideoHelpCalling;
use App\Notifications\Admin\VideoHelpMissed;
use App\Notifications\Learning\LiveSessionStarted;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Video help: a member asks for a video call, every support admin is rung
 * until one accepts (atomically), both meet in the support live room;
 * decline, cancel and missed calls.
 */
class VideoHelpTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    private LearningRoom $room;

    /** The room's host: a super admin, so also rung. */
    private User $host;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->useLiveServer();

        $this->host = $host = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN]);
        $this->room = LearningRoom::create([
            'title' => 'MbunieEduHub Support & Help Desk',
            'host_id' => $host->id,
            'status' => 'completed',
            'access' => 'public',
            'duration_minutes' => 1435,
        ]);

        config([
            'learning.video_help.room_id' => $this->room->id,
            'learning.video_help.ring_seconds' => 120,
        ]);
    }

    private function member(): User
    {
        return User::factory()->create();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function ringFor(User $member, ?string $reason = 'My plan is not active'): VideoHelpRequest
    {
        $this->actingAs($member)
            ->postJson(route('user.video-help.store'), ['reason' => $reason])
            ->assertCreated()
            ->assertJsonPath('status', 'ringing');

        return VideoHelpRequest::query()->where('user_id', $member->id)->latest('id')->firstOrFail();
    }

    public function test_member_page_and_sidebar_tab_render(): void
    {
        $member = $this->member();

        $this->actingAs($member)->get(route('user.video-help.index'))
            ->assertOk()
            ->assertSee('Call support on video')
            ->assertSee(route('user.video-help.index'), false);
    }

    public function test_requesting_rings_every_support_admin_once(): void
    {
        $super = $this->superAdmin();
        $answerer = $this->admin(['video_help.answer']);
        $chatStaff = $this->admin(['chat.manage']);
        $other = $this->admin(['orders.view']);
        $member = $this->member();

        $call = $this->ringFor($member);

        $this->assertSame(VideoHelpRequest::RINGING, $call->status);
        $this->assertSame('My plan is not active', $call->reason);
        $this->assertSame($this->room->id, $call->learning_room_id);
        $this->assertTrue($call->ring_until->isFuture());

        Notification::assertSentTo([$super, $answerer, $chatStaff], VideoHelpCalling::class);
        Notification::assertNotSentTo([$other, $member], VideoHelpCalling::class);

        // Asking again while it rings returns the same call (no second ring).
        $this->actingAs($member)->postJson(route('user.video-help.store'))->assertCreated()->assertJsonPath('id', $call->id);
        $this->assertSame(1, VideoHelpRequest::count());
    }

    public function test_status_polling_belongs_to_the_caller_only(): void
    {
        $member = $this->member();
        $call = $this->ringFor($member);

        $this->actingAs($member)->getJson(route('user.video-help.status', $call))
            ->assertOk()
            ->assertJsonPath('status', 'ringing')
            ->assertJsonPath('join_url', null);

        $this->actingAs($this->member())->getJson(route('user.video-help.status', $call))->assertNotFound();
        $this->actingAs($this->member())->postJson(route('user.video-help.cancel', $call))->assertNotFound();
    }

    public function test_admins_see_the_ringing_call_and_the_popup_only_with_permission(): void
    {
        $answerer = $this->admin(['video_help.answer']);
        $other = $this->admin(['orders.view']);
        $member = $this->member();
        $call = $this->ringFor($member);

        $this->actingAs($answerer)->getJson(route('admin.video-help.ringing'))
            ->assertOk()
            ->assertJsonCount(1, 'calls')
            ->assertJsonPath('calls.0.id', $call->id)
            ->assertJsonPath('calls.0.name', $member->name)
            ->assertJsonPath('calls.0.reason', 'My plan is not active');

        $this->actingAs($other)->getJson(route('admin.video-help.ringing'))->assertForbidden();
        $this->actingAs($member)->getJson(route('admin.video-help.ringing'))->assertForbidden();
        $this->actingAs($other)->postJson(route('admin.video-help.accept', $call))->assertForbidden();

        // The ringer is on admin pages for answerers only.
        $this->actingAs($answerer)->get(route('admin.video-help.index'))->assertOk()
            ->assertSee('videoHelpRinger', false)
            ->assertSee($member->name);
        $this->actingAs($other)->get(route('admin.video-help.index'))->assertForbidden();
    }

    public function test_only_the_first_admin_to_accept_wins_and_both_go_to_the_room(): void
    {
        $first = $this->superAdmin();
        $second = $this->admin(['video_help.answer']);
        $member = $this->member();
        $call = $this->ringFor($member);

        $liveUrl = route('learn.rooms.live', $this->room);

        $this->actingAs($first)->postJson(route('admin.video-help.accept', $call))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('url', $liveUrl);

        $this->actingAs($second)->postJson(route('admin.video-help.accept', $call))
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', $first->name.' already answered this call.');

        $call->refresh();
        $this->assertSame(VideoHelpRequest::ACCEPTED, $call->status);
        $this->assertSame($first->id, (int) $call->accepted_by);

        // The support room was opened quietly: no "class started" blast to every member.
        $this->assertTrue($this->room->fresh()->isLive());
        Notification::assertNotSentTo($member, LiveSessionStarted::class);

        // The popup is gone for everyone; the member is sent into the room.
        $this->actingAs($second)->getJson(route('admin.video-help.ringing'))->assertJsonCount(0, 'calls');
        $this->actingAs($member)->getJson(route('user.video-help.status', $call))
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('join_url', $liveUrl)
            ->assertJsonPath('answered_by', $first->name);

        // The member can join the live room; the admin who answered joins as host.
        $this->actingAs($member)->postJson(route('learn.rooms.join', $this->room))->assertOk()->assertJsonPath('config.moderator', false);
        $this->actingAs($first)->postJson(route('learn.rooms.join', $this->room))->assertOk()->assertJsonPath('config.moderator', true);

        // A double click by the winner is harmless.
        $this->actingAs($first)->postJson(route('admin.video-help.accept', $call))->assertOk();
    }

    public function test_decline_stops_ringing_for_that_admin_and_all_declines_make_it_missed(): void
    {
        $a = $this->superAdmin();
        $b = $this->admin(['video_help.answer']);
        $member = $this->member();
        $call = $this->ringFor($member);

        $this->actingAs($a)->postJson(route('admin.video-help.decline', $call))->assertOk();

        $this->actingAs($a)->getJson(route('admin.video-help.ringing'))->assertJsonCount(0, 'calls');
        $this->actingAs($b)->getJson(route('admin.video-help.ringing'))->assertJsonCount(1, 'calls');
        $this->assertSame(VideoHelpRequest::RINGING, $call->fresh()->status);

        $this->actingAs($b)->postJson(route('admin.video-help.decline', $call))->assertOk();
        $this->assertSame(VideoHelpRequest::RINGING, $call->fresh()->status, 'the room host has not declined yet');

        $this->actingAs($this->host)->postJson(route('admin.video-help.decline', $call))->assertOk();
        $this->assertSame(VideoHelpRequest::MISSED, $call->fresh()->status);

        $this->actingAs($member)->getJson(route('user.video-help.status', $call))
            ->assertJsonPath('status', 'missed')
            ->assertJsonPath('chat_url', route('user.chat.index'));
    }

    public function test_unanswered_call_becomes_missed_and_admins_are_told(): void
    {
        $admin = $this->superAdmin();
        $member = $this->member();
        $call = $this->ringFor($member);

        $this->travel(121)->seconds();

        $this->actingAs($member)->getJson(route('user.video-help.status', $call))
            ->assertOk()
            ->assertJsonPath('status', 'missed')
            ->assertJsonPath('chat_url', route('user.chat.index'));

        Notification::assertSentTo($admin, VideoHelpMissed::class);
        $this->actingAs($admin)->getJson(route('admin.video-help.ringing'))->assertJsonCount(0, 'calls');
        $this->actingAs($admin)->postJson(route('admin.video-help.accept', $call))->assertStatus(409);

        // The member may call again.
        $this->actingAs($member)->postJson(route('user.video-help.store'))->assertCreated()->assertJsonPath('status', 'ringing');
        $this->assertSame(2, VideoHelpRequest::count());
    }

    public function test_the_scheduler_marks_missed_calls_too(): void
    {
        $admin = $this->superAdmin();
        $call = $this->ringFor($this->member());

        $this->travel(121)->seconds();
        $this->artisan('video-help:expire')->assertSuccessful();

        $this->assertSame(VideoHelpRequest::MISSED, $call->fresh()->status);
        Notification::assertSentToTimes($admin, VideoHelpMissed::class, 1);

        // Once only.
        $this->artisan('video-help:expire')->assertSuccessful();
        Notification::assertSentToTimes($admin, VideoHelpMissed::class, 1);
    }

    public function test_member_can_cancel_and_the_call_can_no_longer_be_answered(): void
    {
        $admin = $this->superAdmin();
        $member = $this->member();
        $call = $this->ringFor($member);

        $this->actingAs($member)->postJson(route('user.video-help.cancel', $call))
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->actingAs($admin)->getJson(route('admin.video-help.ringing'))->assertJsonCount(0, 'calls');
        $this->actingAs($admin)->postJson(route('admin.video-help.accept', $call))
            ->assertStatus(409)
            ->assertJsonPath('message', 'The member hung up.');
        $this->assertFalse($this->room->fresh()->isLive());
    }

    public function test_accept_is_refused_when_the_support_room_is_cancelled(): void
    {
        $admin = $this->superAdmin();
        $call = $this->ringFor($this->member());
        $this->room->forceFill(['status' => 'cancelled'])->save();

        $this->actingAs($admin)->postJson(route('admin.video-help.accept', $call))->assertStatus(409);
        $this->assertSame(VideoHelpRequest::RINGING, $call->fresh()->status);
    }
}
