<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OptimizationRecommendation;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs an approved database optimization recommendation: a scoped backup of
 * exactly the rows it changes first, then the delete / update / index.
 * Shared by the web Optimization page and the app.
 */
class OptimizationExecutor
{
    public function __construct(private DatabaseBackupService $backup)
    {
    }

    /**
     * @return array{count:int,backup_path:?string}
     *
     * @throws Throwable when the change fails (nothing beyond the backup was done)
     */
    public function execute(OptimizationRecommendation $recommendation, ?int $actorId): array
    {
        abort_unless($recommendation->isExecutable(), 400, 'This recommendation is not approved, or its finding is detection-only and requires manual action.');

        try {
            $result = match ($recommendation->operation) {
                'delete' => $this->delete($recommendation),
                'update' => $this->update($recommendation),
                'create_index' => $this->createIndex($recommendation),
                default => ['count' => 0, 'backup_path' => null],
            };
        } catch (Throwable $e) {
            ActivityLog::log('optimization_recommendation_execute_failed', 'OptimizationRecommendation', $recommendation->id, [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $recommendation->update([
            'status' => 'executed',
            'executed_by' => $actorId,
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

        return $result;
    }

    /** @return array{count:int,backup_path:?string} */
    private function delete(OptimizationRecommendation $recommendation): array
    {
        $whereSql = (string) $recommendation->where_sql;
        $bindings = $recommendation->where_bindings ?? [];

        $backupPath = $this->backup->backupRows($recommendation->table_name, $whereSql, $bindings, 'rec'.$recommendation->id);

        $count = DB::transaction(fn () => DB::table($recommendation->table_name)->whereRaw($whereSql, $bindings)->delete());

        return ['count' => $count, 'backup_path' => $backupPath];
    }

    /** @return array{count:int,backup_path:?string} */
    private function update(OptimizationRecommendation $recommendation): array
    {
        $whereSql = (string) $recommendation->where_sql;
        $bindings = $recommendation->where_bindings ?? [];
        $values = $recommendation->update_values ?? [];

        abort_if($values === [], 400, 'No update values recorded for this recommendation.');

        $backupPath = $this->backup->backupRows($recommendation->table_name, $whereSql, $bindings, 'rec'.$recommendation->id);

        $count = DB::transaction(fn () => DB::table($recommendation->table_name)->whereRaw($whereSql, $bindings)->update($values));

        return ['count' => $count, 'backup_path' => $backupPath];
    }

    /** @return array{count:int,backup_path:?string} */
    private function createIndex(OptimizationRecommendation $recommendation): array
    {
        try {
            DB::statement((string) $recommendation->index_sql);
        } catch (Throwable $e) {
            // Index already exists: already applied (same rule as SchemaAlterService).
            if (! str_contains($e->getMessage(), 'Duplicate key name')) {
                throw $e;
            }
        }

        return ['count' => 0, 'backup_path' => null];
    }
}
