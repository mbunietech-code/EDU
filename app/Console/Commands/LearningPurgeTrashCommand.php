<?php

namespace App\Console\Commands;

use App\Services\Learning\LearningDeletionService;
use Illuminate\Console\Command;

class LearningPurgeTrashCommand extends Command
{
    protected $signature = 'learning:purge-trash {--days= : Override the retention period (default config learning.trash_retention_days)}';

    protected $description = 'Permanently delete learning content that has been in the trash past the retention period.';

    public function handle(LearningDeletionService $deletions): int
    {
        $days = $this->option('days');

        if ($days !== null && (! ctype_digit((string) $days) || (int) $days < 1)) {
            $this->error('--days must be a positive whole number.');

            return self::FAILURE;
        }

        $this->info('Purged '.$deletions->purgeExpired($days !== null ? (int) $days : null).' trashed learning item(s).');

        return self::SUCCESS;
    }
}
