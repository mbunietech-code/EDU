<?php

namespace Tests\Feature\Api;

use App\Models\AdminGroup;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAiAndTeamChatApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $name = 'Boss'): User
    {
        return User::factory()->create(['name' => $name, 'is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function admin(array $permissions = [], string $name = 'Staff'): User
    {
        return User::factory()->create(['name' => $name, 'is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    // --- AI assistant ------------------------------------------------------

    public function test_ai_assistant_conversation_from_the_app(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->getJson('/api/admin/ai-assistant')->assertOk()->assertJsonPath('data.quick_questions.0.id', 'optimization');

        $id = $this->postJson('/api/admin/ai-assistant')->assertCreated()->json('data.id');

        $this->postJson("/api/admin/ai-assistant/{$id}", ['message' => 'Users summary', 'question_id' => 'users'])
            ->assertOk()
            ->assertJsonPath('data.role', 'assistant');

        $this->getJson("/api/admin/ai-assistant/{$id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');

        $this->deleteJson("/api/admin/ai-assistant/{$id}")->assertOk();
    }

    public function test_ai_assistant_is_private_and_permissioned(): void
    {
        Sanctum::actingAs($owner = $this->superAdmin());
        $id = $this->postJson('/api/admin/ai-assistant')->json('data.id');

        Sanctum::actingAs($this->superAdmin('Other'));
        $this->getJson("/api/admin/ai-assistant/{$id}")->assertNotFound();

        Sanctum::actingAs($this->admin(['orders.view']));
        $this->getJson('/api/admin/ai-assistant')->assertForbidden();
    }

    // --- Team chat -----------------------------------------------------------

    public function test_private_chat_messages_edit_and_delete(): void
    {
        $a = $this->admin([], 'Asha');
        $b = $this->admin([], 'Baraka');
        Sanctum::actingAs($a);

        $this->getJson('/api/admin/team-chat')->assertOk()->assertJsonPath('data.others.0.name', 'Baraka');

        $chat = $this->postJson("/api/admin/team-chat/start/{$b->id}")->assertOk()->json('data.id');

        $msg = $this->postJson("/api/admin/team-chat/{$chat}/messages", ['type' => 'text', 'body' => 'Habari'])
            ->assertCreated()
            ->assertJsonPath('data.mine', true)
            ->json('data.id');

        $this->putJson("/api/admin/team-chat/{$chat}/messages/{$msg}", ['body' => 'Habari yako'])
            ->assertOk()->assertJsonPath('data.edited', true);

        Sanctum::actingAs($b);
        $this->getJson('/api/admin/team-chat')->assertJsonPath('data.chats.0.name', 'Asha')->assertJsonPath('data.chats.0.unread', 1);
        $this->getJson("/api/admin/team-chat/{$chat}/messages")
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Habari yako')
            ->assertJsonPath('data.messages.0.mine', false);
        // Baraka cannot edit Asha's message.
        $this->putJson("/api/admin/team-chat/{$chat}/messages/{$msg}", ['body' => 'x'])->assertForbidden();

        // Someone outside the pair cannot read it.
        Sanctum::actingAs($this->superAdmin());
        $this->getJson("/api/admin/team-chat/{$chat}/messages")->assertForbidden();
    }

    public function test_groups_and_attachments(): void
    {
        Storage::fake('private');
        $boss = $this->superAdmin();
        $staff = $this->admin([], 'Staff');
        Sanctum::actingAs($boss);

        $group = $this->postJson('/api/admin/team-chat/groups', ['name' => 'Finance team', 'members' => [$staff->id]])
            ->assertCreated()->json('data.id');
        $this->assertTrue(AdminGroup::find($group)->hasMember($boss));

        $msg = $this->post("/api/admin/team-chat/{$group}/messages", [
            'type' => 'image', 'file' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.has_file', true)->json('data.id');

        Sanctum::actingAs($staff);
        $this->get("/api/admin/team-chat/{$group}/messages/{$msg}/file")->assertOk();
        // Only super admins manage groups.
        $this->putJson("/api/admin/team-chat/groups/{$group}", ['name' => 'Mine', 'members' => [$staff->id]])->assertForbidden();
    }
}
