<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A quick read-only health picture of the live database for the admin
 * Database page: size, table count, and anything that looks wrong.
 */
class DatabaseHealthService
{
    public function __construct(private SchemaAlterService $alters)
    {
    }

    /**
     * @return array{
     *   status:string, status_label:string,
     *   connection:bool, database:string,
     *   tables:int, rows:int, size_mb:float,
     *   checks:list<array{label:string,ok:bool,detail:string}>
     * }
     */
    public function report(): array
    {
        $connection = true;
        $database = (string) config('database.connections.'.config('database.default').'.database');

        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            return [
                'status' => 'problem',
                'status_label' => 'Cannot connect',
                'connection' => false,
                'database' => $database,
                'tables' => 0,
                'rows' => 0,
                'size_mb' => 0.0,
                'checks' => [
                    ['label' => 'Database connection', 'ok' => false, 'detail' => $e->getMessage()],
                ],
            ];
        }

        $stats = $this->tableStats($database);
        $checks = $this->checks($database, $stats['engines']);

        $failed = collect($checks)->where('ok', false)->count();
        $status = match (true) {
            ! $connection => 'problem',
            $failed === 0 => 'stable',
            $failed <= 1 => 'attention',
            default => 'problem',
        };

        return [
            'status' => $status,
            'status_label' => [
                'stable' => 'Stable',
                'attention' => 'Needs attention',
                'problem' => 'Problem detected',
            ][$status],
            'connection' => $connection,
            'database' => $database,
            'tables' => $stats['tables'],
            'rows' => $stats['rows'],
            'size_mb' => $stats['size_mb'],
            'checks' => $checks,
        ];
    }

    /**
     * @return array{tables:int,rows:int,size_mb:float,engines:array<string,int>}
     */
    private function tableStats(string $database): array
    {
        $rows = DB::select(
            'SELECT ENGINE as engine, TABLE_ROWS as table_rows, DATA_LENGTH as data_length, INDEX_LENGTH as index_length
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"',
            [$database],
        );

        $tables = count($rows);
        $totalRows = 0;
        $bytes = 0;
        $engines = [];

        foreach ($rows as $r) {
            $totalRows += (int) ($r->table_rows ?? 0);
            $bytes += (int) ($r->data_length ?? 0) + (int) ($r->index_length ?? 0);
            $engine = $r->engine ?: 'unknown';
            $engines[$engine] = ($engines[$engine] ?? 0) + 1;
        }

        return [
            'tables' => $tables,
            'rows' => $totalRows,
            'size_mb' => round($bytes / 1048576, 2),
            'engines' => $engines,
        ];
    }

    /**
     * @param  array<string,int>  $engines
     * @return list<array{label:string,ok:bool,detail:string}>
     */
    private function checks(string $database, array $engines): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'Database connection',
            'ok' => true,
            'detail' => 'Connected to '.$database,
        ];

        // Pending schema changes
        try {
            $pending = count($this->alters->pending());
        } catch (Throwable $e) {
            $pending = 0;
        }
        $checks[] = [
            'label' => 'Schema changes',
            'ok' => $pending === 0,
            'detail' => $pending === 0
                ? 'All applied'
                : "{$pending} pending — apply below",
        ];

        // Failed alters recorded
        try {
            $failedAlters = DB::table('schema_alters')->where('ok', false)->count();
        } catch (Throwable $e) {
            $failedAlters = 0;
        }
        if ($failedAlters > 0) {
            $checks[] = [
                'label' => 'Failed schema changes',
                'ok' => false,
                'detail' => "{$failedAlters} file(s) errored — see history",
            ];
        }

        // Storage engines (FK integrity needs InnoDB)
        $nonInno = collect($engines)->reject(fn ($count, $engine) => strtoupper((string) $engine) === 'INNODB')->sum();
        $checks[] = [
            'label' => 'Storage engine',
            'ok' => $nonInno === 0,
            'detail' => $nonInno === 0
                ? 'All tables InnoDB'
                : "{$nonInno} table(s) not InnoDB",
        ];

        // Failed queue jobs
        try {
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $failedJobs = DB::table('failed_jobs')->count();
                $checks[] = [
                    'label' => 'Failed background jobs',
                    'ok' => $failedJobs === 0,
                    'detail' => $failedJobs === 0 ? 'None' : "{$failedJobs} failed job(s)",
                ];
            }
        } catch (Throwable $e) {
            // ignore
        }

        return $checks;
    }
}
