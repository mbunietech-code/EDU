<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\MvpnClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Managing the VPN from the EduHub admin, with the VPN partner API faked. */
class VpnManageTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-partner-secret-0123456789abcdef0123';

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mvpn.url' => 'https://vpn.test',
            'services.mvpn.partner_secret' => self::SECRET,
        ]);
        $this->super = User::factory()->create(['is_admin' => true, 'role' => 'super_admin', 'email' => 'boss@mbuniehub.com', 'email_verified_at' => now()]);
    }

    private function signedOk(Request $r): bool
    {
        $uri = substr($r->url(), strlen('https://vpn.test'));

        return $r->header('X-Mvpn-Signature')[0] === MvpnClient::sign(
            self::SECRET, (int) $r->header('X-Mvpn-Timestamp')[0], $r->method(), $uri, $r->body());
    }

    private function customer(): array
    {
        return [
            'customer' => ['id' => 5, 'name' => 'Gao', 'email' => 'gao@example.com', 'phone' => null, 'status' => 'active',
                'eduhub_user_id' => null, 'created_at' => now()->toIso8601String(),
                'subscription' => ['id' => 9, 'plan' => 'm1', 'status' => 'active', 'expires_at' => now()->addDays(5)->toIso8601String(),
                    'started_at' => null, 'max_devices' => 2, 'devices' => 1, 'online' => true]],
            'subscriptions' => [['id' => 9, 'plan' => 'm1', 'status' => 'active', 'expires_at' => now()->addDays(5)->toIso8601String(),
                'started_at' => null, 'max_devices' => 2, 'devices' => 1, 'online' => true]],
            'devices' => [['id' => 14, 'customer' => ['id' => 5, 'email' => 'gao@example.com', 'phone' => null], 'platform' => 'android',
                'model' => 'samsung SM-G9960', 'os_version' => 'Android 15', 'app_version' => '1.0.12', 'protocol' => 'auto',
                'state' => 'online', 'connected_at' => now()->toIso8601String(), 'last_seen_at' => now()->toIso8601String(),
                'revoked_at' => null, 'last_error' => null, 'last_error_at' => null]],
            'invoices' => [],
        ];
    }

    public function test_pages_render_from_the_vpn_api(): void
    {
        $c = $this->customer();
        Http::fake([
            'vpn.test/api/partner/admin/customers/5' => Http::response($c),
            'vpn.test/api/partner/admin/customers*' => Http::response(['data' => [$c['customer']], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 1]]),
            'vpn.test/api/partner/admin/devices*' => Http::response(['data' => $c['devices'], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 1]]),
            'vpn.test/api/partner/admin/invoices*' => Http::response(['data' => [[
                'id' => 31, 'customer' => ['id' => 5, 'email' => 'gao@example.com', 'phone' => null], 'plan' => 'm1',
                'amount_cents' => 5500, 'currency' => 'cny', 'provider' => 'manual', 'method' => 'alipay', 'status' => 'pending_review',
                'has_proof' => true, 'user_note' => null, 'review_note' => null, 'reviewed_by' => null, 'paid_at' => null,
                'created_at' => now()->toIso8601String()]], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 1]]),
            'vpn.test/api/partner/admin/plans' => Http::response(['data' => [[
                'id' => 1, 'code' => 'm1', 'name' => 'Monthly', 'days' => 30, 'max_devices' => 2,
                'price_usd' => 8, 'price_cny' => 55, 'price_tzs' => 20000, 'is_active' => true]]]),
        ]);

        $this->actingAs($this->super);
        $this->get(route('admin.vpn.customers', ['q' => 'gao']))->assertOk()->assertSee('gao@example.com');
        $this->get(route('admin.vpn.customers.show', 5))->assertOk()->assertSee('samsung SM-G9960')->assertSee('Add days')->assertSee('Remove');
        $this->get(route('admin.vpn.devices', ['filter' => 'online']))->assertOk()->assertSee('samsung SM-G9960');
        $this->get(route('admin.vpn.payments'))->assertOk()->assertSee('CNY 55.00')->assertSee('Approve');
        $this->get(route('admin.vpn.plans'))->assertOk()->assertSee('Monthly')->assertSee('Convert prices automatically')->assertSee('data-plan-prices', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'customers?q=gao') && $this->signedOk($r));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'devices?filter=online') && $this->signedOk($r));
    }

    public function test_actions_are_signed_name_the_admin_and_are_logged(): void
    {
        Http::fake(['vpn.test/*' => Http::response(['ok' => true])]);
        $this->actingAs($this->super);

        $this->post(route('admin.vpn.subscriptions.extend', 9), ['days' => 7])->assertRedirect()->assertSessionHas('success');
        $this->post(route('admin.vpn.devices.remove', 14), ['reason' => 'not mine'])->assertSessionHas('success');
        $this->post(route('admin.vpn.payments.approve', 31))->assertSessionHas('success');
        $this->put(route('admin.vpn.plans.update', 1), [
            'name' => 'Monthly', 'days' => 30, 'max_devices' => 2, 'price_usd' => 8, 'price_cny' => 55, 'price_tzs' => 20000, 'is_active' => '1',
        ])->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/api/partner/admin/subscriptions/9/extend')
            && $r['days'] === 7 && $r['actor'] === 'boss@mbuniehub.com' && $this->signedOk($r));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['is_active'] === true && $r['price_cny'] == 55);
        $this->assertSame(4, ActivityLog::where('entity', 'Vpn')->count());
    }

    public function test_reject_needs_a_reason_and_vpn_errors_are_shown(): void
    {
        Http::fake(['vpn.test/*' => Http::response(['message' => 'Only pending payments can be rejected.'], 422)]);
        $this->actingAs($this->super);

        $this->post(route('admin.vpn.payments.reject', 31), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.vpn.payments.reject', 31), ['reason' => 'blurry'])
            ->assertSessionHas('error', 'VPN API error: Only pending payments can be rejected.');
    }

    public function test_menu_badge_counts_payments_to_review(): void
    {
        Http::fake(['vpn.test/api/partner/admin/invoices*' => Http::response(['data' => [], 'meta' => ['page' => 1, 'last_page' => 1, 'total' => 3]])]);

        $this->assertSame(3, app(MvpnClient::class)->paymentsToReview());
    }

    public function test_menu_badge_hides_and_caches_a_vpn_outage(): void
    {
        Http::fake(['vpn.test/*' => Http::response([], 500)]);

        $this->assertNull(app(MvpnClient::class)->paymentsToReview());
        $this->assertNull(app(MvpnClient::class)->paymentsToReview());
        Http::assertSentCount(1); // the failure is cached, so pages stay fast
    }

    public function test_only_super_admins_get_in(): void
    {
        Http::fake();
        $plainAdmin = User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => ['payments.view'], 'email_verified_at' => now()]);

        $this->actingAs($plainAdmin)->get(route('admin.vpn.customers'))->assertForbidden();
        $this->actingAs($plainAdmin)->post(route('admin.vpn.subscriptions.extend', 9), ['days' => 7])->assertForbidden();
        Http::assertNothingSent();
    }
}
