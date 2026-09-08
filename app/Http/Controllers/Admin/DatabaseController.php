<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseHealthService;
use App\Services\SchemaAlterService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    public function __construct(
        private SchemaAlterService $alters,
        private DatabaseHealthService $health,
    ) {
    }

    public function index()
    {
        $this->alters->ensureTrackingTable();

        $report = $this->health->report();
        $pending = $this->alters->pending();
        $history = $this->alters->history();
        $schema = $this->schemaOverview();

        return view('admin.database.index', compact('report', 'pending', 'history', 'schema'));
    }

    public function apply()
    {
        $this->alters->ensureTrackingTable();

        $results = $this->alters->apply(auth()->id());

        // The acting super admin should stay a super admin after RBAC lands.
        if (Schema::hasColumn('users', 'role')) {
            $user = auth()->user();
            if ($user->role !== 'super_admin') {
                $user->forceFill(['role' => 'super_admin'])->save();
            }
        }

        ActivityLog::log('database_alters_applied', 'Database', null, [
            'files' => array_map(fn ($r) => $r['filename'], $results),
        ]);

        $failed = collect($results)->firstWhere('ok', false);

        if ($results === []) {
            return back()->with('success', 'Nothing to apply — schema is up to date.');
        }

        if ($failed) {
            return back()->with('error', "Stopped at {$failed['filename']}: {$failed['error']}");
        }

        $count = count($results);

        return back()->with('success', "Applied {$count} schema change(s) successfully.");
    }

    /**
     * Clear compiled views/config/routes without needing shell access — for
     * shared hosting where a deploy can leave stale compiled Blade views
     * (or cached config/routes) served even though the source files updated.
     */
    public function clearCaches()
    {
        foreach (['view:clear', 'cache:clear', 'config:clear', 'route:clear'] as $command) {
            Artisan::call($command);
        }

        ActivityLog::log('database_caches_cleared', 'Database', null, []);

        return back()->with('success', 'Caches cleared. Reload the page you were looking at.');
    }

    public function backup(DatabaseBackupService $backup)
    {
        ActivityLog::log('database_backup_downloaded', 'Database', null, []);

        return $backup->download();
    }

    /**
     * @return list<array{name:string,columns:int,rows:int}>
     */
    private function schemaOverview(): array
    {
        $database = DB::connection()->getDatabaseName();

        $rows = DB::select(
            'SELECT t.TABLE_NAME as name, t.TABLE_ROWS as row_estimate,
                    (SELECT COUNT(*) FROM information_schema.COLUMNS c
                     WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME) as column_count
             FROM information_schema.TABLES t
             WHERE t.TABLE_SCHEMA = ? AND t.TABLE_TYPE = "BASE TABLE"
             ORDER BY t.TABLE_NAME',
            [$database],
        );

        return array_map(fn ($r) => [
            'name' => $r->name,
            'columns' => (int) $r->column_count,
            'rows' => (int) $r->row_estimate,
        ], $rows);
    }
}
