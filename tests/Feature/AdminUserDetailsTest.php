<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admins correct a user's name or email from the user's page; admin accounts
 * only by a super admin.
 */
class AdminUserDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = Permissions::ROLE_SUPER_ADMIN, ?array $permissions = null): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => $role, 'permissions' => $permissions]);
    }

    public function test_an_admin_changes_a_users_name_and_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($this->admin())
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee(route('admin.users.update', $user), false);

        $this->put(route('admin.users.update', $user), ['name' => 'Juma Ali', 'email' => 'new@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame('Juma Ali', $user->name);
    }

    public function test_the_email_must_be_valid_and_free(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $this->actingAs($this->admin());

        $this->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
        // Keeping the same email is fine.
        $this->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'mine@example.com'])->assertSessionHasNoErrors();
    }

    public function test_only_admins_who_manage_users_may_do_it(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->actingAs($this->admin(Permissions::ROLE_ADMIN, ['users.view']))
            ->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'hack@example.com'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.users.update', $user), ['name' => 'X', 'email' => 'hack@example.com'])
            ->assertForbidden();

        $this->assertSame('keep@example.com', $user->fresh()->email);
    }

    public function test_another_admins_account_needs_a_super_admin(): void
    {
        $other = $this->admin(Permissions::ROLE_ADMIN);
        $manager = $this->admin(Permissions::ROLE_ADMIN, ['users.view', 'users.manage']);

        $this->actingAs($manager)
            ->put(route('admin.users.update', $other), ['name' => 'X', 'email' => 'taken-over@example.com'])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $other), ['name' => 'X', 'email' => 'fixed@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertSame('fixed@example.com', $other->fresh()->email);
    }
}
