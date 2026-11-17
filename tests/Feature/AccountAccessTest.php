<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Who may stay signed in, and what the admin dashboard shows to whom.
 */
class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    public function test_a_suspended_account_cannot_sign_in_on_the_website(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_signed_in_user_is_signed_out_on_the_next_page_once_suspended(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_suspended_admin_is_signed_out_of_the_admin_panel_too(): void
    {
        $admin = $this->admin(['orders.view']);
        $this->actingAs($admin)->get(route('admin.orders.index'))->assertOk();

        $admin->forceFill(['status' => 'suspended'])->save();

        $this->getJson(route('admin.orders.index'))->assertForbidden();
        $this->assertGuest();
    }

    public function test_the_app_token_stops_working_once_suspended_and_everything_is_signed_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mhub-app')->plainTextToken;
        DeviceToken::create(['user_id' => $user->id, 'token' => 'phone-1', 'platform' => 'android']);
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->withToken($token)->getJson('/api/user')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DeviceToken::where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_a_suspended_token_is_refused_even_if_it_was_not_deleted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mhub-app')->plainTextToken;
        // Suspended without the model event (e.g. a direct database edit).
        User::whereKey($user->id)->update(['status' => 'suspended']);

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_dashboard_hides_money_and_customer_lists_from_admins_without_those_permissions(): void
    {
        $this->actingAs($this->admin(['tasks.manage']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Users')
            ->assertDontSee('Monthly Revenue')
            ->assertDontSee('Revenue (last 6 months)')
            ->assertDontSee('Pending Payments')
            ->assertDontSee('Recent Orders')
            ->assertDontSee('Recent Payments');

        $this->actingAs($this->admin(['orders.view']))
            ->get(route('admin.dashboard'))
            ->assertSee('Recent Orders')
            ->assertDontSee('Recent Payments')
            ->assertDontSee('Monthly Revenue');

        $this->actingAs($this->admin(['payments.view', 'reports.view']))
            ->get(route('admin.dashboard'))
            ->assertSee('Recent Payments')
            ->assertSee('Pending Payments')
            ->assertSee('Monthly Revenue')
            ->assertSee('Revenue (last 6 months)')
            ->assertDontSee('Recent Orders');
    }

    public function test_the_app_dashboard_follows_the_same_rule(): void
    {
        Sanctum::actingAs($this->admin(['tasks.manage']));
        $labels = collect($this->getJson('/api/admin/dashboard')->assertOk()->json('stats'))->pluck('label');
        $this->assertNotContains('Revenue this month', $labels);
        $this->assertNotContains('Pending payments', $labels);
        $this->assertContains('Users', $labels);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]));
        $labels = collect($this->getJson('/api/admin/dashboard')->assertOk()->json('stats'))->pluck('label');
        $this->assertContains('Revenue this month', $labels);
        $this->assertContains('Pending payments', $labels);
    }
}
