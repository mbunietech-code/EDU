<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSubscriptionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiring_soon_filter_works_without_the_daily_job_having_run(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]));

        // Still "active" (job never flipped it) but expires in 2 days.
        $soon = Subscription::factory()->create(['status' => 'active', 'expiry_date' => now()->addDays(2)]);
        // Active but far away, and one already expired — must not appear.
        $far = Subscription::factory()->create(['status' => 'active', 'expiry_date' => now()->addDays(30)]);
        $past = Subscription::factory()->create(['status' => 'active', 'expiry_date' => now()->subDays(2)]);

        $response = $this->get(route('admin.subscriptions.index', ['status' => 'expiring_soon']))->assertOk();

        $ids = $response->viewData('subscriptions')->pluck('id')->all();
        $this->assertContains($soon->id, $ids);
        $this->assertNotContains($far->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }
}
