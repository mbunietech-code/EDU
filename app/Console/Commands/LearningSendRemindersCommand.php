<?php

namespace App\Console\Commands;

use App\Services\Learning\RoomService;
use Illuminate\Console\Command;

class LearningSendRemindersCommand extends Command
{
    protected $signature = 'learning:send-reminders';

    protected $description = 'Remind audiences of live rooms that are about to start.';

    public function handle(RoomService $rooms): int
    {
        $this->info('Reminders sent for '.$rooms->sendReminders().' room(s).');

        return self::SUCCESS;
    }
}
