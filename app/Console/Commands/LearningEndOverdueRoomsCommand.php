<?php

namespace App\Console\Commands;

use App\Services\Learning\RoomService;
use Illuminate\Console\Command;

class LearningEndOverdueRoomsCommand extends Command
{
    protected $signature = 'learning:end-overdue-rooms';

    protected $description = 'End live classes whose planned time is up and disconnect everyone.';

    public function handle(RoomService $rooms): int
    {
        $this->info('Ended '.$rooms->endOverdueRooms().' class(es) whose time was up.');

        return self::SUCCESS;
    }
}
