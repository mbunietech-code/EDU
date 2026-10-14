<?php

namespace Tests\Feature;

use App\Models\CompanyAsset;
use App\Models\User;
use App\Notifications\Admin\AssetExpiring;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Company assets: what Mbunie pays for, when it ends, where to renew it, and the reminders. */
class CompanyAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function asset(array $attrs = []): CompanyAsset
    {
        return CompanyAsset::create($attrs + [
            'name' => 'mbuniehub.com domain',
            'category' => 'domain',
            'vendor' => 'Namecheap',
            'purchased_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addDays(60)->toDateString(),
            'billing_cycle' => 'yearly',
            'cost' => 30000,
            'currency' => 'TZS',
        ]);
    }

    public function test_viewing_and_managing_need_their_permissions(): void
    {
        $this->asset();

        $this->actingAs($this->admin(['orders.view']))->get(route('admin.assets.index'))->assertForbidden();

        $viewer = $this->admin(['assets.view']);
        $this->actingAs($viewer)->get(route('admin.assets.index'))->assertOk()->assertSee('mbuniehub.com domain')->assertDontSee('+ Add asset');
        $this->actingAs($viewer)->get(route('admin.assets.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.assets.store'), ['name' => 'x', 'category' => 'other', 'billing_cycle' => 'yearly', 'currency' => 'TZS'])->assertForbidden();

        $this->actingAs($this->superAdmin())->get(route('admin.assets.index'))->assertOk()->assertSee('+ Add asset');
    }

    public function test_an_asset_is_added_edited_and_deleted(): void
    {
        $admin = $this->admin(['assets.view', 'assets.manage']);
        $owner = $this->admin([]);
        $owner->forceFill(['job_title' => 'Developer'])->save();

        $this->actingAs($admin)->post(route('admin.assets.store'), [
            'name' => 'Hostinger Business hosting',
            'category' => 'hosting',
            'vendor' => 'Hostinger',
            'purchase_url' => 'https://hpanel.hostinger.com',
            'account' => 'admin@mbuniehub.com',
            'purchased_at' => '2026-01-10',
            'expires_at' => '2027-01-10',
            'billing_cycle' => 'yearly',
            'cost' => '250000',
            'currency' => 'tzs',
            'auto_renew' => '1',
            'responsible_id' => $owner->id,
        ])->assertRedirect(route('admin.assets.index'));

        $asset = CompanyAsset::firstOrFail();
        $this->assertSame(['TZS', true, $owner->id], [$asset->currency, $asset->auto_renew, $asset->responsible_id]);

        $this->actingAs($admin)->get(route('admin.assets.index'))->assertOk()
            ->assertSee('Hostinger Business hosting')->assertSee('Developer')->assertSee('TZS 250,000.00');

        $this->actingAs($admin)->put(route('admin.assets.update', $asset), [
            'name' => 'Hostinger hosting', 'category' => 'hosting', 'billing_cycle' => 'yearly', 'currency' => 'TZS',
        ])->assertRedirect();
        $this->assertSame('Hostinger hosting', $asset->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.assets.destroy', $asset))->assertRedirect();
        $this->assertSoftDeleted($asset);
    }

    public function test_input_is_checked_and_only_web_links_are_allowed(): void
    {
        $admin = $this->superAdmin();
        $base = ['name' => 'x', 'category' => 'other', 'billing_cycle' => 'yearly', 'currency' => 'TZS'];

        $this->actingAs($admin)->post(route('admin.assets.store'), $base + ['purchase_url' => 'javascript:alert(1)'])->assertSessionHasErrors('purchase_url');
        $this->actingAs($admin)->post(route('admin.assets.store'), $base + ['purchased_at' => '2026-05-01', 'expires_at' => '2026-04-01'])->assertSessionHasErrors('expires_at');
        $this->actingAs($admin)->post(route('admin.assets.store'), array_merge($base, ['category' => 'spaceship']))->assertSessionHasErrors('category');
        $this->actingAs($admin)->post(route('admin.assets.store'), $base + ['responsible_id' => User::factory()->create()->id])->assertSessionHasErrors('responsible_id');

        $this->assertSame(0, CompanyAsset::count());
    }

    public function test_renewing_moves_the_end_date_one_cycle_on(): void
    {
        $admin = $this->superAdmin();
        $asset = $this->asset(['expires_at' => now()->addDays(5)->toDateString(), 'billing_cycle' => 'monthly']);
        $asset->forceFill(['reminded_days' => 7])->save();

        $this->actingAs($admin)->post(route('admin.assets.renew', $asset))->assertRedirect();

        $asset->refresh();
        $this->assertSame(now()->addDays(5)->addMonthNoOverflow()->toDateString(), $asset->expires_at->toDateString());
        $this->assertSame(now()->toDateString(), $asset->purchased_at->toDateString());
        $this->assertNull($asset->reminded_days);

        $once = $this->asset(['billing_cycle' => 'one_time']);
        $this->actingAs($admin)->post(route('admin.assets.renew', $once))->assertSessionHas('error');
    }

    public function test_filters_show_what_needs_attention(): void
    {
        $this->asset(['name' => 'Ended licence', 'expires_at' => now()->subDays(3)->toDateString()]);
        $this->asset(['name' => 'Domain ending soon', 'expires_at' => now()->addDays(10)->toDateString()]);
        $this->asset(['name' => 'Laptop', 'category' => 'device', 'billing_cycle' => 'one_time', 'expires_at' => null]);

        $page = $this->actingAs($this->superAdmin())->get(route('admin.assets.index', ['status' => 'attention']))->assertOk();
        $page->assertSee('Ended licence')->assertSee('Domain ending soon')->assertDontSee('Laptop');
        $page->assertSee('Ended 3 days ago')->assertSee('Ends in 10 days');
    }

    public function test_reminders_go_out_once_per_step_to_managers_and_the_person_in_charge(): void
    {
        Notification::fake();
        $boss = $this->superAdmin();
        $owner = $this->admin([]);
        $bystander = $this->admin(['orders.view']);
        $asset = $this->asset(['expires_at' => now()->addDays(6)->toDateString(), 'responsible_id' => $owner->id]);
        $this->asset(['name' => 'Far away', 'expires_at' => now()->addDays(200)->toDateString()]);

        $this->artisan('assets:remind')->assertSuccessful();
        Notification::assertSentTo([$boss, $owner], AssetExpiring::class, fn ($n) => $n->asset->is($asset) && $n->daysLeft === 6);
        Notification::assertNotSentTo($bystander, AssetExpiring::class);
        $this->assertSame(7, $asset->fresh()->reminded_days);

        // Same step again: nothing new.
        $this->artisan('assets:remind')->assertSuccessful();
        Notification::assertSentToTimes($boss, AssetExpiring::class, 1);

        // Next step (1 day left): reminded again.
        $this->travel(5)->days();
        $this->artisan('assets:remind')->assertSuccessful();
        Notification::assertSentToTimes($boss, AssetExpiring::class, 2);
    }

    public function test_the_sidebar_shows_the_company_section(): void
    {
        $this->asset(['expires_at' => now()->addDays(2)->toDateString()]);

        $this->actingAs($this->superAdmin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('admin.assets.index'), false)
            ->assertSee(route('admin.tasks.index'), false);
    }
}
