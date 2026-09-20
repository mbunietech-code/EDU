<?php

namespace App\Console\Commands;

use App\Services\AdminAlertService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Console\Command;

class OptimizationScanCommand extends Command
{
    protected $signature = 'optimize:scan';

    protected $description = 'Run the AI database optimization analysis, record a new scan, and alert admins by email/SMS if something urgent is found.';

    public function handle(DatabaseOptimizationService $optimizer, AdminAlertService $alerts): int
    {
        if (! $optimizer->isSupported()) {
            $this->error('Optimization analysis requires a MySQL connection.');

            return self::FAILURE;
        }

        $scan = $optimizer->scan();

        $this->info("Scan #{$scan->id} complete — {$scan->recommendations->count()} recommendation(s), ".
            "estimated {$scan->estimated_recovery_mb} MB recoverable.");

        $result = $alerts->notifyFor($scan);
        if ($result['sent']) {
            $this->info('Alert sent: '.implode('; ', $result['alerts']));
        } elseif ($result['alerts'] !== []) {
            $this->line('Alerts found but not sent (already sent within 24h, or no channel configured/failed).');
        }

        return self::SUCCESS;
    }
}
