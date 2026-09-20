<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardRevenueMaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_a_revenue_hide_toggle_with_masked_placeholders(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('toggleRevenueMask()', false)
            ->assertSee('TZS ••••••')
            ->assertSee('class="rev-real', false);
    }
}
