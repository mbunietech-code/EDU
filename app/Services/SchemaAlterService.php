<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Applies additive schema-change files from database/alters/ to the live
 * database, tracking what has run in the `schema_alters` table.
 *
 * Errors that mean "this change is already in place" (duplicate column,
 * table already exists, duplicate index) are treated as success so that
 * re-importing a dump and re-applying is safe.
 */
class SchemaAlterService
{
    /** Substrings in a MySQL error that mean "already applied". */
    private const BENIGN_ERRORS = [
        'Duplicate column name',
        'already exists',
        'Duplicate key name',
        'Multiple primary key defined',
        "check that column/key exists",
    ];

    public function altersPath(): string
    {
        return database_path('alters');
    }

    public function ensureTrackingTable(): void
    {
        if (Schema::hasTable('schema_alters')) {
            return;
        }

        Schema::create('schema_alters', function ($table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('checksum', 64);
            $table->unsignedInteger('statements')->default(0);
            $table->boolean('ok')->default(true);
            $table->text('error')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return list<array{filename:string,checksum:string,contents:string,statements:list<string>}>
     */
    public function allFiles(): array
    {
        $dir = $this->altersPath();
        if (! File::isDirectory($dir)) {
            return [];
        }

        $files = collect(File::files($dir))
            ->filter(fn ($f) => strtolower($f->getExtension()) === 'sql')
            ->sortBy(fn ($f) => $f->getFilename())
            ->values();

        return $files->map(function ($f) {
            $contents = File::get($f->getPathname());

            return [
                'filename' => $f->getFilename(),
                'checksum' => hash('sha256', $contents),
                'contents' => $contents,
                'statements' => $this->splitStatements($contents),
            ];
        })->all();
    }

    /**
     * Files that have not been recorded as applied yet.
     *
     * @return list<array{filename:string,checksum:string,contents:string,statements:list<string>}>
     */
    public function pending(): array
    {
        $this->ensureTrackingTable();

        $applied = DB::table('schema_alters')->where('ok', true)->pluck('filename')->all();

        return array_values(array_filter(
            $this->allFiles(),
            fn ($file) => ! in_array($file['filename'], $applied, true),
        ));
    }

    /**
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function history()
    {
        $this->ensureTrackingTable();

        return DB::table('schema_alters')->orderByDesc('id')->get();
    }

    /**
     * Apply every pending file, in order. Stops at the first file that
     * fails with a non-benign error.
     *
     * @return list<array{filename:string,ok:bool,ran:int,skipped:int,error:?string}>
     */
    public function apply(?int $userId = null): array
    {
        $this->ensureTrackingTable();

        $results = [];

        foreach ($this->pending() as $file) {
            $ran = 0;
            $skipped = 0;
            $error = null;

            foreach ($file['statements'] as $statement) {
                try {
                    DB::unprepared($statement);
                    $ran++;
                } catch (Throwable $e) {
                    if ($this->isBenign($e->getMessage())) {
                        $skipped++;

                        continue;
                    }
                    $error = $e->getMessage();
                    break;
                }
            }

            $ok = $error === null;

            DB::table('schema_alters')->updateOrInsert(
                ['filename' => $file['filename']],
                [
                    'checksum' => $file['checksum'],
                    'statements' => count($file['statements']),
                    'ok' => $ok,
                    'error' => $error,
                    'applied_by' => $userId,
                    'applied_at' => $ok ? Carbon::now() : null,
                    'updated_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                ],
            );

            $results[] = [
                'filename' => $file['filename'],
                'ok' => $ok,
                'ran' => $ran,
                'skipped' => $skipped,
                'error' => $error,
            ];

            if (! $ok) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function splitStatements(string $sql): array
    {
        // Drop full-line -- comments.
        $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $clean[] = $line;
        }
        $body = implode("\n", $clean);

        return collect(explode(';', $body))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();
    }

    private function isBenign(string $message): bool
    {
        foreach (self::BENIGN_ERRORS as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
