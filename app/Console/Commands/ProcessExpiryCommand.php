<?php

namespace App\Console\Commands;

use App\Services\ExpiryService;
use Illuminate\Console\Command;

class ProcessExpiryCommand extends Command
{
    protected $signature = 'subscriptions:process-expiry {--expired-only} {--expiring-only}';

    protected $description = 'Process subscription expiry: mark expiring-soon and expire subscriptions, releasing accounts.';

    public function handle(ExpiryService $expiryService): int
    {
        $expiredOnly = (bool) $this->option('expired-only');
        $expiringOnly = (bool) $this->option('expiring-only');

        if ($expiredOnly && $expiringOnly) {
            $this->error('Use only one of --expired-only or --expiring-only.');
            return self::FAILURE;
        }

        if ($expiredOnly) {
            $expiryService->checkExpired();
            $this->info('Processed expired subscriptions.');
        } elseif ($expiringOnly) {
            $expiryService->checkExpiringSoon();
            $this->info('Processed expiring-soon subscriptions.');
        } else {
            $expiryService->checkExpiringSoon();
            $expiryService->checkExpired();
            $this->info('Processed subscription expiry checks.');
        }

        return self::SUCCESS;
    }
}