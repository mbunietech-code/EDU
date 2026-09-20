<?php

namespace Tests\Feature;

use App\Models\AdminGroup;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminGroupChatTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function lineAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => ['orders.view']]);
    }

    private function groupWith(User ...$members): AdminGroup
    {
        $group = AdminGroup::create(['name' => 'Support', 'created_by' => $members[0]->id]);
        $group->members()->sync(collect($members)->pluck('id')->all());

        return $group;
    }

    public function test_only_super_admins_can_create_groups(): void
    {
        $this->actingAs($this->lineAdmin());
        $this->get(route('admin.team-chat.groups.create'))->assertForbidden();
        $this->post(route('admin.team-chat.groups.store'), ['name' => 'X', 'members' => [1]])->assertForbidden();
    }

    public function test_super_admin_creates_group_with_only_real_admins_and_stays_a_member(): void
    {
        $super = $this->superAdmin();
        $helper = $this->lineAdmin();
        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($super);

        $this->post(route('admin.team-chat.groups.store'), [
            'name' => 'Support team',
            'members' => [$helper->id, $customer->id],
        ])->assertRedirect();

        $group = AdminGroup::firstOrFail();
        $ids = $group->members()->pluck('users.id')->all();
        $this->assertEqualsCanonicalizing([$super->id, $helper->id], $ids);
        $this->assertNotContains($customer->id, $ids);
    }

    public function test_members_chat_and_non_members_are_shut_out(): void
    {
        $a = $this->superAdmin();
        $b = $this->lineAdmin();
        $outsider = $this->lineAdmin();
        $group = $this->groupWith($a, $b);

        $this->actingAs($a);
        $this->postJson(route('admin.team-chat.groups.send', $group), ['type' => 'text', 'body' => 'hi team'])
            ->assertOk()->assertJsonPath('message.mine', true);

        $this->actingAs($b);
        $this->get(route('admin.team-chat.groups.show', $group))->assertOk()->assertSee('hi team');
        $fetched = $this->getJson(route('admin.team-chat.groups.fetch', $group))->assertOk();
        $this->assertSame($a->name, $fetched->json('messages.0.sender'));
        $this->assertFalse($fetched->json('messages.0.mine'));

        $this->actingAs($outsider);
        $this->get(route('admin.team-chat.groups.show', $group))->assertForbidden();
        $this->getJson(route('admin.team-chat.groups.fetch', $group))->assertForbidden();
        $this->postJson(route('admin.team-chat.groups.send', $group), ['type' => 'text', 'body' => 'let me in'])->assertForbidden();

        // A super admin who isn't a member is shut out too.
        $this->actingAs($this->superAdmin());
        $this->get(route('admin.team-chat.groups.show', $group))->assertForbidden();
    }

    public function test_unread_counts_follow_each_members_own_read_position(): void
    {
        $a = $this->superAdmin();
        $b = $this->lineAdmin();
        $group = $this->groupWith($a, $b);
        $group->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'one']);
        $group->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'two']);

        $this->assertSame(2, $group->unreadFor($b));
        $this->assertSame(0, $group->unreadFor($a));
        $this->assertSame(2, $b->unreadTeamChatMessagesCount());

        $this->actingAs($b)->get(route('admin.team-chat.groups.show', $group))->assertOk();

        $this->assertSame(0, $group->unreadFor($b));
        $this->assertSame(0, $b->fresh()->unreadTeamChatMessagesCount());
    }

    public function test_only_the_sender_can_edit_or_delete_a_message(): void
    {
        $a = $this->superAdmin();
        $b = $this->lineAdmin();
        $group = $this->groupWith($a, $b);
        $message = $group->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'original']);

        $this->actingAs($b);
        $this->putJson(route('admin.team-chat.groups.message.update', [$group, $message]), ['body' => 'hacked'])->assertForbidden();
        $this->deleteJson(route('admin.team-chat.groups.message.destroy', [$group, $message]))->assertForbidden();

        $this->actingAs($a);
        $this->putJson(route('admin.team-chat.groups.message.update', [$group, $message]), ['body' => 'fixed'])->assertOk();
        $this->assertSame('fixed', $message->fresh()->body);

        $this->deleteJson(route('admin.team-chat.groups.message.destroy', [$group, $message]))->assertOk();
        $this->assertTrue($message->fresh()->is_deleted);
    }

    public function test_members_can_share_an_image_and_download_it(): void
    {
        Storage::fake('private');
        $a = $this->superAdmin();
        $b = $this->lineAdmin();
        $group = $this->groupWith($a, $b);

        $this->actingAs($a);
        $response = $this->post(route('admin.team-chat.groups.send', $group), [
            'type' => 'image',
            'file' => UploadedFile::fake()->image('pic.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = $response->json('message.file');
        $this->assertNotNull($url);

        $this->actingAs($b);
        $this->get($url)->assertOk();
    }

    public function test_super_admin_updates_members_and_deletes_group(): void
    {
        $a = $this->superAdmin();
        $b = $this->lineAdmin();
        $c = $this->lineAdmin();
        $group = $this->groupWith($a, $b);
        $group->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'x']);

        $this->actingAs($a);
        $this->put(route('admin.team-chat.groups.update', $group), ['name' => 'Renamed', 'members' => [$a->id, $c->id]])->assertRedirect();

        $this->assertSame('Renamed', $group->fresh()->name);
        $this->assertEqualsCanonicalizing([$a->id, $c->id], $group->members()->pluck('users.id')->all());

        $this->delete(route('admin.team-chat.groups.destroy', $group))->assertRedirect(route('admin.team-chat.index'));
        $this->assertDatabaseMissing('admin_groups', ['id' => $group->id]);
        $this->assertDatabaseCount('admin_group_messages', 0);
    }

    public function test_index_lists_groups_and_line_admin_without_groups_still_lands_on_their_thread(): void
    {
        $super = $this->superAdmin();
        $line = $this->lineAdmin();
        $lonely = $this->lineAdmin();
        $this->groupWith($super, $line);

        $this->actingAs($super)->get(route('admin.team-chat.index'))->assertOk()->assertSee('Support');
        $this->actingAs($line)->get(route('admin.team-chat.index'))->assertOk()->assertSee('Support');

        $this->actingAs($lonely)->get(route('admin.team-chat.index'))->assertRedirect();
    }
}
