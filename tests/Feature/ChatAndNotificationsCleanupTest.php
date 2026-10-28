<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Chat: copy, links and who may delete; notifications: delete one or all. */
class ChatAndNotificationsCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $this->client = User::factory()->create(['is_admin' => false]);
    }

    public function test_an_admin_can_delete_a_clients_message_but_a_client_cannot_delete_an_admins(): void
    {
        $conversation = Conversation::create(['user_id' => $this->client->id]);
        $fromClient = ChatMessage::create(['conversation_id' => $conversation->id, 'is_from_admin' => false, 'type' => 'text', 'body' => 'My email is me@example.com']);
        $fromAdmin = ChatMessage::create(['conversation_id' => $conversation->id, 'is_from_admin' => true, 'type' => 'text', 'body' => 'Open https://mbuniehub.com/user/orders']);

        $this->actingAs($this->client)->deleteJson(route('user.chat.destroy', [$conversation, $fromAdmin]))->assertForbidden();
        $this->assertFalse((bool) $fromAdmin->fresh()->is_deleted);

        $this->actingAs($this->admin)->deleteJson(route('admin.chat.destroy', [$conversation, $fromClient]))
            ->assertOk()->assertJsonPath('message.deleted', true);
        $this->assertTrue((bool) $fromClient->fresh()->is_deleted);

        // The thread knows who may delete what, and renders links and the copy button.
        $this->actingAs($this->admin)->get(route('admin.chat.show', $conversation))->assertOk()
            ->assertSee('"deletable":true', false)->assertSee('linkify(m.body)', false)->assertSee('Copy message');
        $this->actingAs($this->client)->get(route('user.chat.show', $conversation))->assertOk()
            ->assertSee('"deletable":false', false)->assertSee('Copy message');
    }

    public function test_notifications_stay_after_reading_and_can_be_deleted_one_or_all(): void
    {
        foreach ([$this->admin, $this->client] as $user) {
            $side = $user->is_admin ? 'admin' : 'user';
            $ids = collect(['First', 'Second', 'Third'])->map(fn ($m) => $user->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['message' => $m],
            ])->id);

            // Reading keeps it.
            $this->actingAs($user)->post(route("{$side}.notifications.mark-read", $ids[0]))->assertRedirect();
            $this->assertSame(3, $user->notifications()->count());
            $this->actingAs($user)->get(route("{$side}.notifications.index"))->assertOk()->assertSee('First')->assertSee('Delete all');

            $this->actingAs($user)->delete(route("{$side}.notifications.destroy", $ids[0]))->assertRedirect();
            $this->assertSame(2, $user->notifications()->count());

            $this->actingAs($user)->delete(route("{$side}.notifications.destroy-all"))->assertRedirect();
            $this->assertSame(0, $user->notifications()->count());
        }
    }

    public function test_nobody_can_delete_someone_elses_notification(): void
    {
        $theirs = $this->admin->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['message' => 'Admin only']]);

        $this->actingAs($this->client)->delete(route('user.notifications.destroy', $theirs->id))->assertNotFound();
        $this->actingAs($this->client)->delete(route('user.notifications.destroy-all'))->assertRedirect();
        $this->assertSame(1, $this->admin->notifications()->count());
    }
}
