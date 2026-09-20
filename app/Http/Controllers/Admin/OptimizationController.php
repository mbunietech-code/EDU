<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OptimizationRecommendation;
use App\Models\OptimizationScan;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only database analysis + a reviewable, backup-before-execute cleanup
 * workflow. Nothing here ever touches data without an explicit approve step
 * (see OptimizationRecommendation::isExecutable()), and every destructive
 * action is preceded by a scoped backup of exactly the rows it will change.
 */
class OptimizationController extends Controller
{
    public function __construct(private DatabaseOptimizationService $optimizer)
    {
    }

    public function index()
    {
        $scan = OptimizationScan::with('recommendations')->latest('id')->first();
        $history = OptimizationScan::latest('id')->take(10)->get();

        return view('admin.optimization.index', [
            'scan' => $scan,
            'history' => $history,
            'supported' => $this->optimizer->isSupported(),
        ]);
    }

    public function scan()
    {
        $scan = $this->optimizer->scan(auth()->id());

        ActivityLog::log('optimization_scan_run', 'OptimizationScan', $scan->id, [
            'recommendations' => $scan->recommendations->count(),
        ]);

        return back()->with('success', 'Scan complete — '.$scan->recommendations->count().' recommendation(s) found.');
    }

    public function approve(OptimizationRecommendation $recommendation)
    {
        abort_unless($recommendation->status === 'pending', 400, 'Only pending recommendations can be approved.');

        $recommendation->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ActivityLog::log('optimization_recommendation_approved', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
            'category' => $recommendation->category,
        ]);

        return back()->with('success', 'Recommendation approved. You can now execute it.');
    }

    public function reject(Request $request, OptimizationRecommendation $recommendation)
    {
        abort_unless(in_array($recommendation->status, ['pending', 'approved'], true), 400, 'This recommendation cannot be rejected.');

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $recommendation->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        ActivityLog::log('optimization_recommendation_rejected', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
        ]);

        return back()->with('success', 'Recommendation rejected.');
    }

    public function execute(OptimizationRecommendation $recommendation, DatabaseBackupService $backup)
    {
        abort_unless($recommendation->isExecutable(), 400, 'This recommendation is not approved, or its finding is detection-only and requires manual action.');

        try {
            $result = match ($recommendation->operation) {
                'delete' => $this->executeDelete($recommendation, $backup),
                'update' => $this->executeUpdate($recommendation, $backup),
                'create_index' => $this->executeCreateIndex($recommendation),
                default => null,
            };
        } catch (Throwable $e) {
            ActivityLog::log('optimization_recommendation_execute_failed', 'OptimizationRecommendation', $recommendation->id, [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Execution failed: '.$e->getMessage().'. No changes were made beyond the backup step, if reached.');
        }

        $recommendation->update([
            'status' => 'executed',
            'executed_by' => auth()->id(),
            'executed_at' => now(),
            'executed_count' => $result['count'],
            'backup_path' => $result['backup_path'],
        ]);

        ActivityLog::log('optimization_recommendation_executed', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
            'operation' => $recommendation->operation,
            'affected' => $result['count'],
            'backup_path' => $result['backup_path'],
        ]);

        return back()->with('success', "Executed. {$result['count']} row(s) affected.".($result['backup_path'] ? ' Backup saved.' : ''));
    }

    /**
     * @return array{count:int,backup_path:?string}
     */
    private function executeDelete(OptimizationRecommendation $recommendation, DatabaseBackupService $backup): array
    {
        $whereSql = (string) $recommendation->where_sql;
        $bindings = $recommendation->where_bindings ?? [];

        $backupPath = $backup->backupRows($recommendation->table_name, $whereSql, $bindings, 'rec'.$recommendation->id);

        $count = DB::transaction(function () use ($recommendation, $whereSql, $bindings) {
            return DB::table($recommendation->table_name)->whereRaw($whereSql, $bindings)->delete();
        });

        return ['count' => $count, 'backup_path' => $backupPath];
    }

    /**
     * @return array{count:int,backup_path:?string}
     */
    private function executeUpdate(OptimizationRecommendation $recommendation, DatabaseBackupService $backup): array
    {
        $whereSql = (string) $recommendation->where_sql;
        $bindings = $recommendation->where_bindings ?? [];
        $values = $recommendation->update_values ?? [];

        abort_if($values === [], 400, 'No update values recorded for this recommendation.');

        $backupPath = $backup->backupRows($recommendation->table_name, $whereSql, $bindings, 'rec'.$recommendation->id);

        $count = DB::transaction(function () use ($recommendation, $whereSql, $bindings, $values) {
            return DB::table($recommendation->table_name)->whereRaw($whereSql, $bindings)->update($values);
        });

        return ['count' => $count, 'backup_path' => $backupPath];
    }

    /**
     * @return array{count:int,backup_path:?string}
     */
    private function executeCreateIndex(OptimizationRecommendation $recommendation): array
    {
        try {
            DB::statement((string) $recommendation->index_sql);
        } catch (Throwable $e) {
            if (! str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
            // Index already exists — treat as already applied, matching the
            // benign-error convention used by SchemaAlterService.
        }

        return ['count' => 0, 'backup_path' => null];
    }
}
