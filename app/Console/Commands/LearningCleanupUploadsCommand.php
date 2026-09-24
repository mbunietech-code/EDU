<?php

namespace App\Console\Commands;

use App\Services\Learning\ChunkedUploadService;
use Illuminate\Console\Command;

class LearningCleanupUploadsCommand extends Command
{
    protected $signature = 'learning:cleanup-uploads {--hours=24 : Remove unfinished uploads idle for longer than this}';

    protected $description = 'Delete abandoned chunked lesson uploads from temporary storage.';

    public function handle(ChunkedUploadService $uploads): int
    {
        $hours = (string) $this->option('hours');

        if (! ctype_digit($hours) || (int) $hours < 1) {
            $this->error('--hours must be a positive whole number.');

            return self::FAILURE;
        }

        $this->info('Removed '.$uploads->cleanup((int) $hours).' abandoned upload(s).');

        return self::SUCCESS;
    }
}
