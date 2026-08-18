<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_receives_403(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/admin');

        $response->assertForbidden();
    }

    public function test_admin_user_can_access_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin');

        $response->assertOk();
    }

    public function test_non_admin_cannot_access_admin_orders(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get('/admin/orders');

        $response->assertForbidden();
    }
}
