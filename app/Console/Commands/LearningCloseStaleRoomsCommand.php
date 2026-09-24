<?php

namespace App\Console\Commands;

use App\Services\Learning\RoomService;
use Illuminate\Console\Command;

class LearningCloseStaleRoomsCommand extends Command
{
    protected $signature = 'learning:close-stale-rooms';

    protected $description = 'End live rooms that overran their slot and have nobody left in them.';

    public function handle(RoomService $rooms): int
    {
        $this->info('Closed '.$rooms->closeStaleRooms().' stale room(s).');

        return self::SUCCESS;
    }
}
