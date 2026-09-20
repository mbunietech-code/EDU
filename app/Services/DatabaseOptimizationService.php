<?php

namespace App\Services;

use App\Models\OptimizationRecommendation;
use App\Models\OptimizationScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only rule-based analysis of the live database: finds expired,
 * expiring-soon, inactive/archivable, duplicate and orphaned data, plus
 * missing-index and (where available) slow-query signals — and turns each
 * finding into a reviewable recommendation. Never executes anything itself;
 * that only happens when an admin approves a recommendation explicitly
 * (see OptimizationController).
 */
class DatabaseOptimizationService
{
    /** @var array<string,array{rows:int,avg_bytes:float}> */
    private array $tableStatsCache = [];

    public function isSupported(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Run every rule, persist a scan + its recommendations, and return the
     * scan (with recommendations loaded).
     */
    public function scan(?int $userId = null): OptimizationScan
    {
        if (! $this->isSupported()) {
            return OptimizationScan::create([
                'triggered_by' => $userId,
                'notes' => ['error' => 'Optimization analysis requires a MySQL connection.'],
            ]);
        }

        $this->tableStatsCache = [];
        $database = DB::connection()->getDatabaseName();
        $overview = $this->databaseOverview($database);

        $findings = [
            ...$this->ruleExpiredAccounts(),
            ...$this->ruleExpiringSoonSubscriptions(),
            ...$this->ruleArchivableLogs(),
            ...$this->ruleSoftDeletedMessages(),
            ...$this->ruleDuplicates(),
            ...$this->ruleMissingIndexes($database),
            ...$this->ruleSlowQueries(),
            ...$this->ruleOrphanedRelationships(),
        ];

        $scan = OptimizationScan::create([
            'triggered_by' => $userId,
            'total_tables' => $overview['tables'],
            'total_rows' => $overview['rows'],
            'total_size_mb' => $overview['size_mb'],
            'expired_count' => $this->sumByCategory($findings, 'expired'),
            'expiring_soon_count' => $this->sumByCategory($findings, 'expiring_soon'),
            'inactive_count' => $this->sumByCategory($findings, 'inactive'),
            'duplicate_count' => $this->sumByCategory($findings, 'duplicate'),
            'missing_index_count' => count(array_filter($findings, fn ($f) => $f['category'] === 'missing_index')),
            'slow_query_count' => count(array_filter($findings, fn ($f) => $f['category'] === 'slow_query')),
            'orphaned_count' => $this->sumByCategory($findings, 'orphaned'),
            'estimated_recovery_mb' => round(array_sum(array_column($findings, 'estimated_recovery_mb')), 2),
            'notes' => $overview['notes'],
        ]);

        foreach ($findings as $finding) {
            $finding['scan_id'] = $scan->id;
            unset($finding['_meta']);
            OptimizationRecommendation::create($finding);
        }

        return $scan->load('recommendations');
    }

    /**
     * @param  list<array<string,mixed>>  $findings
     */
    private function sumByCategory(array $findings, string $category): int
    {
        return (int) array_sum(array_map(
            fn ($f) => $f['category'] === $category ? $f['affected_count'] : 0,
            $findings,
        ));
    }

    /**
     * @return array{tables:int,rows:int,size_mb:float,notes:array<string,mixed>}
     */
    private function databaseOverview(string $database): array
    {
        $rows = DB::select(
            'SELECT COUNT(*) as tables, SUM(TABLE_ROWS) as row_estimate, SUM(DATA_LENGTH + INDEX_LENGTH) as bytes
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE"',
            [$database],
        );
        $r = $rows[0] ?? null;

        return [
            'tables' => (int) ($r->tables ?? 0),
            'rows' => (int) ($r->row_estimate ?? 0),
            'size_mb' => round((float) ($r->bytes ?? 0) / 1048576, 2),
            'notes' => [],
        ];
    }

    /**
     * Average bytes-per-row for a table, from information_schema — used to
     * turn "N rows can be removed" into a storage-recovery estimate.
     */
    private function estimateMb(string $table, int $affectedRows): float
    {
        if ($affectedRows <= 0) {
            return 0.0;
        }

        if (! isset($this->tableStatsCache[$table])) {
            $database = DB::connection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT TABLE_ROWS as row_estimate, (DATA_LENGTH + INDEX_LENGTH) as bytes
                 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database, $table],
            );
            $tableRows = max((int) ($row->row_estimate ?? 0), 1);
            $this->tableStatsCache[$table] = [
                'rows' => $tableRows,
                'avg_bytes' => (float) ($row->bytes ?? 0) / $tableRows,
            ];
        }

        return round(($this->tableStatsCache[$table]['avg_bytes'] * $affectedRows) / 1048576, 2);
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    // ------------------------------------------------------------------
    // #1 Already-expired business data
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleExpiredAccounts(): array
    {
        $findings = [];
        $cutoff = Carbon::now()->subDays(30);

        $count = DB::table('accounts')
            ->where('status', 'expired')
            ->where('updated_at', '<', $cutoff)
            ->count();

        if ($count > 0) {
            $findings[] = [
                'category' => 'expired',
                'operation' => 'update',
                'table_name' => 'accounts',
                'column_name' => 'status',
                'affected_count' => $count,
                'expiration_status' => "expired more than 30 days ago",
                'action' => "Move {$count} long-expired account(s) from 'expired' to 'archived' so they stop showing as active inventory. Credentials stay in the database.",
                'risk_level' => 'medium',
                'estimated_recovery_mb' => 0.0,
                'where_sql' => "status = ? AND updated_at < ?",
                'where_bindings' => ['expired', $cutoff->toDateTimeString()],
                'update_values' => ['status' => 'archived'],
                'sql_preview' => "UPDATE accounts SET status = 'archived' WHERE status = 'expired' AND updated_at < '{$cutoff->toDateTimeString()}';",
                'rollback_note' => 'Non-destructive status change. Rollback: UPDATE accounts SET status = \'expired\' WHERE id IN (<affected ids from backup>).',
            ];
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #2 Expiring soon (informational only — no destructive action)
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleExpiringSoonSubscriptions(): array
    {
        $findings = [];
        $today = Carbon::today();
        $soon = Carbon::today()->addDays(7);

        $count = DB::table('subscriptions')
            ->whereIn('status', ['active', 'expiring_soon'])
            ->whereBetween('expiry_date', [$today, $soon])
            ->count();

        if ($count > 0) {
            $findings[] = [
                'category' => 'expiring_soon',
                'operation' => 'none',
                'table_name' => 'subscriptions',
                'column_name' => 'expiry_date',
                'affected_count' => $count,
                'expiration_status' => 'expiring within 7 days',
                'action' => "{$count} subscription(s) expire within 7 days. Informational only — the daily subscriptions:process-expiry job already handles status transitions and account release.",
                'risk_level' => 'low',
                'estimated_recovery_mb' => 0.0,
                'where_sql' => null,
                'where_bindings' => null,
                'sql_preview' => "SELECT * FROM subscriptions WHERE status IN ('active','expiring_soon') AND expiry_date BETWEEN '{$today->toDateString()}' AND '{$soon->toDateString()}';",
                'rollback_note' => 'No action taken — read-only signal.',
            ];
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #3 Old/inactive log & framework data consuming storage
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleArchivableLogs(): array
    {
        $findings = [];

        // activity_logs older than 180 days.
        $cutoff = Carbon::now()->subDays(180);
        $count = DB::table('activity_logs')->where('created_at', '<', $cutoff)->count();
        if ($count > 0) {
            $findings[] = $this->deleteFinding(
                category: 'inactive',
                table: 'activity_logs',
                column: 'created_at',
                affected: $count,
                expirationStatus: 'older than 180 days',
                action: "Delete {$count} activity-log entr(y/ies) older than 180 days. Download a full backup first — this is an append-only audit trail, not business data, but it has no expiry column of its own.",
                risk: 'low',
                whereSql: 'created_at < ?',
                bindings: [$cutoff->toDateTimeString()],
            );
        }

        // error_logs resolved more than 90 days ago.
        $cutoff = Carbon::now()->subDays(90);
        $count = DB::table('error_logs')->whereNotNull('resolved_at')->where('resolved_at', '<', $cutoff)->count();
        if ($count > 0) {
            $findings[] = $this->deleteFinding(
                category: 'inactive',
                table: 'error_logs',
                column: 'resolved_at',
                affected: $count,
                expirationStatus: 'resolved more than 90 days ago',
                action: "Delete {$count} error-log entr(y/ies) resolved more than 90 days ago. Unresolved errors are never touched.",
                risk: 'low',
                whereSql: 'resolved_at IS NOT NULL AND resolved_at < ?',
                bindings: [$cutoff->toDateTimeString()],
            );
        }

        // Read notifications older than 90 days.
        if ($this->tableExists('notifications')) {
            $cutoff = Carbon::now()->subDays(90);
            $count = DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'notifications',
                    column: 'read_at',
                    affected: $count,
                    expirationStatus: 'read, older than 90 days',
                    action: "Delete {$count} read notification(s) older than 90 days. Unread notifications are never touched.",
                    risk: 'low',
                    whereSql: 'read_at IS NOT NULL AND created_at < ?',
                    bindings: [$cutoff->toDateTimeString()],
                );
            }
        }

        // Stale sessions (30+ days idle) — column is an int unix timestamp.
        if ($this->tableExists('sessions')) {
            $cutoff = Carbon::now()->subDays(30)->timestamp;
            $count = DB::table('sessions')->where('last_activity', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'sessions',
                    column: 'last_activity',
                    affected: $count,
                    expirationStatus: 'idle more than 30 days',
                    action: "Delete {$count} session row(s) idle for 30+ days. Safe — these users will simply log in again.",
                    risk: 'low',
                    whereSql: 'last_activity < ?',
                    bindings: [$cutoff],
                );
            }
        }

        // Expired cache entries — safe by definition.
        if ($this->tableExists('cache')) {
            $now = Carbon::now()->timestamp;
            $count = DB::table('cache')->where('expiration', '<', $now)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'cache',
                    column: 'expiration',
                    affected: $count,
                    expirationStatus: 'already expired',
                    action: "Delete {$count} already-expired cache row(s). Framework will regenerate them on demand.",
                    risk: 'low',
                    whereSql: 'expiration < ?',
                    bindings: [$now],
                );
            }
        }

        // Old failed jobs (30+ days) — presumed already investigated.
        if ($this->tableExists('failed_jobs')) {
            $cutoff = Carbon::now()->subDays(30);
            $count = DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'failed_jobs',
                    column: 'failed_at',
                    affected: $count,
                    expirationStatus: 'failed more than 30 days ago',
                    action: "Delete {$count} failed job(s) older than 30 days. Review them in the queue tooling first if you haven't already.",
                    risk: 'low',
                    whereSql: 'failed_at < ?',
                    bindings: [$cutoff->toDateTimeString()],
                );
            }
        }

        // Expired password-reset tokens (meant to live for minutes, not days).
        if ($this->tableExists('password_reset_tokens')) {
            $cutoff = Carbon::now()->subDay();
            $count = DB::table('password_reset_tokens')->where('created_at', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'password_reset_tokens',
                    column: 'created_at',
                    affected: $count,
                    expirationStatus: 'older than 1 day',
                    action: "Delete {$count} stale password-reset token(s) older than 1 day. These are single-use and time-boxed by design.",
                    risk: 'low',
                    whereSql: 'created_at < ?',
                    bindings: [$cutoff->toDateTimeString()],
                );
            }
        }

        // Expired personal access tokens (Sanctum).
        if ($this->tableExists('personal_access_tokens')) {
            $now = Carbon::now();
            $count = DB::table('personal_access_tokens')->whereNotNull('expires_at')->where('expires_at', '<', $now)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'personal_access_tokens',
                    column: 'expires_at',
                    affected: $count,
                    expirationStatus: 'expired',
                    action: "Delete {$count} expired API token(s). They can no longer authenticate anything.",
                    risk: 'low',
                    whereSql: 'expires_at IS NOT NULL AND expires_at < ?',
                    bindings: [$now->toDateTimeString()],
                );
            }
        }

        // Stale device (push-notification) tokens.
        if ($this->tableExists('device_tokens')) {
            $cutoff = Carbon::now()->subDays(90);
            $count = DB::table('device_tokens')
                ->where(fn ($q) => $q->where('last_used_at', '<', $cutoff)->orWhere(fn ($q2) => $q2->whereNull('last_used_at')->where('created_at', '<', $cutoff)))
                ->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'device_tokens',
                    column: 'last_used_at',
                    affected: $count,
                    expirationStatus: 'unused for 90+ days',
                    action: "Delete {$count} push-notification token(s) unused for 90+ days. The device will simply re-register if it comes back.",
                    risk: 'low',
                    whereSql: '(last_used_at < ?) OR (last_used_at IS NULL AND created_at < ?)',
                    bindings: [$cutoff->toDateTimeString(), $cutoff->toDateTimeString()],
                );
            }
        }

        // Read contact messages older than 180 days.
        if ($this->tableExists('contact_messages')) {
            $cutoff = Carbon::now()->subDays(180);
            $count = DB::table('contact_messages')->where('is_read', true)->where('created_at', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: 'contact_messages',
                    column: 'created_at',
                    affected: $count,
                    expirationStatus: 'read, older than 180 days',
                    action: "Delete {$count} read contact-form message(s) older than 180 days. Unread messages are never touched.",
                    risk: 'low',
                    whereSql: 'is_read = 1 AND created_at < ?',
                    bindings: [$cutoff->toDateTimeString()],
                );
            }
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // Soft-deleted chat/team-chat messages already flagged by the app
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleSoftDeletedMessages(): array
    {
        $findings = [];
        $cutoff = Carbon::now()->subDays(30);

        foreach (['chat_messages', 'admin_messages'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'is_deleted')) {
                continue;
            }

            $count = DB::table($table)->where('is_deleted', true)->where('updated_at', '<', $cutoff)->count();
            if ($count > 0) {
                $findings[] = $this->deleteFinding(
                    category: 'inactive',
                    table: $table,
                    column: 'is_deleted',
                    affected: $count,
                    expirationStatus: 'user-deleted more than 30 days ago',
                    action: "Permanently delete {$count} message(s) in {$table} already marked deleted by their sender/admin for 30+ days.",
                    risk: 'low',
                    whereSql: 'is_deleted = 1 AND updated_at < ?',
                    bindings: [$cutoff->toDateTimeString()],
                );
            }
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #4 Duplicate / redundant records
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleDuplicates(): array
    {
        $findings = [];

        // contact_messages: identical (email, subject, message) submitted more than once.
        if ($this->tableExists('contact_messages')) {
            $dupGroups = DB::table('contact_messages')
                ->select('email', 'subject', 'message')
                ->groupBy('email', 'subject', 'message')
                ->havingRaw('COUNT(*) > 1')
                ->selectRaw('COUNT(*) as cnt')
                ->get();

            $extra = (int) $dupGroups->sum(fn ($g) => $g->cnt - 1);
            if ($extra > 0) {
                $findings[] = [
                    'category' => 'duplicate',
                    'operation' => 'select_only',
                    'table_name' => 'contact_messages',
                    'column_name' => 'email, subject, message',
                    'affected_count' => $extra,
                    'expiration_status' => null,
                    'action' => "{$extra} duplicate contact-form submission(s) found (same email/subject/message, likely double-submits). Keep the earliest of each group and remove the rest after manual review.",
                    'risk_level' => 'low',
                    'estimated_recovery_mb' => $this->estimateMb('contact_messages', $extra),
                    'where_sql' => null,
                    'where_bindings' => null,
                    'sql_preview' => "SELECT email, subject, message, COUNT(*) FROM contact_messages GROUP BY email, subject, message HAVING COUNT(*) > 1;",
                    'rollback_note' => 'Detection only — review the listed groups, then delete duplicates by id manually or re-run analysis after confirming.',
                ];
            }
        }

        // device_tokens: more than one token per (user_id, platform) — earlier ones are stale registrations.
        if ($this->tableExists('device_tokens')) {
            $dupGroups = DB::table('device_tokens')
                ->select('user_id', 'platform')
                ->whereNotNull('platform')
                ->groupBy('user_id', 'platform')
                ->havingRaw('COUNT(*) > 1')
                ->selectRaw('COUNT(*) as cnt')
                ->get();

            $extra = (int) $dupGroups->sum(fn ($g) => $g->cnt - 1);
            if ($extra > 0) {
                $findings[] = [
                    'category' => 'duplicate',
                    'operation' => 'select_only',
                    'table_name' => 'device_tokens',
                    'column_name' => 'user_id, platform',
                    'affected_count' => $extra,
                    'expiration_status' => null,
                    'action' => "{$extra} extra device token(s) where a user has more than one token registered for the same platform. Keep only the most recently used per (user, platform).",
                    'risk_level' => 'low',
                    'estimated_recovery_mb' => $this->estimateMb('device_tokens', $extra),
                    'where_sql' => null,
                    'where_bindings' => null,
                    'sql_preview' => "SELECT user_id, platform, COUNT(*) FROM device_tokens GROUP BY user_id, platform HAVING COUNT(*) > 1;",
                    'rollback_note' => 'Detection only — no rows removed automatically; verify which token is the active device before deleting others.',
                ];
            }
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #5 Missing/inefficient indexes
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleMissingIndexes(string $database): array
    {
        $findings = [];

        // Foreign-key columns with no covering index (first column of any key).
        $rows = DB::select(
            "SELECT kcu.TABLE_NAME as table_name, kcu.COLUMN_NAME as column_name, kcu.REFERENCED_TABLE_NAME as ref_table
             FROM information_schema.KEY_COLUMN_USAGE kcu
             WHERE kcu.TABLE_SCHEMA = ? AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
             AND NOT EXISTS (
                 SELECT 1 FROM information_schema.STATISTICS s
                 WHERE s.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                   AND s.TABLE_NAME = kcu.TABLE_NAME
                   AND s.SEQ_IN_INDEX = 1
                   AND s.COLUMN_NAME = kcu.COLUMN_NAME
             )",
            [$database],
        );

        foreach ($rows as $row) {
            $indexName = 'idx_'.$row->table_name.'_'.$row->column_name;
            $findings[] = [
                'category' => 'missing_index',
                'operation' => 'create_index',
                'table_name' => $row->table_name,
                'column_name' => $row->column_name,
                'affected_count' => 0,
                'expiration_status' => null,
                'action' => "Foreign key {$row->table_name}.{$row->column_name} (references {$row->ref_table}) has no covering index — lookups and cascade deletes on it do a full scan as the table grows.",
                'risk_level' => 'low',
                'estimated_recovery_mb' => 0.0,
                'where_sql' => null,
                'where_bindings' => null,
                'index_sql' => "CREATE INDEX {$indexName} ON {$row->table_name} ({$row->column_name});",
                'sql_preview' => "CREATE INDEX {$indexName} ON {$row->table_name} ({$row->column_name});",
                'rollback_note' => "Additive only. Rollback: DROP INDEX {$indexName} ON {$row->table_name};",
            ];
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #6 Slow/expensive queries (best-effort — needs performance_schema)
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleSlowQueries(): array
    {
        $findings = [];

        try {
            $enabled = DB::selectOne("SHOW VARIABLES LIKE 'performance_schema'");
            if (! $enabled || strtoupper((string) $enabled->Value) !== 'ON') {
                return $findings;
            }

            $rows = DB::select(
                "SELECT DIGEST_TEXT as query, COUNT_STAR as executions,
                        ROUND(AVG_TIMER_WAIT / 1000000000, 2) as avg_ms,
                        ROUND(SUM_TIMER_WAIT / 1000000000, 2) as total_ms
                 FROM performance_schema.events_statements_summary_by_digest
                 WHERE SCHEMA_NAME = DATABASE() AND DIGEST_TEXT IS NOT NULL
                   AND DIGEST_TEXT REGEXP '^(SELECT|UPDATE|DELETE|INSERT)'
                 ORDER BY AVG_TIMER_WAIT DESC LIMIT 5",
            );

            foreach ($rows as $row) {
                // Ignore one-off runs (a single slow migration/admin action isn't
                // a query pattern worth flagging) and anything not actually slow.
                if ((float) $row->avg_ms < 50 || (int) $row->executions < 5) {
                    continue;
                }

                $findings[] = [
                    'category' => 'slow_query',
                    'operation' => 'select_only',
                    'table_name' => '(query digest)',
                    'column_name' => null,
                    'affected_count' => (int) $row->executions,
                    'expiration_status' => null,
                    'action' => "Query pattern averaging {$row->avg_ms} ms across {$row->executions} execution(s): ".substr((string) $row->query, 0, 200),
                    'risk_level' => 'low',
                    'estimated_recovery_mb' => 0.0,
                    'where_sql' => null,
                    'where_bindings' => null,
                    'sql_preview' => 'EXPLAIN '.$row->query,
                    'rollback_note' => 'Detection only — review with EXPLAIN and add an index or rewrite the query as needed.',
                ];
            }
        } catch (Throwable) {
            // performance_schema not accessible on this host (common on shared hosting) — skip silently.
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // #8 Broken/orphaned relationships (detection only, never destructive)
    // ------------------------------------------------------------------

    /**
     * @return list<array<string,mixed>>
     */
    private function ruleOrphanedRelationships(): array
    {
        $findings = [];

        /** @var list<array{child:string,fk:string,parent:string,parentKey:string}> $relations */
        $relations = [
            ['child' => 'subscriptions', 'fk' => 'account_id', 'parent' => 'accounts', 'parentKey' => 'id'],
            ['child' => 'payment_proofs', 'fk' => 'payment_id', 'parent' => 'payments', 'parentKey' => 'id'],
            ['child' => 'chat_messages', 'fk' => 'conversation_id', 'parent' => 'conversations', 'parentKey' => 'id'],
            ['child' => 'admin_messages', 'fk' => 'admin_conversation_id', 'parent' => 'admin_conversations', 'parentKey' => 'id'],
            ['child' => 'device_tokens', 'fk' => 'user_id', 'parent' => 'users', 'parentKey' => 'id'],
        ];

        foreach ($relations as $rel) {
            if (! $this->tableExists($rel['child']) || ! $this->tableExists($rel['parent'])) {
                continue;
            }

            $count = DB::table($rel['child'].' as c')
                ->whereNotNull('c.'.$rel['fk'])
                ->whereNotExists(function ($q) use ($rel) {
                    $q->select(DB::raw(1))
                        ->from($rel['parent'].' as p')
                        ->whereColumn('p.'.$rel['parentKey'], 'c.'.$rel['fk']);
                })
                ->count();

            if ($count > 0) {
                $findings[] = [
                    'category' => 'orphaned',
                    'operation' => 'select_only',
                    'table_name' => $rel['child'],
                    'column_name' => $rel['fk'],
                    'affected_count' => $count,
                    'expiration_status' => null,
                    'action' => "{$count} row(s) in {$rel['child']} reference a {$rel['parent']} row that no longer exists. This should not happen given the foreign-key constraints — likely legacy data from before a constraint was added. Investigate before touching.",
                    'risk_level' => 'high',
                    'estimated_recovery_mb' => 0.0,
                    'where_sql' => null,
                    'where_bindings' => null,
                    'sql_preview' => "SELECT c.* FROM {$rel['child']} c WHERE c.{$rel['fk']} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$rel['parent']} p WHERE p.{$rel['parentKey']} = c.{$rel['fk']});",
                    'rollback_note' => 'Detection only — no automatic fix. Decide per-row whether to relink or remove after manual review.',
                ];
            }
        }

        return $findings;
    }

    // ------------------------------------------------------------------
    // Shared helper for the common "DELETE ... WHERE ..." finding shape.
    // ------------------------------------------------------------------

    /**
     * @param  list<mixed>  $bindings
     * @return array<string,mixed>
     */
    private function deleteFinding(
        string $category,
        string $table,
        ?string $column,
        int $affected,
        string $expirationStatus,
        string $action,
        string $risk,
        string $whereSql,
        array $bindings,
    ): array {
        return [
            'category' => $category,
            'operation' => 'delete',
            'table_name' => $table,
            'column_name' => $column,
            'affected_count' => $affected,
            'expiration_status' => $expirationStatus,
            'action' => $action,
            'risk_level' => $risk,
            'estimated_recovery_mb' => $this->estimateMb($table, $affected),
            'where_sql' => $whereSql,
            'where_bindings' => $bindings,
            'sql_preview' => "DELETE FROM {$table} WHERE ".$this->inlineWhere($whereSql, $bindings).';',
            'rollback_note' => "A backup of the exact matching rows is taken before deletion. Rollback: run the generated backup .sql file to re-insert them into {$table}.",
        ];
    }

    /**
     * Render a WHERE clause with its bindings inlined, for the human-readable
     * SQL preview only — actual execution always uses parameter binding.
     *
     * @param  list<mixed>  $bindings
     */
    private function inlineWhere(string $whereSql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? (string) $binding : "'".$binding."'";
            $whereSql = preg_replace('/\?/', $value, $whereSql, 1) ?? $whereSql;
        }

        return $whereSql;
    }
}
