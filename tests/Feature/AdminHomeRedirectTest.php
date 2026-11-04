<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A signed-in admin who opens the site in another tab (e.g. during a live
 * class) lands in the admin panel, never on the customer side.
 */
class AdminHomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    public function test_admin_opening_the_customer_dashboard_goes_to_the_admin_panel(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_signed_in_admin_opening_the_sign_in_page_goes_to_the_admin_panel(): void
    {
        $this->actingAs($this->admin())->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_signed_in_customer_opening_the_sign_in_page_goes_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_customer_still_sees_their_own_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('dashboard'))
            ->assertOk();
    }

    public function test_admin_session_still_works_on_the_live_class_pages_and_admin_panel(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('learn.rooms.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }
}
