<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Admin\AiAlert;
use App\Services\Ai\AiWatchdog;
use App\Services\AiAssistantTools;
use App\Support\AiAssistantSettings;
use App\Support\ChatbotSettings;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantDeepSeekTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function withKey(): void
    {
        app(AiAssistantSettings::class)->setKey('sk-test-123456');
    }

    /** A DeepSeek streamed answer (server-sent events). */
    private function sse(string $text): string
    {
        $out = '';
        foreach (str_split($text, 5) as $piece) {
            $out .= 'data: '.json_encode(['choices' => [['delta' => ['content' => $piece], 'finish_reason' => null]]])."\n\n";
        }
        $out .= 'data: '.json_encode(['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])."\n\n";

        return $out."data: [DONE]\n\n";
    }

    private function sseToolCall(string $name, string $args = '{}'): string
    {
        $chunks = [
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_1', 'type' => 'function', 'function' => ['name' => $name, 'arguments' => '']]]]]]],
            ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => $args]]]], 'finish_reason' => 'tool_calls']]],
        ];

        return implode('', array_map(fn ($c) => 'data: '.json_encode($c)."\n\n", $chunks))."data: [DONE]\n\n";
    }

    private function conversationFor(User $admin): AiConversation
    {
        $this->actingAs($admin)->get(route('admin.ai-assistant.index'))->assertOk();

        return AiConversation::where('user_id', $admin->id)->latest('id')->first();
    }

    public function test_page_shows_the_chatgpt_style_chat(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.ai-assistant.index'))
            ->assertOk()
            ->assertSee('aiChat(', false)
            ->assertSee('New chat')
            ->assertSee('Design a poster');
    }

    public function test_send_then_run_streams_the_deepseek_answer_into_the_reply(): void
    {
        $this->withKey();
        Http::fake(['api.deepseek.com/*' => Http::response($this->sse('Habari! **Kila kitu** kiko sawa.'), 200, ['Content-Type' => 'text/event-stream'])]);

        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);

        $sent = $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Habari yako?', 'wait' => false])
            ->assertOk()
            ->assertJsonPath('reply.status', 'streaming')
            ->assertJsonPath('user_message.content', 'Habari yako?');

        $replyId = $sent->json('reply.id');
        $this->getJson(route('admin.ai-assistant.messages.show', $replyId))->assertJsonPath('status', 'streaming');

        $this->postJson(route('admin.ai-assistant.messages.run', $replyId))
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('content', 'Habari! **Kila kitu** kiko sawa.');

        $this->getJson(route('admin.ai-assistant.messages.show', $replyId))->assertJsonPath('status', 'done');

        Http::assertSent(function (HttpRequest $r) {
            $body = $r->data();

            return $r->url() === 'https://api.deepseek.com/chat/completions'
                && $r->hasHeader('Authorization', 'Bearer sk-test-123456')
                && $body['stream'] === true
                && $body['messages'][0]['role'] === 'system'
                && end($body['messages'])['content'] === 'Habari yako?'
                && collect($body['tools'])->pluck('function.name')->contains('list_orders');
        });

        $this->assertSame('Habari yako?', $c->fresh()->title);
    }

    public function test_earlier_turns_are_sent_as_context(): void
    {
        $this->withKey();
        Http::fake(['api.deepseek.com/*' => Http::sequence()
            ->push($this->sse('Jina lako ni Asha.'))
            ->push($this->sse('Uliniambia jina lako ni Asha.'))]);

        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);

        $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Jina langu ni Asha'])->assertOk();
        $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Jina langu ni nani?'])->assertOk()
            ->assertJsonPath('content', 'Uliniambia jina lako ni Asha.');

        $requests = Http::recorded();
        $messages = $requests[1][0]->data()['messages'];
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($messages, 'role'));
        $this->assertSame('Jina langu ni Asha', $messages[1]['content']);
    }

    public function test_tool_calls_read_live_data_and_feed_it_back(): void
    {
        $this->withKey();
        User::factory()->count(2)->create();
        Http::fake(['api.deepseek.com/*' => Http::sequence()
            ->push($this->sseToolCall('get_users_summary'))
            ->push($this->sse('Tuna users 3.'))]);

        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);

        $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Tuna users wangapi?'])
            ->assertOk()
            ->assertJsonPath('content', 'Tuna users 3.')
            ->assertJsonPath('tools_used.0', 'get_users_summary');

        $second = Http::recorded()[1][0]->data()['messages'];
        $tool = collect($second)->firstWhere('role', 'tool');
        $this->assertSame('call_1', $tool['tool_call_id']);
        $this->assertSame(3, json_decode($tool['content'], true)['total']);
        $this->assertSame('get_users_summary', collect($second)->firstWhere('role', 'assistant')['tool_calls'][0]['function']['name']);
    }

    public function test_tools_respect_the_admins_permissions(): void
    {
        $tools = app(AiAssistantTools::class);
        $ordersOnly = $this->admin(['orders.view']);

        $names = collect($tools->definitions($ordersOnly))->pluck('function.name');
        $this->assertTrue($names->contains('list_orders'));
        $this->assertFalse($names->contains('list_payments'));
        $this->assertFalse($names->contains('get_database_health'));

        $this->assertArrayHasKey('error', $tools->call('list_payments', [], $ordersOnly));
        $this->assertArrayHasKey('orders', $tools->call('list_orders', [], $ordersOnly));
        $this->assertCount(count(AiAssistantTools::PERMISSIONS), $tools->definitions($this->superAdmin()));
    }

    public function test_user_tools_never_return_passwords(): void
    {
        $member = User::factory()->create(['name' => 'Juma Test', 'email' => 'juma@example.com']);

        $json = json_encode(app(AiAssistantTools::class)->call('get_user', ['id' => $member->id], $this->superAdmin()));

        $this->assertStringContainsString('juma@example.com', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString((string) $member->password, $json);
    }

    public function test_stop_cuts_the_reply_off(): void
    {
        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);
        $this->actingAs($admin);
        $reply = AiMessage::create(['conversation_id' => $c->id, 'role' => 'assistant', 'content' => 'Half', 'status' => AiMessage::STREAMING]);

        $this->postJson(route('admin.ai-assistant.messages.stop', $reply))->assertOk()->assertJsonPath('status', 'stopped');
        $this->postJson(route('admin.ai-assistant.messages.run', $reply))->assertOk()->assertJsonPath('status', 'stopped');
    }

    public function test_another_admin_cannot_poll_run_or_rename(): void
    {
        $owner = $this->superAdmin();
        $c = $this->conversationFor($owner);
        $reply = AiMessage::create(['conversation_id' => $c->id, 'role' => 'assistant', 'content' => '', 'status' => AiMessage::STREAMING]);

        $other = $this->superAdmin();
        $this->actingAs($other);
        $this->getJson(route('admin.ai-assistant.messages.show', $reply))->assertForbidden();
        $this->postJson(route('admin.ai-assistant.messages.run', $reply))->assertForbidden();
        $this->postJson(route('admin.ai-assistant.messages.stop', $reply))->assertForbidden();
        $this->patchJson(route('admin.ai-assistant.rename', $c), ['title' => 'Mine'])->assertForbidden();
    }

    public function test_rename_and_regenerate(): void
    {
        $this->withKey();
        Http::fake(['api.deepseek.com/*' => Http::sequence()->push($this->sse('Jibu la kwanza'))->push($this->sse('Jibu jipya'))]);

        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);
        $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Swali'])->assertOk();

        $this->patchJson(route('admin.ai-assistant.rename', $c), ['title' => 'Mpango wa wiki'])->assertOk()->assertJsonPath('title', 'Mpango wa wiki');

        $regen = $this->postJson(route('admin.ai-assistant.regenerate', $c))->assertOk();
        $this->postJson($regen->json('run_url'))->assertOk()->assertJsonPath('content', 'Jibu jipya');

        $this->assertSame(['user', 'assistant'], $c->messages()->pluck('role')->all());
        $this->assertSame('Jibu jipya', $c->messages()->get()->last()->content);
    }

    public function test_deepseek_failure_marks_the_reply_as_error(): void
    {
        $this->withKey();
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => ['message' => 'Insufficient Balance']], 402)]);

        $admin = $this->superAdmin();
        $c = $this->conversationFor($admin);

        $this->postJson(route('admin.ai-assistant.send', $c), ['message' => 'Habari'])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('content', fn ($text) => str_contains($text, 'Insufficient Balance'));
    }

    public function test_key_is_stored_encrypted_and_falls_back_to_the_chatbot_key(): void
    {
        $settings = app(AiAssistantSettings::class);
        $this->assertNull($settings->keySource());

        app(ChatbotSettings::class)->setKey('deepseek', 'sk-chatbot-9999');
        $settings = app(AiAssistantSettings::class);
        $this->assertSame('chatbot', $settings->keySource());
        $this->assertSame('sk-chatbot-9999', $settings->apiKey());

        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('admin.ai-assistant.settings.update'), [
            'key' => 'sk-own-abcd',
            'model' => 'deepseek-chat',
            'daily_limit' => 100,
            'alerts_enabled' => 1,
            'payment_wait' => 15,
            'chat_wait' => 3,
        ])->assertRedirect();

        $stored = Setting::where('key', 'ai_assistant_key_deepseek')->value('value');
        $this->assertNotSame('sk-own-abcd', $stored);
        $this->assertStringNotContainsString('sk-own-abcd', (string) $stored);

        $settings = app(AiAssistantSettings::class);
        $this->assertSame('own', $settings->keySource());
        $this->assertSame('sk-own-abcd', $settings->apiKey());
        $this->assertSame(15, $settings->paymentWaitMinutes());

        $this->actingAs($admin)->get(route('admin.ai-assistant.settings'))->assertOk()
            ->assertSee('••••abcd')
            ->assertDontSee('sk-own-abcd');
    }

    public function test_restricted_admin_cannot_open_settings(): void
    {
        $this->actingAs($this->admin(['orders.view']))->get(route('admin.ai-assistant.settings'))->assertForbidden();
    }

    // ------------------------------------------------------------- alerts

    private function pendingPayment(int $minutesAgo): Payment
    {
        $customer = User::factory()->create(['name' => 'Neema Customer']);
        $order = \App\Models\Order::factory()->create(['user_id' => $customer->id]);
        $id = DB::table('payments')->insertGetId([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'transaction_reference' => 'ABC123',
            'status' => 'pending',
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);

        return Payment::find($id);
    }

    public function test_a_waiting_payment_rings_the_admins_who_can_approve_it_once(): void
    {
        $boss = $this->superAdmin();
        $cashier = $this->admin(['payments.manage', 'payments.view']);
        $orders = $this->admin(['orders.view']);
        $payment = $this->pendingPayment(30);
        $this->pendingPayment(2); // too new to ring

        $this->assertSame(1, app(AiWatchdog::class)->scan());
        $this->assertSame(0, app(AiWatchdog::class)->scan()); // once only

        $this->assertSame(1, $boss->notifications()->where('type', AiAlert::class)->count());
        $this->assertSame(1, $cashier->notifications()->where('type', AiAlert::class)->count());
        $this->assertSame(0, $orders->notifications()->where('type', AiAlert::class)->count());

        // It rings in the popup on every admin page...
        $ring = $this->actingAs($cashier)->getJson(route('admin.video-help.ringing'))
            ->assertOk()
            ->assertJsonCount(0, 'calls')
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.url', route('admin.payments.show', $payment))
            ->assertJsonPath('alerts.0.ask_url', null); // the cashier has no AI Assistant

        $this->assertStringContainsString('Neema Customer', $ring->json('alerts.0.message'));
        $this->actingAs($boss)->getJson(route('admin.video-help.ringing'))
            ->assertJsonPath('alerts.0.ask_url', fn ($url) => str_contains($url, 'ai-assistant?ask='));

        // ...until dismissed.
        $this->postJson($ring->json('alerts.0.dismiss_url'))->assertOk(); // the boss cannot dismiss the cashier's alert
        $this->actingAs($cashier)->getJson(route('admin.video-help.ringing'))->assertJsonCount(1, 'alerts');
        $this->actingAs($cashier)->postJson($ring->json('alerts.0.dismiss_url'))->assertOk();
        $this->actingAs($cashier)->getJson(route('admin.video-help.ringing'))->assertJsonCount(0, 'alerts');

        $this->actingAs($orders)->get(route('admin.orders.index'))->assertOk()->assertSee('videoHelpRinger', false);
    }

    public function test_a_customer_waiting_in_chat_rings_support_but_the_auto_reply_does_not_count(): void
    {
        $support = $this->admin(['chat.manage']);
        $customer = User::factory()->create();
        $conversation = Conversation::create(['user_id' => $customer->id]);
        $q = ChatMessage::create(['conversation_id' => $conversation->id, 'is_from_admin' => false, 'type' => 'text', 'body' => 'Account yangu haifunguki']);
        $q->forceFill(['created_at' => now()->subMinutes(10)])->save();
        ChatMessage::create(['conversation_id' => $conversation->id, 'is_from_admin' => true, 'is_auto' => true, 'type' => 'text', 'body' => 'We will reply soon']);

        $this->assertSame(1, app(AiWatchdog::class)->scan());

        $alert = $support->notifications()->where('type', AiAlert::class)->first();
        $this->assertSame('chat', $alert->data['kind']);
        $this->assertStringContainsString('Account yangu haifunguki', $alert->data['message']);
        $this->assertSame(route('admin.chat.show', $conversation), $alert->data['url']);

        // A human reply ends it.
        ChatMessage::create(['conversation_id' => $conversation->id, 'is_from_admin' => true, 'type' => 'text', 'body' => 'Tunakusaidia sasa']);
        $this->assertSame([], collect(app(AiWatchdog::class)->problems())->where('kind', 'chat')->all());
    }

    public function test_alerts_can_be_switched_off(): void
    {
        $this->superAdmin();
        $this->pendingPayment(40);
        app(AiAssistantSettings::class)->set('ai_assistant_alerts_enabled', '0');

        $this->assertSame(0, app(AiWatchdog::class)->scan());
        $this->artisan('ai:watch')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
