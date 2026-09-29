<?php

namespace Tests\Feature;

use App\Models\AdminConversation;
use App\Models\AdminGroup;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTeamChatPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $name = 'Boss'): User
    {
        return User::factory()->create(['name' => $name, 'is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function lineAdmin(string $name = 'Helper'): User
    {
        return User::factory()->create(['name' => $name, 'is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['orders.view']]);
    }

    public function test_starting_a_chat_is_one_private_pair_whichever_side_starts(): void
    {
        $a = $this->lineAdmin('Asha');
        $b = $this->superAdmin('Baraka');

        $this->actingAs($a)->post(route('admin.team-chat.start', $b))->assertRedirect();
        $this->actingAs($b)->post(route('admin.team-chat.start', $a))->assertRedirect();

        $this->assertSame(1, AdminGroup::count());
        $chat = AdminGroup::firstOrFail();
        $this->assertTrue($chat->isDirect());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $chat->members()->pluck('users.id')->all());
    }

    public function test_nobody_outside_the_pair_can_read_it_not_even_a_super_admin(): void
    {
        $a = $this->lineAdmin('Asha');
        $b = $this->superAdmin('Baraka');
        $chat = AdminGroup::directBetween($a, $b);

        $this->actingAs($a);
        $this->postJson(route('admin.team-chat.groups.send', $chat), ['type' => 'text', 'body' => 'siri yetu'])->assertOk();
        $message = $chat->messages()->firstOrFail();

        foreach ([$this->superAdmin('Other boss'), $this->lineAdmin('Other helper')] as $outsider) {
            $this->actingAs($outsider);
            $this->get(route('admin.team-chat.index'))->assertOk()->assertDontSee('siri yetu');
            $this->get(route('admin.team-chat.groups.show', $chat))->assertForbidden();
            $this->getJson(route('admin.team-chat.groups.fetch', $chat))->assertForbidden();
            $this->postJson(route('admin.team-chat.groups.send', $chat), ['type' => 'text', 'body' => 'x'])->assertForbidden();
            $this->get(route('admin.team-chat.groups.attachment', [$chat, $message]))->assertForbidden();
            $this->assertSame(0, $outsider->unreadTeamChatMessagesCount());
        }

        $this->actingAs($b);
        $this->get(route('admin.team-chat.index'))->assertOk()->assertSee('siri yetu')->assertSee('Asha');
        $this->get(route('admin.team-chat.groups.show', $chat))->assertOk()->assertSee('siri yetu');
        $this->assertNull($this->getJson(route('admin.team-chat.groups.fetch', $chat))->json('messages.0.sender'));
    }

    public function test_a_private_chat_cannot_be_renamed_extended_or_deleted(): void
    {
        $super = $this->superAdmin();
        $chat = AdminGroup::directBetween($super, $this->lineAdmin());
        $intruder = $this->superAdmin('Intruder');

        $this->actingAs($super);
        $this->get(route('admin.team-chat.groups.settings', $chat))->assertNotFound();
        $this->put(route('admin.team-chat.groups.update', $chat), ['name' => 'X', 'members' => [$intruder->id]])->assertNotFound();
        $this->delete(route('admin.team-chat.groups.destroy', $chat))->assertNotFound();

        $this->assertFalse($chat->hasMember($intruder));
    }

    public function test_cannot_start_a_chat_with_yourself_or_a_non_admin(): void
    {
        $a = $this->lineAdmin();
        $this->actingAs($a);

        $this->post(route('admin.team-chat.start', $a))->assertNotFound();
        $this->post(route('admin.team-chat.start', User::factory()->create(['is_admin' => false])))->assertNotFound();
        $this->assertSame(0, AdminGroup::count());
    }

    public function test_index_offers_every_other_admin_without_a_chat_yet(): void
    {
        $a = $this->lineAdmin('Asha');
        $b = $this->superAdmin('Baraka');
        $this->superAdmin('Chausiku');
        AdminGroup::directBetween($a, $b);

        $response = $this->actingAs($a)->get(route('admin.team-chat.index'))->assertOk();

        $this->assertSame(['Chausiku'], $response->viewData('others')->pluck('name')->all());
    }

    public function test_old_shared_threads_move_into_private_chats(): void
    {
        $helper = $this->lineAdmin('Asha');
        $boss = $this->superAdmin('Baraka');
        $otherBoss = $this->superAdmin('Chausiku');

        $answered = AdminConversation::create(['admin_id' => $helper->id]);
        $answered->messages()->create(['sender_id' => $helper->id, 'type' => 'text', 'body' => 'hello bosses']);
        $answered->messages()->create(['sender_id' => $boss->id, 'type' => 'text', 'body' => 'reply one']);
        $answered->messages()->create(['sender_id' => $boss->id, 'type' => 'text', 'body' => 'reply two']);
        $answered->messages()->create(['sender_id' => $otherBoss->id, 'type' => 'text', 'body' => 'me too']);

        $unanswered = AdminConversation::create(['admin_id' => $otherBoss->id]);
        $unanswered->messages()->create(['sender_id' => $otherBoss->id, 'type' => 'text', 'body' => 'anyone?']);

        $migration = require database_path('migrations/2026_09_29_000001_private_team_chat.php');
        (fn () => $this->moveLegacyThreads())->call($migration);

        $helperChat = AdminGroup::where('direct_key', AdminGroup::directKey($helper, $boss))->firstOrFail();
        $this->assertSame(['hello bosses', 'reply one', 'reply two', 'me too'], $helperChat->messages()->orderBy('id')->pluck('body')->all());
        $this->assertSame(0, $helperChat->unreadFor($helper));

        // No reply: goes to the first super admin.
        $bossesChat = AdminGroup::where('direct_key', AdminGroup::directKey($otherBoss, $boss))->firstOrFail();
        $this->assertSame(['anyone?'], $bossesChat->messages()->pluck('body')->all());

        $this->assertNotNull($answered->fresh()->migrated_at);
        $this->assertSame($boss->id, (int) $answered->fresh()->peer_id);

        // Running it again copies nothing twice.
        (fn () => $this->moveLegacyThreads())->call($migration);
        $this->assertSame(4, $helperChat->messages()->count());
    }
}
