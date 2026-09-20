<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_floating_button_pointing_to_login(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('images/mbunie-ai.webp', false)
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_customers_get_the_button_to_their_chat_with_an_unread_badge(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $conversation = Conversation::create(['user_id' => $user->id]);
        $conversation->messages()->create(['is_from_admin' => true, 'type' => 'text', 'body' => 'Karibu', 'is_read' => false]);
        $conversation->messages()->create(['is_from_admin' => true, 'type' => 'text', 'body' => 'Habari', 'is_read' => false]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('user.chat.index').'"', false)
            ->assertSee('Ongea na msaada wetu');
    }

    public function test_button_is_hidden_on_the_chat_page_itself(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $conversation = Conversation::create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('user.chat.show', $conversation))
            ->assertOk()
            ->assertDontSee('images/mbunie-ai.webp', false);
    }

    public function test_super_admin_gets_the_button_to_the_ai_assistant_but_not_on_chat_pages(): void
    {
        $super = User::factory()->create(['is_admin' => true, 'role' => \App\Support\Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->actingAs($super);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.ai-assistant.index').'" class="mb-chat-fab"', false)
            ->assertSee('Uliza Mbunie AI');

        $this->get(route('admin.ai-assistant.index'))->assertOk()->assertDontSee('mb-chat-fab"', false);
        $this->get(route('admin.team-chat.index'))->assertOk()->assertDontSee('mb-chat-fab"', false);
    }

    public function test_limited_admin_without_ai_access_does_not_get_the_button(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'role' => \App\Support\Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view'],
        ]));

        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('mb-chat-fab"', false);
    }
}
