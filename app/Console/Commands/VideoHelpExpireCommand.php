<?php

namespace App\Console\Commands;

use App\Services\VideoHelpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Video help calls nobody answered in time become "missed" and the support
 * admins get a missed-call notification. Polls do this too; the scheduler
 * covers the case where every page was closed.
 */
class VideoHelpExpireCommand extends Command
{
    protected $signature = 'video-help:expire';

    protected $description = 'Mark unanswered video help calls as missed';

    public function handle(VideoHelpService $help): int
    {
        if (! Schema::hasTable('video_help_requests')) {
            return self::SUCCESS; // database/alters/0041 not applied yet
        }

        $missed = $help->expireOverdue();
        if ($missed > 0) {
            $this->line("Missed video help calls: {$missed}");
        }

        return self::SUCCESS;
    }
}
