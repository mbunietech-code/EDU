<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ErrorLog;
use App\Models\OptimizationRecommendation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OptimizationAgentTest extends TestCase
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

    private function restrictedAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'role' => Permissions::ROLE_ADMIN,
            'permissions' => ['orders.view'],
        ]);
    }

    public function test_restricted_admin_cannot_reach_optimization_page(): void
    {
        $this->actingAs($this->restrictedAdmin());

        $this->get(route('admin.optimization.index'))->assertForbidden();
        $this->post(route('admin.optimization.scan'))->assertForbidden();
    }

    public function test_super_admin_can_view_the_dashboard_and_run_a_scan(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get(route('admin.optimization.index'))->assertOk();

        $response = $this->post(route('admin.optimization.scan'));
        $response->assertRedirect();

        $this->assertDatabaseCount('optimization_scans', 1);
    }

    public function test_scan_flags_a_long_expired_account_for_archival(): void
    {
        $this->actingAs($this->superAdmin());

        Account::factory()->create([
            'status' => 'expired',
            'updated_at' => Carbon::now()->subDays(60),
        ]);

        $this->post(route('admin.optimization.scan'));

        $rec = OptimizationRecommendation::where('table_name', 'accounts')->where('category', 'expired')->first();
        $this->assertNotNull($rec);
        $this->assertSame('update', $rec->operation);
        $this->assertSame(1, $rec->affected_count);
        $this->assertSame('pending', $rec->status);
    }

    public function test_scan_flags_resolved_old_error_logs_for_deletion(): void
    {
        $this->actingAs($this->superAdmin());

        ErrorLog::create([
            'fingerprint' => str_repeat('a', 64),
            'level' => 'error',
            'exception' => 'RuntimeException',
            'message' => 'boom',
            'occurrences' => 1,
            'resolved_at' => Carbon::now()->subDays(120),
        ]);

        $this->post(route('admin.optimization.scan'));

        $rec = OptimizationRecommendation::where('table_name', 'error_logs')->first();
        $this->assertNotNull($rec);
        $this->assertSame('delete', $rec->operation);
        $this->assertSame('low', $rec->risk_level);
    }

    public function test_pending_recommendation_cannot_be_executed_without_approval(): void
    {
        $this->actingAs($this->superAdmin());

        $log = ErrorLog::create([
            'fingerprint' => str_repeat('b', 64),
            'level' => 'error',
            'exception' => 'RuntimeException',
            'message' => 'boom',
            'occurrences' => 1,
            'resolved_at' => Carbon::now()->subDays(120),
        ]);
        $this->post(route('admin.optimization.scan'));

        $rec = OptimizationRecommendation::where('table_name', 'error_logs')->first();

        $this->post(route('admin.optimization.execute', $rec))->assertStatus(400);

        $this->assertDatabaseHas('error_logs', ['id' => $log->id]);
    }

    public function test_approve_then_execute_backs_up_and_deletes_only_matching_rows(): void
    {
        Storage::fake('local');
        $this->actingAs($this->superAdmin());

        $old = ErrorLog::create([
            'fingerprint' => str_repeat('c', 64),
            'level' => 'error',
            'exception' => 'RuntimeException',
            'message' => 'old resolved error',
            'occurrences' => 1,
            'resolved_at' => Carbon::now()->subDays(120),
        ]);
        $recent = ErrorLog::create([
            'fingerprint' => str_repeat('d', 64),
            'level' => 'error',
            'exception' => 'RuntimeException',
            'message' => 'recently resolved error',
            'occurrences' => 1,
            'resolved_at' => Carbon::now()->subDays(2),
        ]);

        $this->post(route('admin.optimization.scan'));
        $rec = OptimizationRecommendation::where('table_name', 'error_logs')->first();

        $this->post(route('admin.optimization.approve', $rec))->assertRedirect();
        $rec->refresh();
        $this->assertSame('approved', $rec->status);

        $this->post(route('admin.optimization.execute', $rec))->assertRedirect();
        $rec->refresh();

        $this->assertSame('executed', $rec->status);
        $this->assertSame(1, $rec->executed_count);
        $this->assertNotNull($rec->backup_path);
        Storage::disk('local')->assertExists($rec->backup_path);

        // Only the old, resolved-120-days-ago row was deleted.
        $this->assertDatabaseMissing('error_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('error_logs', ['id' => $recent->id]);
    }

    public function test_reject_records_reason_and_prevents_execution(): void
    {
        $this->actingAs($this->superAdmin());

        ErrorLog::create([
            'fingerprint' => str_repeat('e', 64),
            'level' => 'error',
            'exception' => 'RuntimeException',
            'message' => 'boom',
            'occurrences' => 1,
            'resolved_at' => Carbon::now()->subDays(120),
        ]);
        $this->post(route('admin.optimization.scan'));
        $rec = OptimizationRecommendation::where('table_name', 'error_logs')->first();

        $this->post(route('admin.optimization.reject', $rec), ['rejection_reason' => 'Keep for compliance review'])
            ->assertRedirect();

        $rec->refresh();
        $this->assertSame('rejected', $rec->status);
        $this->assertSame('Keep for compliance review', $rec->rejection_reason);

        $this->post(route('admin.optimization.execute', $rec))->assertStatus(400);
    }

    public function test_create_index_recommendation_executes_directly_and_is_idempotent(): void
    {
        // InnoDB auto-creates a covering index whenever a foreign key is added,
        // so a real "FK without an index" scenario can't be engineered through
        // normal schema operations here. Instead, exercise the create_index
        // execute path directly against a genuinely unindexed column.
        $this->actingAs($this->superAdmin());

        $scan = \App\Models\OptimizationScan::create([]);
        $rec = OptimizationRecommendation::create([
            'scan_id' => $scan->id,
            'category' => 'missing_index',
            'operation' => 'create_index',
            'table_name' => 'contact_messages',
            'column_name' => 'subject',
            'affected_count' => 0,
            'action' => 'Add a covering index for a frequently filtered column.',
            'risk_level' => 'low',
            'index_sql' => 'CREATE INDEX idx_contact_messages_subject_test ON contact_messages (subject);',
            'sql_preview' => 'CREATE INDEX idx_contact_messages_subject_test ON contact_messages (subject);',
            'rollback_note' => 'Additive only. Rollback: DROP INDEX idx_contact_messages_subject_test ON contact_messages;',
            'status' => 'pending',
        ]);

        $this->post(route('admin.optimization.approve', $rec))->assertRedirect();
        $this->post(route('admin.optimization.execute', $rec))->assertRedirect();

        $rec->refresh();
        $this->assertSame('executed', $rec->status);
        $this->assertNotEmpty(DB::select("SHOW INDEX FROM contact_messages WHERE Key_name = 'idx_contact_messages_subject_test'"));

        // Re-running the same index creation (e.g. a stale "approved" state
        // re-executed) must not blow up — MySQL's "Duplicate key name" is
        // treated as already-applied, matching SchemaAlterService's convention.
        $rec->update(['status' => 'approved']);
        $this->post(route('admin.optimization.execute', $rec))->assertRedirect();
    }
}
