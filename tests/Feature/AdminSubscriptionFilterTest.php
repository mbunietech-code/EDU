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

    private function ids(array $query): array
    {
        return $this->get(route('admin.subscriptions.index', $query))->assertOk()
            ->viewData('subscriptions')->pluck('id')->all();
    }

    public function test_clicking_any_value_filters_the_list(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_SUPER_ADMIN,
            'permissions' => null,
        ]));

        $a = Subscription::factory()->create(['expiry_date' => now()->addDays(5)->startOfDay()]);
        $a->order->update(['device' => 'iPhone 13']);
        $b = Subscription::factory()->create(['expiry_date' => now()->addDays(60), 'status' => 'suspended']);
        $b->order->update(['device' => null]);
        $c = Subscription::factory()->create(['expiry_date' => now()->subDays(3), 'status' => 'expired', 'account_id' => null]);

        // Values in the table are links that add their filter.
        $this->get(route('admin.subscriptions.index'))->assertOk()
            ->assertSee(route('admin.subscriptions.index', ['user' => $a->user_id]), false)
            ->assertSee('device=iPhone%2013', false)
            ->assertSee('account='.$a->account_id, false)
            ->assertSee('expires='.$a->expiry_date->toDateString(), false);

        $this->assertSame([$a->id], $this->ids(['user' => $a->user_id]));
        $this->assertSame([$b->id], $this->ids(['product' => $b->product_id]));
        $this->assertSame([$b->id], $this->ids(['product' => $b->product_id, 'plan' => $b->plan_id]));
        $this->assertSame([$a->id], $this->ids(['device' => 'iPhone 13']));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], $this->ids(['device' => 'none']));
        $this->assertSame([$a->id], $this->ids(['account' => $a->account_id]));
        $this->assertSame([$c->id], $this->ids(['account' => 'none']));
        $this->assertSame([$a->id], $this->ids(['expires' => $a->expiry_date->toDateString()]));
        $this->assertSame([$a->id], $this->ids(['expires' => '7d']));
        $this->assertSame([$c->id], $this->ids(['expires' => 'past']));
        $this->assertSame([$b->id], $this->ids(['expires' => 'later']));
        $this->assertSame([$b->id], $this->ids(['status' => 'suspended']));

        // Filters combine, and the active ones show as removable chips.
        $this->assertSame([], $this->ids(['user' => $a->user_id, 'status' => 'suspended']));
        $this->get(route('admin.subscriptions.index', ['device' => 'iPhone 13', 'expires' => '7d']))
            ->assertSee('Device: iPhone 13')->assertSee('Expires: Next 7 days')->assertSee('Clear all');

        // Expires header sorts by end date.
        $this->assertSame([$c->id, $a->id, $b->id], $this->ids(['sort' => 'expires_asc']));
        $this->assertSame([$b->id, $a->id, $c->id], $this->ids(['sort' => 'expires_desc']));

        // Junk values are ignored rather than breaking the page.
        $this->assertCount(3, $this->ids(['user' => 'x', 'account' => '1 or 1', 'expires' => '2026-99-99x', 'sort' => 'nope']));
    }
}
