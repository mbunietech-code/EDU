<?php

namespace Tests\Feature;

use App\Models\ChatbotMessage;
use App\Models\Setting;
use App\Models\User;
use App\Support\ChatbotSettings;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteChatbotTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): ChatbotSettings
    {
        return app(ChatbotSettings::class);
    }

    private function configure(string $provider = 'deepseek', ?string $key = 'sk-test-secret-1234', ?string $model = null): void
    {
        $s = $this->settings();
        $s->set('chatbot_enabled', '1');
        $s->set('chatbot_provider', $provider);
        if ($model !== null) {
            $s->set("chatbot_model_{$provider}", $model);
        }
        if ($key !== null) {
            $s->setKey($provider, $key);
        }
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function openAiShaped(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
    }

    public function test_widget_is_hidden_until_the_active_provider_has_a_key(): void
    {
        $this->get(route('public.home'))->assertOk()->assertDontSee('id="mb-bot"', false);

        $this->configure('deepseek');
        $this->get(route('public.home'))->assertOk()
            ->assertSee('id="mb-bot"', false)
            ->assertSee(route('public.chatbot.send'), false)
            ->assertDontSee('mbuniehub-chat', false)
            ->assertSee('aria-label="Chat with MbunieHub AI" title="Chat with MbunieHub AI"', false)
            ->assertDontSee('mb-bot__fab-label', false)
            ->assertSee('images/chatbot/mbuniehub-robot.webp', false)
            ->assertSee('images/chatbot/mbuniehub-robot-head.webp', false)
            ->assertSee('@keyframes mbh-wave', false)
            ->assertSee('prefers-reduced-motion: reduce', false)
            ->assertSee('MbunieHub AI')
            ->assertDontSee('sk-test-secret-1234');

        foreach (['mbuniehub-robot.png', 'mbuniehub-robot.webp', 'mbuniehub-robot-head.png', 'mbuniehub-robot-head.webp'] as $file) {
            $path = public_path('images/chatbot/'.$file);
            $this->assertFileExists($path);
            $this->assertLessThan(60 * 1024, filesize($path), $file.' should stay web-sized');
        }

        $this->settings()->set('chatbot_enabled', '0');
        $this->get(route('public.home'))->assertOk()->assertDontSee('id="mb-bot"', false);
    }

    public function test_the_bot_is_told_to_answer_in_the_visitors_language_even_with_a_custom_prompt(): void
    {
        $this->configure('deepseek');
        $this->settings()->set('chatbot_system_prompt', 'You are a helpful assistant for MbunieEduHub.');
        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiShaped('Bonjour !'))]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'Bonjour, combien coûte ChatGPT ?'])->assertOk();

        Http::assertSent(function (HttpRequest $request) {
            $system = $request['messages'][0]['content'];

            return str_starts_with($system, 'You are a helpful assistant for MbunieEduHub.')
                && str_ends_with($system, "Language: reply in the same language as the visitor's latest message (any language). Do not switch to Swahili unless the visitor wrote in Swahili.");
        });

        $this->assertStringContainsString('Never default to Swahili', ChatbotSettings::defaultSystemPrompt());

        // The chat window itself greets in English and says any language works.
        $this->get(route('public.home'))->assertOk()
            ->assertSee('Your assistant. Ask in any language')
            ->assertSee('in any language.', false)
            ->assertDontSee('Habari! Mimi ni MbunieHub AI')
            // Site colours (indigo), not the logo's navy/cyan.
            ->assertSee('--mbh-blue: #4f46e5', false)
            ->assertDontSee('#00b4f0', false);
    }

    public function test_guest_gets_a_deepseek_answer_with_the_key_sent_server_side_only(): void
    {
        $this->configure('deepseek');
        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiShaped('Karibu! Bei zipo kwenye ukurasa wa AI Tools.'))]);

        $response = $this->postJson(route('public.chatbot.send'), ['message' => 'Bei za AI tools ni kiasi gani?'])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'reply' => 'Karibu! Bei zipo kwenye ukurasa wa AI Tools.']);

        $this->assertStringNotContainsString('sk-test-secret-1234', $response->getContent());

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api.deepseek.com/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-secret-1234')
                && $request['model'] === 'deepseek-chat'
                && $request['messages'][0]['role'] === 'system'
                && str_contains($request['messages'][0]['content'], 'MbunieEduHub')
                && end($request->data()['messages'])['content'] === 'Bei za AI tools ni kiasi gani?';
        });

        $this->assertSame(2, ChatbotMessage::count());
        $this->assertSame(['ok', 'ok'], ChatbotMessage::orderBy('id')->pluck('status')->all());
    }

    public function test_follow_up_questions_include_the_earlier_turns_and_history_endpoint_returns_them(): void
    {
        $this->configure('deepseek');
        Http::fake(['api.deepseek.com/*' => Http::sequence()
            ->push($this->openAiShaped('Jibu la kwanza'))
            ->push($this->openAiShaped('Jibu la pili'))]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'Swali la kwanza'])->assertOk();
        $this->postJson(route('public.chatbot.send'), ['message' => 'Swali la pili'])->assertOk();

        $recorded = Http::recorded();
        $messages = $recorded[1][0]['messages'];
        $this->assertSame(['system', 'user', 'assistant', 'user'], array_column($messages, 'role'));
        $this->assertSame('Swali la kwanza', $messages[1]['content']);

        $this->getJson(route('public.chatbot.history'))->assertOk()
            ->assertJsonCount(4, 'messages')
            ->assertJsonPath('messages.3.content', 'Jibu la pili');
    }

    public function test_claude_provider_uses_the_messages_api_and_reads_only_text_blocks(): void
    {
        $this->configure('anthropic', 'sk-ant-test-9999');
        Http::fake(['api.anthropic.com/*' => Http::response([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'thinking', 'thinking' => '', 'signature' => 'x'],
                ['type' => 'text', 'text' => 'Hello from Claude'],
            ],
        ])]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'How do I pay?'])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'reply' => 'Hello from Claude']);

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'sk-ant-test-9999')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['model'] === 'claude-opus-5-5'
                && is_string($request['system'])
                && $request['messages'] === [['role' => 'user', 'content' => 'How do I pay?']]
                && $request['output_config'] === ['effort' => 'low'];
        });
    }

    public function test_claude_refusal_falls_back_to_the_support_message(): void
    {
        $this->configure('anthropic', 'sk-ant-test-9999', 'claude-haiku-5-5');
        Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'refusal', 'content' => []])]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'Hi'])
            ->assertOk()
            ->assertJson(['status' => 'error', 'support_url' => route('public.contact')]);
    }

    public function test_chatgpt_provider_uses_the_chosen_model(): void
    {
        $this->configure('openai', 'sk-proj-test-7777', 'gpt-4.1-mini');
        Http::fake(['api.openai.com/*' => Http::response($this->openAiShaped('Hi from ChatGPT'))]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'Hello'])
            ->assertOk()->assertJson(['status' => 'ok', 'reply' => 'Hi from ChatGPT']);

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.openai.com/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer sk-proj-test-7777')
            && $r['model'] === 'gpt-4.1-mini'
            && isset($r['max_completion_tokens']));
    }

    public function test_without_a_key_the_bot_answers_with_a_friendly_support_message_and_calls_nobody(): void
    {
        $this->configure('deepseek', null);
        Http::fake();

        $this->postJson(route('public.chatbot.send'), ['message' => 'Habari'])
            ->assertOk()
            ->assertJson(['status' => 'no_key', 'support_url' => route('public.contact')])
            ->assertJsonPath('reply', fn ($reply) => str_contains($reply, 'msaada'));

        Http::assertNothingSent();
    }

    public function test_provider_failure_returns_the_fallback_and_signed_in_customers_get_their_support_chat_link(): void
    {
        $this->configure('deepseek');
        Http::fake(['api.deepseek.com/*' => Http::response(['error' => ['message' => 'Insufficient balance']], 402)]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->postJson(route('public.chatbot.send'), ['message' => 'Habari'])
            ->assertOk()
            ->assertJson(['status' => 'error', 'support_url' => route('user.chat.index')]);

        $this->assertSame($user->id, ChatbotMessage::where('role', 'user')->value('user_id'));
    }

    public function test_long_and_empty_messages_are_rejected(): void
    {
        $this->configure('deepseek');
        Http::fake();

        $this->postJson(route('public.chatbot.send'), ['message' => str_repeat('a', ChatbotSettings::MAX_MESSAGE_LENGTH + 1)])->assertStatus(422);
        $this->postJson(route('public.chatbot.send'), ['message' => ''])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_per_chat_daily_limit_stops_provider_calls(): void
    {
        $this->configure('deepseek');
        $this->settings()->set('chatbot_session_daily_limit', '2');
        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiShaped('ok'))]);

        $this->postJson(route('public.chatbot.send'), ['message' => 'one'])->assertJson(['status' => 'ok']);
        $this->postJson(route('public.chatbot.send'), ['message' => 'two'])->assertJson(['status' => 'ok']);
        $this->postJson(route('public.chatbot.send'), ['message' => 'three'])->assertJson(['status' => 'limit']);

        Http::assertSentCount(2);
    }

    public function test_requests_are_throttled_per_ip(): void
    {
        $this->configure('deepseek', null);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('public.chatbot.send'), ['message' => "q{$i}"])->assertOk();
        }
        $this->postJson(route('public.chatbot.send'), ['message' => 'one more'])->assertStatus(429);
    }

    public function test_admin_saves_keys_encrypted_and_never_sees_them_again(): void
    {
        $this->actingAs($this->superAdmin());

        $this->put(route('admin.chatbot.update'), [
            'enabled' => '1',
            'provider' => 'anthropic',
            'system_prompt' => '',
            'daily_limit' => 300,
            'session_daily_limit' => 20,
            'model_anthropic' => 'claude-haiku-5-5',
            'key_anthropic' => 'sk-ant-real-secret-ABCD',
            'key_openai' => 'sk-openai-secret-WXYZ',
        ])->assertRedirect()->assertSessionHas('success');

        $stored = Setting::where('key', 'chatbot_key_anthropic')->value('value');
        $this->assertNotSame('sk-ant-real-secret-ABCD', $stored);
        $this->assertSame('sk-ant-real-secret-ABCD', Crypt::decryptString($stored));
        $this->assertSame('anthropic', $this->settings()->provider());
        $this->assertSame('claude-haiku-5-5', $this->settings()->model());
        $this->assertSame(300, $this->settings()->dailyLimit());

        $this->get(route('admin.chatbot.edit'))->assertOk()
            ->assertDontSee('sk-ant-real-secret-ABCD')
            ->assertDontSee('sk-openai-secret-WXYZ')
            ->assertSee('ABCD')
            ->assertSee('Live on the website');

        // The generic settings table must not list the chatbot keys either.
        $this->get(route('admin.settings.index'))->assertOk()->assertDontSee('chatbot_key_anthropic');

        // Empty key box keeps the saved key; switching provider works.
        $this->put(route('admin.chatbot.update'), [
            'enabled' => '1', 'provider' => 'openai', 'daily_limit' => 300, 'session_daily_limit' => 20,
        ])->assertRedirect();
        $this->assertSame('sk-ant-real-secret-ABCD', app(ChatbotSettings::class)->apiKey('anthropic'));
        $this->assertSame('openai', app(ChatbotSettings::class)->provider());

        $this->put(route('admin.chatbot.update'), [
            'enabled' => '1', 'provider' => 'openai', 'daily_limit' => 300, 'session_daily_limit' => 20,
            'remove_key_anthropic' => '1',
        ])->assertRedirect();
        $this->assertFalse(app(ChatbotSettings::class)->hasKey('anthropic'));
        $this->assertTrue(app(ChatbotSettings::class)->hasKey('openai'));
    }

    public function test_admin_test_button_calls_the_provider(): void
    {
        $this->actingAs($this->superAdmin());
        $this->configure('deepseek');
        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiShaped('Habari!'))]);

        $this->post(route('admin.chatbot.test'), ['provider' => 'deepseek'])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Habari!'));

        $this->post(route('admin.chatbot.test'), ['provider' => 'openai'])
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_only_admins_with_the_chatbot_permission_can_open_the_page(): void
    {
        $this->get(route('admin.chatbot.edit'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['settings.manage']]));
        $this->get(route('admin.chatbot.edit'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['chatbot.manage']]));
        $this->get(route('admin.chatbot.edit'))->assertOk();
    }
}
