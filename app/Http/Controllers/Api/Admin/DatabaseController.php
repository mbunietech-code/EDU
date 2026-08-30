<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseHealthService;
use App\Services\SchemaAlterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DatabaseController extends Controller
{
    public function __construct(
        private SchemaAlterService $alters,
        private DatabaseHealthService $health,
    ) {
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Super admin only.');
    }

    public function index(Request $request): JsonResponse
    {
        $this->guard($request);
        $this->alters->ensureTrackingTable();

        return response()->json(['data' => [
            'health' => $this->health->report(),
            'pending' => collect($this->alters->pending())->map(fn ($f) => [
                'filename' => $f['filename'],
                'statements' => count($f['statements'] ?? []),
            ])->values(),
            'history' => collect($this->alters->history())->map(fn ($h) => [
                'filename' => $h->filename,
                'ok' => (bool) $h->ok,
                'ran' => $h->ran ?? null,
                'skipped' => $h->skipped ?? null,
                'error' => $h->error ?? null,
                'applied_at' => $h->created_at ?? null,
            ])->values(),
            'backups' => $this->backupList(),
        ]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $this->guard($request);
        $this->alters->ensureTrackingTable();

        $results = $this->alters->apply($request->user()->id);

        if (Schema::hasColumn('users', 'role') && $request->user()->role !== 'super_admin') {
            $request->user()->forceFill(['role' => 'super_admin'])->save();
        }

        ActivityLog::log('database_alters_applied', 'Database', null, [
            'files' => array_map(fn ($r) => $r['filename'], $results),
        ]);

        if ($results === []) {
            return response()->json(['message' => 'Nothing to apply — schema is up to date.', 'data' => ['results' => []]]);
        }

        $failed = collect($results)->firstWhere('ok', false);

        return response()->json([
            'message' => $failed
                ? "Stopped at {$failed['filename']}: {$failed['error']}"
                : 'Applied '.count($results).' schema change(s).',
            'data' => ['results' => $results, 'failed' => (bool) $failed],
        ], $failed ? 422 : 200);
    }

    public function backup(Request $request, DatabaseBackupService $backup): JsonResponse
    {
        $this->guard($request);

        $filename = $backup->filename();

        // Build the dump and store it server-side; the file download itself
        // stays on the website, which handles large downloads cleanly.
        Storage::disk('local')->put('backups/'.$filename, $backup->dumpToString());

        ActivityLog::log('database_backup_created', 'Database', null, ['filename' => $filename, 'via' => 'app']);

        return response()->json([
            'message' => 'Backup created and stored on the server.',
            'data' => [
                'filename' => $filename,
                'size' => Storage::disk('local')->size('backups/'.$filename),
                'download_hint' => 'Download it from the Database page on the website.',
            ],
        ]);
    }

    public function downloadBackup(Request $request, string $name)
    {
        $this->guard($request);

        $path = 'backups/'.basename($name);
        abort_unless(Storage::disk('local')->exists($path), 404);

        ActivityLog::log('database_backup_downloaded', 'Database', null, ['filename' => basename($name), 'via' => 'app']);

        return Storage::disk('local')->download($path);
    }

    private function backupList(): array
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->sortDesc()
            ->take(10)
            ->map(fn ($f) => [
                'filename' => basename($f),
                'size' => Storage::disk('local')->size($f),
                'created_at' => date('c', Storage::disk('local')->lastModified($f)),
            ])
            ->values()
            ->all();
    }
}
