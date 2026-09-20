<?php

namespace App\Console\Commands;

use App\Services\DatabaseOptimizationService;
use Illuminate\Console\Command;

class OptimizationScanCommand extends Command
{
    protected $signature = 'optimize:scan';

    protected $description = 'Run the AI database optimization analysis and record a new scan with recommendations.';

    public function handle(DatabaseOptimizationService $optimizer): int
    {
        if (! $optimizer->isSupported()) {
            $this->error('Optimization analysis requires a MySQL connection.');

            return self::FAILURE;
        }

        $scan = $optimizer->scan();

        $this->info("Scan #{$scan->id} complete — {$scan->recommendations->count()} recommendation(s), ".
            "estimated {$scan->estimated_recovery_mb} MB recoverable.");

        return self::SUCCESS;
    }
}
