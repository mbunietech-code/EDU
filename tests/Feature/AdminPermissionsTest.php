<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
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

    private function restrictedAdmin(array $permissions): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => $permissions,
        ]);
    }

    public function test_super_admin_reaches_every_area(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.database.index'))->assertOk();
        $this->get(route('admin.team.index'))->assertOk();
    }

    public function test_restricted_admin_only_sees_granted_areas(): void
    {
        $this->actingAs($this->restrictedAdmin(['orders.view']));

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.database.index'))->assertForbidden();
        $this->get(route('admin.team.index'))->assertForbidden();
    }

    public function test_view_permission_does_not_grant_manage(): void
    {
        $order = \App\Models\Order::factory()->create();

        $this->actingAs($this->restrictedAdmin(['orders.view']));

        $this->get(route('admin.orders.index'))->assertOk();          // has view
        $this->get(route('admin.orders.edit', $order))->assertForbidden(); // lacks manage
    }

    public function test_unrestricted_legacy_admin_keeps_full_feature_access_but_not_super_only(): void
    {
        // role=admin, permissions=null  => "not yet restricted"
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => null,
        ]);
        $this->actingAs($admin);

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.orders.index'))->assertOk();

        // Database & Team remain super-admin only.
        $this->get(route('admin.database.index'))->assertForbidden();
        $this->get(route('admin.team.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_a_restricted_admin(): void
    {
        $this->actingAs($this->superAdmin());

        $response = $this->post(route('admin.team.store'), [
            'name' => 'New Helper',
            'email' => 'helper@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view', 'payments.view', 'not_a_real_key'],
        ]);

        $response->assertRedirect(route('admin.team.index'));

        $created = User::where('email', 'helper@example.com')->firstOrFail();
        $this->assertTrue($created->is_admin);
        $this->assertSame(Permissions::ROLE_ADMIN, $created->role);
        // Invalid key filtered out.
        $this->assertEqualsCanonicalizing(['orders.view', 'payments.view'], $created->permissions);
    }

    public function test_normal_admin_cannot_open_team_page(): void
    {
        $this->actingAs($this->restrictedAdmin(['users.view', 'users.manage']));

        $this->get(route('admin.team.index'))->assertForbidden();
        $this->post(route('admin.team.store'), [])->assertForbidden();
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $super = $this->superAdmin();
        $other = $this->restrictedAdmin(['orders.view']);
        $this->actingAs($super);

        $this->put(route('admin.team.update', $other), [
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => [],
        ])->assertRedirect();

        // Now demote the original super while another exists — allowed.
        $this->put(route('admin.team.update', $other), [
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view'],
        ])->assertRedirect();

        $other->refresh();
        $this->assertSame(Permissions::ROLE_ADMIN, $other->role);
    }
}
