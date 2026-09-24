<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Services\AutoReplyService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChatAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    private function customerChat(): array
    {
        $user = User::factory()->create(['name' => 'Juma', 'is_admin' => false]);

        return [$user, Conversation::create(['user_id' => $user->id])];
    }

    private function say(Conversation $c, bool $fromAdmin, int $minutesAgo, bool $auto = false, bool $deleted = false, string $body = 'ujumbe')
    {
        $m = $c->messages()->create([
            'is_from_admin' => $fromAdmin,
            'is_auto' => $auto,
            'is_deleted' => $deleted,
            'type' => 'text',
            'body' => $body,
        ]);
        $m->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        return $m;
    }

    private function autoMessages(Conversation $c)
    {
        return $c->messages()->where('is_auto', true)->get();
    }

    public function test_no_reply_before_the_waiting_time_is_up(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $this->say($c, false, 5);

        $this->assertFalse(app(AutoReplyService::class)->processConversation($c));
        $this->assertCount(0, $this->autoMessages($c));
    }

    public function test_replies_once_the_message_has_waited_long_enough_and_notifies_the_customer(): void
    {
        Notification::fake();
        [$user, $c] = $this->customerChat();
        $this->say($c, false, 11);

        $this->assertTrue(app(AutoReplyService::class)->processConversation($c));

        $auto = $this->autoMessages($c);
        $this->assertCount(1, $auto);
        $this->assertTrue($auto[0]->is_from_admin);
        $this->assertStringContainsString('Juma', $auto[0]->body);
        Notification::assertSentTo($user, ChatMessageReceived::class);
    }

    public function test_no_reply_when_someone_already_answered(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $this->say($c, false, 20);
        $this->say($c, true, 15);

        $this->assertFalse(app(AutoReplyService::class)->processConversation($c));
        $this->assertCount(0, $this->autoMessages($c));
    }

    public function test_only_one_auto_reply_until_a_person_responds(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $service = app(AutoReplyService::class);

        $this->say($c, false, 30);
        $this->assertTrue($service->processConversation($c));

        // Customer writes again and waits — still no second auto-reply.
        $this->say($c, false, 12);
        $this->assertFalse($service->processConversation($c));
        $this->assertCount(1, $this->autoMessages($c));

        // A person answers, then the customer waits again: a new auto-reply is allowed.
        $this->say($c, true, 8);
        $this->say($c, false, 11);
        $this->assertTrue($service->processConversation($c));
        $this->assertCount(2, $this->autoMessages($c));
    }

    public function test_switched_off_sends_nothing(): void
    {
        Notification::fake();
        Setting::set('autoreply_enabled', '0', 'string', 'autoreply');
        [, $c] = $this->customerChat();
        $this->say($c, false, 30);

        $this->assertFalse(app(AutoReplyService::class)->processConversation($c));
        $this->assertSame(0, app(AutoReplyService::class)->processAll());
    }

    public function test_old_forgotten_chats_are_left_alone(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $this->say($c, false, 60 * 30); // 30 hours ago

        $this->assertFalse(app(AutoReplyService::class)->processConversation($c));
        $this->assertSame(0, app(AutoReplyService::class)->processAll());
    }

    public function test_deleted_customer_messages_do_not_count(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $this->say($c, false, 30, deleted: true);

        $this->assertFalse(app(AutoReplyService::class)->processConversation($c));
    }

    public function test_custom_wait_time_and_message_are_used(): void
    {
        Notification::fake();
        Setting::set('autoreply_minutes', '30', 'integer', 'autoreply');
        Setting::set('autoreply_message', 'Karibu {name}, tunakuja!', 'string', 'autoreply');
        [, $c] = $this->customerChat();
        $service = app(AutoReplyService::class);

        $this->say($c, false, 20);
        $this->assertFalse($service->processConversation($c)); // 20 < 30

        $this->say($c, false, 31);
        $this->assertTrue($service->processConversation($c));
        $this->assertSame('Karibu Juma, tunakuja!', $this->autoMessages($c)[0]->body);
    }

    public function test_command_replies_to_every_waiting_conversation(): void
    {
        Notification::fake();
        [, $a] = $this->customerChat();
        [, $b] = $this->customerChat();
        [, $fresh] = $this->customerChat();
        $this->say($a, false, 15);
        $this->say($b, false, 40);
        $this->say($fresh, false, 2);

        $this->artisan('chat:auto-reply')->expectsOutput('Sent 2 auto-replies.')->assertSuccessful();

        $this->assertCount(1, $this->autoMessages($a));
        $this->assertCount(1, $this->autoMessages($b));
        $this->assertCount(0, $this->autoMessages($fresh));
    }

    public function test_customer_polling_their_chat_triggers_the_reply_without_cron(): void
    {
        Notification::fake();
        [$user, $c] = $this->customerChat();
        $this->say($c, false, 12);

        $response = $this->actingAs($user)->getJson(route('user.chat.fetch', $c))->assertOk();

        $this->assertTrue($response->json('messages.0.fromAdmin'));
        $this->assertTrue($response->json('messages.0.auto'));
        $this->assertCount(1, $this->autoMessages($c));

        // Polling again must not add another.
        $this->actingAs($user)->getJson(route('user.chat.fetch', $c));
        $this->assertCount(1, $this->autoMessages($c));
    }

    public function test_only_admins_who_can_manage_chat_can_edit_the_settings(): void
    {
        $viewer = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['chat.view']]);
        $this->actingAs($viewer)->get(route('admin.chat.auto-reply.edit'))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.chat.auto-reply.update'), ['minutes' => 5, 'message' => 'x'])->assertForbidden();

        $super = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->actingAs($super)->get(route('admin.chat.auto-reply.edit'))->assertOk()->assertSee('Habari {name}');

        $this->actingAs($super)->put(route('admin.chat.auto-reply.update'), ['minutes' => 15, 'message' => 'Subiri kidogo {name}'])->assertRedirect();

        $service = app(AutoReplyService::class);
        $this->assertFalse($service->enabled()); // checkbox omitted = switched off
        $this->assertSame(15, $service->minutes());
        $this->assertSame('Subiri kidogo {name}', $service->messageTemplate());

        $this->actingAs($super)->put(route('admin.chat.auto-reply.update'), ['minutes' => 0, 'message' => 'x'])->assertSessionHasErrors('minutes');
    }

    public function test_reply_is_in_the_language_the_customer_wrote_in(): void
    {
        Notification::fake();
        Setting::set('autoreply_message_en', 'Hi {name}, we will be with you shortly.', 'string', 'autoreply');
        $service = app(AutoReplyService::class);

        [, $english] = $this->customerChat();
        $this->say($english, false, 12, body: 'Hello, I am still waiting for a reply please');
        $this->assertTrue($service->processConversation($english));
        $this->assertSame('Hi Juma, we will be with you shortly.', $this->autoMessages($english)[0]->body);

        [, $swahili] = $this->customerChat();
        $this->say($swahili, false, 12, body: 'Habari, naomba msaada wenu tafadhali');
        $this->assertTrue($service->processConversation($swahili));
        $this->assertStringStartsWith('Habari Juma', $this->autoMessages($swahili)[0]->body);
    }

    public function test_falls_back_to_swahili_when_the_language_cannot_be_told(): void
    {
        Notification::fake();
        [, $c] = $this->customerChat();
        $this->say($c, false, 12, body: '');

        $this->assertTrue(app(AutoReplyService::class)->processConversation($c));
        $this->assertStringStartsWith('Habari Juma', $this->autoMessages($c)[0]->body);
    }

    public function test_english_message_can_be_edited_in_settings(): void
    {
        $super = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);

        $this->actingAs($super)->put(route('admin.chat.auto-reply.update'), [
            'enabled' => 1,
            'minutes' => 10,
            'message' => 'Subiri kidogo {name}',
            'message_en' => 'Please wait {name}',
        ])->assertRedirect();

        $this->assertSame('Please wait {name}', app(AutoReplyService::class)->messageTemplate('en'));
        $this->actingAs($super)->get(route('admin.chat.auto-reply.edit'))->assertOk()->assertSee('Please wait {name}');
    }
}
