<?php

namespace App\Policies;

use App\Models\LearningRoom;
use App\Models\User;

/**
 * Who may do what with a live room. State rules (the room must be live to
 * join, removed participants stay out, cancelled rooms cannot start, …) live
 * in RoomService, because Gate::before lets super admins through every
 * ability here.
 */
class LearningRoomPolicy
{
    public function view(User $user, LearningRoom $room): bool
    {
        return $room->isVisibleTo($user);
    }

    public function join(User $user, LearningRoom $room): bool
    {
        return $this->view($user, $room);
    }

    public function create(User $user): bool
    {
        return $user->canHostRooms();
    }

    public function manage(User $user, LearningRoom $room): bool
    {
        return $room->isManageableBy($user);
    }

    public function viewAttendance(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room) || $user->hasPermission('rooms.view');
    }

    public function update(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }

    public function delete(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }

    public function start(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }

    public function end(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }

    public function cancel(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }

    public function moderate(User $user, LearningRoom $room): bool
    {
        return $this->manage($user, $room);
    }
}
