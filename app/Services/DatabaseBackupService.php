<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a full mysqldump-style .sql backup (structure + data) of the
 * current database without needing shell access to `mysqldump`.
 *
 * Rows are streamed table-by-table in chunks so memory stays flat even
 * for large tables.
 */
class DatabaseBackupService
{
    private int $chunk = 500;

    public function filename(): string
    {
        $db = (string) config('database.connections.'.config('database.default').'.database');

        return $db.'_backup_'.now()->format('Y-m-d_His').'.sql';
    }

    /**
     * Build the full backup as a string (used by the API, which stores the
     * file server-side instead of streaming it to the client).
     */
    public function dumpToString(): string
    {
        ob_start();
        $pdo = DB::connection()->getPdo();
        $database = DB::connection()->getDatabaseName();

        $this->line('-- MbunieEduHub database backup');
        $this->line('-- Database: '.$database);
        $this->line('-- Generated: '.now()->toDateTimeString());
        $this->line('SET NAMES utf8mb4;');
        $this->line('SET FOREIGN_KEY_CHECKS = 0;');
        $this->line('');

        foreach ($this->tables($database) as $table) {
            $this->dumpTable($pdo, $table);
        }

        $this->line('SET FOREIGN_KEY_CHECKS = 1;');

        return (string) ob_get_clean();
    }

    public function download(): StreamedResponse
    {
        $filename = $this->filename();

        return new StreamedResponse(function () {
            $pdo = DB::connection()->getPdo();
            $database = DB::connection()->getDatabaseName();

            $this->line('-- MbunieEduHub database backup');
            $this->line('-- Database: '.$database);
            $this->line('-- Generated: '.now()->toDateTimeString());
            $this->line('SET NAMES utf8mb4;');
            $this->line('SET FOREIGN_KEY_CHECKS = 0;');
            $this->line('');

            foreach ($this->tables($database) as $table) {
                $this->dumpTable($pdo, $table);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }

            $this->line('SET FOREIGN_KEY_CHECKS = 1;');
        }, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Back up exactly the rows an optimization recommendation is about to
     * change/delete — CREATE TABLE + matching rows only, so it can be
     * restored on its own without touching the rest of the table. Stored
     * under storage/app/optimization-backups/ (same disk as full backups)
     * and the relative path is returned for the recommendation's audit
     * record.
     *
     * @param  list<mixed>  $bindings
     */
    public function backupRows(string $table, string $whereSql, array $bindings, string $tag): string
    {
        $quoted = '`'.str_replace('`', '', $table).'`';
        $pdo = DB::connection()->getPdo();

        ob_start();
        $this->line('-- Optimization backup for table: '.$table);
        $this->line('-- Generated: '.now()->toDateTimeString());
        $this->line('SET NAMES utf8mb4;');
        $this->line('');

        $create = DB::selectOne("SHOW CREATE TABLE {$quoted}");
        $createSql = $create->{'Create Table'} ?? null;
        if ($createSql) {
            $this->line('-- DROP TABLE IF EXISTS '.$quoted.';');
            $this->line($createSql.';');
            $this->line('');
        }

        $rows = DB::select("SELECT * FROM {$quoted} WHERE {$whereSql}", $bindings);
        $this->dumpRowValues($pdo, $quoted, $rows);
        $content = (string) ob_get_clean();

        $filename = 'optimization-backups/'.now()->format('Y-m-d_His').'_'.$tag.'_'.$table.'.sql';
        Storage::disk('local')->put($filename, $content);

        return $filename;
    }

    /**
     * @param  list<object>  $rows
     */
    private function dumpRowValues(\PDO $pdo, string $quotedTable, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, $this->chunk) as $batch) {
            $values = [];
            foreach ($batch as $row) {
                $cells = [];
                foreach ((array) $row as $value) {
                    if ($value === null) {
                        $cells[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $cells[] = (string) $value;
                    } else {
                        $cells[] = $pdo->quote((string) $value);
                    }
                }
                $values[] = '('.implode(',', $cells).')';
            }

            $this->line("INSERT INTO {$quotedTable} VALUES ".implode(',', $values).';');
        }
    }

    /**
     * @return list<string>
     */
    private function tables(string $database): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME as name FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE" ORDER BY TABLE_NAME',
            [$database],
        );

        return array_map(fn ($r) => $r->name, $rows);
    }

    private function dumpTable(\PDO $pdo, string $table): void
    {
        $quoted = '`'.str_replace('`', '', $table).'`';

        $this->line('--');
        $this->line('-- Table: '.$table);
        $this->line('--');
        $this->line("DROP TABLE IF EXISTS {$quoted};");

        $create = DB::selectOne("SHOW CREATE TABLE {$quoted}");
        $createSql = $create->{'Create Table'} ?? ($create->{'Create View'} ?? null);
        if ($createSql) {
            $this->line($createSql.';');
            $this->line('');
        }

        $offset = 0;
        while (true) {
            $rows = DB::select("SELECT * FROM {$quoted} LIMIT {$this->chunk} OFFSET {$offset}");
            if ($rows === []) {
                break;
            }

            $values = [];
            foreach ($rows as $row) {
                $cells = [];
                foreach ((array) $row as $value) {
                    if ($value === null) {
                        $cells[] = 'NULL';
                    } elseif (is_int($value) || is_float($value)) {
                        $cells[] = (string) $value;
                    } else {
                        $cells[] = $pdo->quote((string) $value);
                    }
                }
                $values[] = '('.implode(',', $cells).')';
            }

            $this->line("INSERT INTO {$quoted} VALUES ".implode(',', $values).';');

            $offset += $this->chunk;
            if (count($rows) < $this->chunk) {
                break;
            }
        }

        $this->line('');
    }

    private function line(string $text): void
    {
        echo $text."\n";
    }
}
