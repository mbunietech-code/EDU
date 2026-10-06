<?php

namespace Tests\Feature\Api;

use App\Models\ContactMessage;
use App\Models\OptimizationRecommendation;
use App\Models\OptimizationScan;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    public function test_alert_settings_keep_the_secret_private(): void
    {
        Sanctum::actingAs($this->superAdmin());

        $this->putJson('/api/admin/alerts', [
            'alert_emails' => 'ops@example.com',
            'beem_api_key' => 'key',
            'beem_secret_key' => 'top-secret',
            'alert_expired_min' => 5, 'alert_expiring_min' => 10, 'alert_storage_mb' => 500, 'alert_errors_min' => 3,
        ])->assertOk();

        $data = $this->getJson('/api/admin/alerts')->assertOk()->json('data');
        $this->assertSame('ops@example.com', $data['alert_emails']);
        $this->assertTrue($data['has_beem_secret']);
        $this->assertSame(5, $data['thresholds']['alert_expired_min']);
        $this->assertStringNotContainsString('top-secret', json_encode($data));
        $this->assertNotSame('top-secret', Setting::get('beem_secret_key'));
    }

    public function test_optimization_recommendation_is_approved_then_run_with_a_backup(): void
    {
        Sanctum::actingAs($this->superAdmin());
        ContactMessage::create(['name' => 'a', 'email' => 'a@x.test', 'subject' => 'old', 'message' => 'm']);
        ContactMessage::create(['name' => 'b', 'email' => 'b@x.test', 'subject' => 'keep', 'message' => 'm']);

        $scan = OptimizationScan::create(['triggered_by' => null]);
        $rec = OptimizationRecommendation::create([
            'scan_id' => $scan->id, 'category' => 'expired', 'operation' => 'delete', 'table_name' => 'contact_messages',
            'affected_count' => 1, 'action' => 'Delete old messages', 'risk_level' => 'low',
            'where_sql' => 'subject = ?', 'where_bindings' => ['old'], 'status' => 'pending',
            'sql_preview' => "DELETE FROM contact_messages WHERE subject = 'old'", 'rollback_note' => 'Restore from the backup file.',
        ]);

        $this->getJson('/api/admin/optimization')->assertOk()->assertJsonPath('data.scan.recommendations.0.id', $rec->id);

        // Not approved yet: nothing runs.
        $this->postJson("/api/admin/optimization/{$rec->id}/execute")->assertStatus(400);
        $this->assertSame(2, ContactMessage::count());

        $this->postJson("/api/admin/optimization/{$rec->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');

        // The row backup uses MySQL (SHOW CREATE TABLE). When it fails, nothing is deleted.
        $this->postJson("/api/admin/optimization/{$rec->id}/execute")->assertStatus(422);
        $this->assertSame(2, ContactMessage::count());

        $this->mock(DatabaseBackupService::class, fn ($m) => $m->shouldReceive('backupRows')->once()
            ->with('contact_messages', 'subject = ?', ['old'], 'rec'.$rec->id)
            ->andReturn('optimization-backups/test.sql'));
        $this->postJson("/api/admin/optimization/{$rec->id}/execute")->assertOk()->assertJsonPath('data.status', 'executed');

        $this->assertSame(['keep'], ContactMessage::pluck('subject')->all());
        $this->assertNotNull($rec->fresh()->backup_path);
    }

    public function test_mail_settings_and_test_email(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->superAdmin());

        $this->putJson('/api/admin/settings/mail', [
            'mail_mailer' => 'log', 'mail_password' => 'smtp-pass',
            'mail_from_address' => 'no-reply@mbuniehub.com', 'mail_from_name' => 'MbunieEduHub',
        ])->assertOk();

        $this->getJson('/api/admin/settings/mail')->assertOk()
            ->assertJsonPath('data.mail_from_name', 'MbunieEduHub')
            ->assertJsonPath('data.has_password', true)
            ->assertJsonMissingPath('data.mail_password');

        $this->postJson('/api/admin/settings/mail/test')->assertOk();
    }

    public function test_finance_pin_change_needs_the_current_pin(): void
    {
        Sanctum::actingAs($this->superAdmin());
        Setting::set('finance_pin', '123456');

        $this->postJson('/api/admin/settings/finance-pin', ['current_pin' => '000000', 'pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertStatus(422)->assertJsonValidationErrors('current_pin');
        $this->postJson('/api/admin/settings/finance-pin', ['current_pin' => '123456', 'pin' => '12ab', 'pin_confirmation' => '12ab'])
            ->assertStatus(422)->assertJsonValidationErrors('pin');
        $this->postJson('/api/admin/settings/finance-pin', ['current_pin' => '123456', 'pin' => '654321', 'pin_confirmation' => '654321'])
            ->assertOk();

        $this->assertSame('654321', Setting::get('finance_pin'));
    }

    public function test_restricted_admins_are_kept_out(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['role' => 'admin', 'permissions' => ['settings.manage']]));

        $this->getJson('/api/admin/alerts')->assertForbidden();
        $this->getJson('/api/admin/optimization')->assertForbidden();
        $this->postJson('/api/admin/settings/finance-pin', ['pin' => '111111', 'pin_confirmation' => '111111'])->assertForbidden();
        $this->getJson('/api/admin/settings/mail')->assertOk();
    }
}
