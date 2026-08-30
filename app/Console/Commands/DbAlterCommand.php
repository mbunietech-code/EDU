<?php

namespace App\Console\Commands;

use App\Services\SchemaAlterService;
use Illuminate\Console\Command;

class DbAlterCommand extends Command
{
    protected $signature = 'db:alter {--pending : List pending alter files} {--apply : Apply all pending alter files}';

    protected $description = 'Inspect or apply additive schema-change files from database/alters/';

    public function handle(SchemaAlterService $alters): int
    {
        $alters->ensureTrackingTable();

        if ($this->option('apply')) {
            $results = $alters->apply();

            if ($results === []) {
                $this->info('Nothing to apply — schema is up to date.');

                return self::SUCCESS;
            }

            foreach ($results as $r) {
                $this->line(sprintf(
                    ' %s %s  (ran %d, skipped %d)%s',
                    $r['ok'] ? '<info>OK</info>' : '<error>FAIL</error>',
                    $r['filename'],
                    $r['ran'],
                    $r['skipped'],
                    $r['error'] ? "  — {$r['error']}" : '',
                ));
            }

            return collect($results)->contains('ok', false) ? self::FAILURE : self::SUCCESS;
        }

        $pending = $alters->pending();

        if ($pending === []) {
            $this->info('No pending schema changes.');

            return self::SUCCESS;
        }

        $this->warn(count($pending).' pending schema change(s):');
        foreach ($pending as $file) {
            $this->line("  - {$file['filename']} (".count($file['statements']).' statement(s))');
        }
        $this->newLine();
        $this->line('Run <comment>php artisan db:alter --apply</comment> to apply them.');

        return self::SUCCESS;
    }
}
