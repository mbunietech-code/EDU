<?php

namespace Tests\Feature;

use App\Models\OptimizationScan;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdminAlertService;
use App\Services\CredentialService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAlertsTest extends TestCase
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

    private function configureBeem(): void
    {
        Setting::set('beem_api_key', 'key123', 'string', 'alerts');
        Setting::set('beem_secret_key', app(CredentialService::class)->encrypt('secret456'), 'encrypted', 'alerts');
        Setting::set('beem_sender_id', 'MBUNIE', 'string', 'alerts');
        Setting::set('alert_phones', '0712345678', 'string', 'alerts');
    }

    public function test_restricted_admin_cannot_reach_alert_settings(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view'],
        ]));

        $this->get(route('admin.alerts.index'))->assertForbidden();
        $this->post(route('admin.alerts.test'))->assertForbidden();
    }

    public function test_settings_save_and_secret_is_stored_encrypted(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.alerts.index'))->assertOk();

        $this->put(route('admin.alerts.update'), [
            'alert_emails' => 'a@example.com',
            'alert_phones' => '0712345678',
            'beem_api_key' => 'key123',
            'beem_secret_key' => 'secret456',
            'beem_sender_id' => 'MBUNIE',
            'alert_expired_min' => 3,
            'alert_expiring_min' => 2,
            'alert_storage_mb' => 100,
            'alert_errors_min' => 4,
        ])->assertRedirect();

        $stored = Setting::get('beem_secret_key');
        $this->assertNotSame('secret456', $stored);
        $this->assertSame('secret456', app(CredentialService::class)->decryptValue($stored));
        $this->assertSame('3', (string) Setting::get('alert_expired_min'));
    }

    public function test_blank_secret_keeps_the_saved_one(): void
    {
        $this->actingAs($this->superAdmin());
        $this->configureBeem();
        $before = Setting::get('beem_secret_key');

        $this->put(route('admin.alerts.update'), [
            'beem_api_key' => 'key123',
            'beem_secret_key' => '',
            'beem_sender_id' => 'MBUNIE',
            'alert_expired_min' => 10,
            'alert_expiring_min' => 5,
            'alert_storage_mb' => 500,
            'alert_errors_min' => 10,
        ]);

        $this->assertSame($before, Setting::get('beem_secret_key'));
    }

    public function test_scan_over_threshold_sends_email_and_sms_once_per_day(): void
    {
        Mail::fake();
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'request_id' => 1])]);
        $this->configureBeem();
        $this->superAdmin();

        $scan = OptimizationScan::create(['expired_count' => 12, 'expiring_soon_count' => 0, 'total_size_mb' => 1]);
        $service = app(AdminAlertService::class);

        $first = $service->notifyFor($scan);
        $this->assertTrue($first['sent']);
        $this->assertTrue($first['email']);
        $this->assertTrue($first['sms']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'apisms.beem.africa')
            && $r['recipients'][0]['dest_addr'] === '255712345678'
            && $r['source_addr'] === 'MBUNIE');

        // Same alert again within 24h is suppressed.
        $second = $service->notifyFor($scan);
        $this->assertFalse($second['sent']);
        Http::assertSentCount(1);
    }

    public function test_scan_under_thresholds_sends_nothing(): void
    {
        Mail::fake();
        Http::fake();
        $this->configureBeem();

        $scan = OptimizationScan::create(['expired_count' => 1, 'expiring_soon_count' => 1, 'total_size_mb' => 1]);
        $result = app(AdminAlertService::class)->notifyFor($scan);

        $this->assertSame([], $result['alerts']);
        $this->assertFalse($result['sent']);
        Http::assertNothingSent();
    }

    public function test_sms_failure_does_not_block_email(): void
    {
        Mail::fake();
        Http::fake(['apisms.beem.africa/*' => Http::response(['error' => 'bad'], 401)]);
        $this->configureBeem();
        $this->superAdmin();

        $scan = OptimizationScan::create(['expired_count' => 50]);
        $result = app(AdminAlertService::class)->notifyFor($scan);

        $this->assertTrue($result['email']);
        $this->assertFalse($result['sms']);
        $this->assertNotEmpty($result['errors']);
        $this->assertTrue($result['sent']);
    }

    public function test_test_button_reports_when_nothing_is_configured(): void
    {
        Mail::fake();
        $this->actingAs($this->superAdmin());
        // no emails configured -> falls back to super admins, so email channel exists; ensure it reports success
        $this->post(route('admin.alerts.test'))->assertSessionHas('success');
    }
}
