<?php

namespace App\Services\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Breakout rooms of a live class. The host sets how many rooms there are and
 * who goes where (by hand or shuffled), then opens them: every assigned
 * participant's classroom moves to that room's own SFU room with a fresh
 * token, and back to the main room when the host closes them. Hosts are not
 * assigned; they visit any room (their attendance remembers which).
 *
 * Laravel decides the SFU room in every token, so a participant can only ever
 * be in the main room or the room they were given.
 */
class RoomBreakoutService
{
    public const MAX_ROOMS = 20;

    public function __construct(protected LiveProvider $live)
    {
    }

    public function openSession(LearningRoom $room): ?LearningRoomSession
    {
        return LearningRoomSession::query()
            ->where('learning_room_id', $room->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    /** The breakout room this attendance is in right now (null = main room). */
    public function currentRoom(?LearningRoomSession $session, ?LearningRoomAttendance $attendance): ?int
    {
        if (! $session || ! $session->breakouts_open || ! $attendance || ! $attendance->breakout_number) {
            return null;
        }

        return $attendance->breakout_number <= $session->breakout_count ? (int) $attendance->breakout_number : null;
    }

    /** The SFU room this attendance is connected to right now. */
    public function sfuRoomFor(LearningRoom $room, ?LearningRoomAttendance $attendance, ?LearningRoomSession $session = null): string
    {
        $session ??= $attendance ? $this->openSession($room) : null;

        return $this->live->sfuRoomName($room, $this->currentRoom($session, $attendance));
    }

    /**
     * Where a join / fresh token puts this person. Participants go to the room
     * they are assigned to while rooms are open; a host goes to the room they
     * ask for (and that is remembered as the room they visit).
     */
    public function roomForJoin(LearningRoom $room, LearningRoomSession $session, LearningRoomAttendance $attendance, bool $manager, ?int $requested): ?int
    {
        if (! $manager) {
            return $this->currentRoom($session, $attendance);
        }

        $visit = $session->breakouts_open && $requested !== null && $requested >= 1 && $requested <= $session->breakout_count
            ? $requested
            : null;

        if ($attendance->breakout_number !== $visit) {
            $attendance->forceFill(['breakout_number' => $visit])->save();
        }

        return $visit;
    }

    /**
     * Set the number of rooms and who is in which. $assignments maps user id →
     * room number (null / 0 = stays in the main room); people left out keep
     * their room. Hosts are never assigned.
     *
     * @param  array<int|string,int|null>  $assignments
     */
    public function configure(LearningRoom $room, int $count, array $assignments = []): void
    {
        if ($count < 1 || $count > self::MAX_ROOMS) {
            throw ValidationException::withMessages(['count' => 'Choose between 1 and '.self::MAX_ROOMS.' rooms.']);
        }

        foreach ($assignments as $number) {
            if ($number !== null && ((int) $number < 0 || (int) $number > $count)) {
                throw ValidationException::withMessages(['assignments' => 'Rooms are numbered 1 to '.$count.'.']);
            }
        }

        $session = $this->liveSession($room);

        DB::transaction(function () use ($room, $session, $count, $assignments) {
            $session->forceFill(['breakout_count' => $count])->save();

            // Fewer rooms than before: people in the removed rooms go back to the main room.
            LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->where('breakout_number', '>', $count)
                ->update(['breakout_number' => null]);

            foreach ($this->participants($room, $session) as $attendance) {
                if (array_key_exists($attendance->user_id, $assignments)) {
                    $number = (int) $assignments[$attendance->user_id];
                    $attendance->forceFill(['breakout_number' => $number >= 1 ? $number : null])->save();
                }
            }
        });
    }

    /** Spread everyone in the class evenly and randomly over $count rooms. */
    public function shuffle(LearningRoom $room, int $count): void
    {
        $session = $this->liveSession($room);
        $people = $this->participants($room, $session, presentOnly: true)->shuffle()->values();

        $assignments = [];
        foreach ($people as $i => $attendance) {
            $assignments[$attendance->user_id] = ($i % max(1, $count)) + 1;
        }

        $this->configure($room, $count, $assignments);
    }

    public function open(LearningRoom $room): void
    {
        $session = $this->liveSession($room);

        if ($session->breakout_count < 1) {
            throw ValidationException::withMessages(['count' => 'Create the rooms first.']);
        }

        $session->forceFill(['breakouts_open' => true])->save();
    }

    /** Everyone back to the main room (assignments are kept for next time). */
    public function close(LearningRoom $room): void
    {
        $session = $this->liveSession($room);
        $session->forceFill(['breakouts_open' => false])->save();

        // Hosts stop "visiting".
        LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->where('role', 'host')
            ->whereNotNull('breakout_number')
            ->update(['breakout_number' => null]);
    }

    /**
     * Breakout state for the classroom feed.
     *
     * @return array{open:bool,count:int}
     */
    public function state(?LearningRoomSession $session): array
    {
        return [
            'open' => (bool) $session?->breakouts_open,
            'count' => (int) $session?->breakout_count,
        ];
    }

    /** Every SFU room of the session (main first) — to disconnect everyone at the end. */
    public function allSfuRooms(LearningRoom $room, ?LearningRoomSession $session): array
    {
        $names = [$this->live->roomName($room)];

        for ($n = 1; $n <= (int) $session?->breakout_count; $n++) {
            $names[] = $this->live->sfuRoomName($room, $n);
        }

        return $names;
    }

    /** @return \Illuminate\Support\Collection<int,LearningRoomAttendance> non-host, not removed */
    private function participants(LearningRoom $room, LearningRoomSession $session, bool $presentOnly = false)
    {
        return LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->where('role', '!=', 'host')
            ->whereNull('removed_at')
            ->when($presentOnly, fn ($q) => $q->present())
            ->get();
    }

    private function liveSession(LearningRoom $room): LearningRoomSession
    {
        $session = $room->isLive() ? $this->openSession($room) : null;

        if (! $session) {
            throw ValidationException::withMessages(['status' => 'Breakout rooms only run while the class is live.']);
        }

        return $session;
    }
}
