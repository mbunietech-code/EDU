<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]);
    }

    private function restrictedAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view'],
        ]);
    }

    public function test_restricted_admin_cannot_reach_ai_assistant(): void
    {
        $admin = $this->restrictedAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.ai-assistant.index'))->assertForbidden();
        $this->post(route('admin.ai-assistant.new'))->assertForbidden();
    }

    public function test_visiting_with_no_history_creates_one_empty_conversation(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.ai-assistant.index'))->assertOk();

        $this->assertSame(1, AiConversation::where('user_id', $admin->id)->count());
    }

    public function test_quick_question_answers_and_sets_conversation_title(): void
    {
        User::factory()->count(3)->create();
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $admin->id)->first();

        $response = $this->postJson(route('admin.ai-assistant.send', $conversation), [
            'message' => 'Users summary',
            'question_id' => 'users',
        ]);

        $response->assertOk()->assertJsonPath('tools_used.0', 'get_users_summary');
        $this->assertStringContainsString('4', $response->json('content')); // super admin + 3 factory users

        $conversation->refresh();
        $this->assertSame('Users summary', $conversation->title);
    }

    public function test_free_text_keyword_resolves_to_the_right_tool(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $admin->id)->first();

        $response = $this->postJson(route('admin.ai-assistant.send', $conversation), [
            'message' => 'ni malipo mangapi yamekubaliwa',
        ]);

        $response->assertOk()->assertJsonPath('tools_used.0', 'get_payments_summary');
    }

    public function test_unrecognized_free_text_returns_help_with_no_tools_used(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $admin->id)->first();

        $response = $this->postJson(route('admin.ai-assistant.send', $conversation), [
            'message' => 'asdkjaslkdjaslkdj',
        ]);

        $response->assertOk();
        $this->assertNull($response->json('tools_used'));
        $this->assertStringContainsString('Sikuweza', $response->json('content'));
    }

    public function test_new_conversation_button_starts_a_separate_thread_kept_in_history(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.ai-assistant.index'));
        $first = AiConversation::where('user_id', $admin->id)->first();
        $this->postJson(route('admin.ai-assistant.send', $first), ['message' => 'health', 'question_id' => 'health']);

        $response = $this->post(route('admin.ai-assistant.new'));
        $second = AiConversation::where('user_id', $admin->id)->latest('id')->first();
        $response->assertRedirect(route('admin.ai-assistant.show', $second));

        $this->assertNotEquals($first->id, $second->id);
        $this->assertSame(2, AiConversation::where('user_id', $admin->id)->count());

        // Both threads still show up in this admin's history.
        $this->get(route('admin.ai-assistant.show', $first))->assertOk();
    }

    public function test_admin_cannot_open_or_message_another_admins_conversation(): void
    {
        $owner = $this->superAdmin();
        $this->actingAs($owner);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $owner->id)->first();

        $other = $this->superAdmin();
        $this->actingAs($other);

        $this->get(route('admin.ai-assistant.show', $conversation))->assertForbidden();
        $this->postJson(route('admin.ai-assistant.send', $conversation), ['message' => 'hi'])->assertForbidden();
    }

    public function test_admin_can_delete_own_conversation(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $admin->id)->first();
        $this->postJson(route('admin.ai-assistant.send', $conversation), ['message' => 'health', 'question_id' => 'health']);

        $this->deleteJson(route('admin.ai-assistant.destroy', $conversation))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['conversation_id' => $conversation->id]); // cascade delete
    }

    public function test_admin_cannot_delete_another_admins_conversation(): void
    {
        $owner = $this->superAdmin();
        $this->actingAs($owner);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $owner->id)->first();

        $other = $this->superAdmin();
        $this->actingAs($other);

        $this->deleteJson(route('admin.ai-assistant.destroy', $conversation))->assertForbidden();
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_messages_persist_within_a_conversation(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->get(route('admin.ai-assistant.index'));
        $conversation = AiConversation::where('user_id', $admin->id)->first();

        $this->postJson(route('admin.ai-assistant.send', $conversation), ['message' => 'accounts', 'question_id' => 'accounts']);
        $this->postJson(route('admin.ai-assistant.send', $conversation), ['message' => 'health', 'question_id' => 'health']);

        $this->assertSame(4, AiMessage::where('conversation_id', $conversation->id)->count()); // 2 user + 2 assistant
    }
}
