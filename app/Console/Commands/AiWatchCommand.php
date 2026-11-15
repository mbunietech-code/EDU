<?php

namespace App\Console\Commands;

use App\Services\Ai\AiWatchdog;
use Illuminate\Console\Command;

class AiWatchCommand extends Command
{
    protected $signature = 'ai:watch';

    protected $description = 'AI Assistant watchman: ring the right admins about new errors, waiting payments, waiting customers and missed video help calls.';

    public function handle(AiWatchdog $watchdog): int
    {
        $new = $watchdog->scan();

        $this->line("AI watch: {$new} new problem(s) rung.");

        return self::SUCCESS;
    }
}
