<?php

namespace App\Console\Commands;

use App\Services\AutoReplyService;
use Illuminate\Console\Command;

class ChatAutoReplyCommand extends Command
{
    protected $signature = 'chat:auto-reply';

    protected $description = 'Send the automatic "we are running late" reply to customers whose chat message has gone unanswered.';

    public function handle(AutoReplyService $autoReply): int
    {
        $sent = $autoReply->processAll();

        $this->info($autoReply->enabled()
            ? "Sent {$sent} auto-repl".($sent === 1 ? 'y' : 'ies').'.'
            : 'Auto-reply is switched off.');

        return self::SUCCESS;
    }
}
