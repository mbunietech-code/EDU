<?php

namespace App\Services\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomBoardStroke;
use App\Models\LearningRoomSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * The live classroom's shared whiteboard, one per session. The host opens and
 * closes it, may let everyone draw, and may clear it. Strokes are drawn live
 * over SFU data packets and saved here when the pen lifts, so the board
 * survives a reload and late joiners see it. board_version rises whenever
 * strokes disappear (clear / undo) so clients reload the whole board.
 *
 * A stroke's data: {c: "#rrggbb" (palette only), w: pen width in 1/1000 of
 * the board width, p: [x0, y0, x1, y1, …] as integers 0..10000 (board
 * coordinates, so it scales to any screen), s: 1 for shapes drawn with sharp
 * corners (optional)}.
 */
class RoomBoardService
{
    /** Pen colours (the last one is the eraser: board white). */
    public const COLOURS = ['#111827', '#dc2626', '#2563eb', '#16a34a', '#f59e0b', '#ffffff'];

    public const MIN_WIDTH = 1;

    public const MAX_WIDTH = 80;

    public const MAX_POINTS = 1500;

    public const MAX_STROKES = 5000;

    /** Strokes returned per request (the client pages through a big board). */
    public const PAGE = 500;

    /** @return array{active:bool,all_can_draw:bool,version:int} */
    public function state(LearningRoom $room): array
    {
        $session = $this->openSession($room);

        return [
            'active' => (bool) $session?->board_active,
            'all_can_draw' => (bool) $session?->board_all_can_draw,
            'version' => (int) $session?->board_version,
        ];
    }

    /**
     * Host switches: open / close the board, let everyone draw, clear it.
     *
     * @param  array{active?:bool,all_can_draw?:bool,clear?:bool}  $switches
     */
    public function update(LearningRoom $room, array $switches): void
    {
        $session = $this->liveSession($room);

        if (array_key_exists('active', $switches)) {
            $session->board_active = (bool) $switches['active'];
        }

        if (array_key_exists('all_can_draw', $switches)) {
            $session->board_all_can_draw = (bool) $switches['all_can_draw'];
        }

        if (! empty($switches['clear'])) {
            LearningRoomBoardStroke::query()->where('learning_room_session_id', $session->id)->delete();
            $session->board_version = (int) $session->board_version + 1;
        }

        $session->save();
    }

    public function canDraw(LearningRoom $room, User $user, ?LearningRoomSession $session = null): bool
    {
        $session ??= $this->openSession($room);

        if (! $session || ! $session->board_active || ! $room->isLive()) {
            return false;
        }

        if ($room->isManageableBy($user)) {
            return true;
        }

        return $session->board_all_can_draw && LearningRoomAttendance::query()
            ->where('learning_room_session_id', $session->id)
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->exists();
    }

    /**
     * @param  array<string,mixed>  $data  {uid, c, w, p}
     */
    public function addStroke(LearningRoom $room, User $user, array $data): LearningRoomBoardStroke
    {
        $session = $this->liveSession($room);

        if (! $this->canDraw($room, $user, $session)) {
            throw ValidationException::withMessages(['board' => 'Only the host can draw on the board right now.']);
        }

        $uid = (string) ($data['uid'] ?? '');
        $colour = strtolower((string) ($data['c'] ?? ''));
        $width = $data['w'] ?? null;
        $points = $data['p'] ?? null;

        $error = match (true) {
            ! preg_match('/^[A-Za-z0-9_-]{8,40}$/', $uid) => 'Invalid stroke id.',
            ! in_array($colour, self::COLOURS, true) => 'Choose a colour from the palette.',
            ! is_int($width) || $width < self::MIN_WIDTH || $width > self::MAX_WIDTH => 'Invalid pen size.',
            ! is_array($points) || ! array_is_list($points) || count($points) < 2 || count($points) % 2 !== 0
                || count($points) > self::MAX_POINTS * 2 => 'Invalid stroke.',
            collect($points)->contains(fn ($v) => ! is_int($v) || $v < 0 || $v > 10000) => 'Invalid stroke.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['board' => $error]);
        }

        if (LearningRoomBoardStroke::query()->where('learning_room_session_id', $session->id)->count() >= self::MAX_STROKES) {
            throw ValidationException::withMessages(['board' => 'The board is full. Ask the host to clear it.']);
        }

        try {
            return LearningRoomBoardStroke::create([
                'learning_room_session_id' => $session->id,
                'user_id' => $user->id,
                'uid' => $uid,
                // s = sharp corners (shapes: lines, boxes, arrows) instead of a smoothed pen line.
                'data' => ['c' => $colour, 'w' => $width, 'p' => $points] + (! empty($data['s']) ? ['s' => 1] : []),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // A retried upload of the same stroke.
            return LearningRoomBoardStroke::query()
                ->where('learning_room_session_id', $session->id)
                ->where('uid', $uid)
                ->firstOrFail();
        }
    }

    /** Undo: people remove their own strokes; the host may remove any. */
    public function deleteStroke(LearningRoom $room, User $user, LearningRoomBoardStroke $stroke): void
    {
        $session = $this->liveSession($room);

        if ((int) $stroke->learning_room_session_id !== (int) $session->id) {
            throw ValidationException::withMessages(['board' => 'This stroke is not on the current board.']);
        }

        if ((int) $stroke->user_id !== (int) $user->id && ! $room->isManageableBy($user)) {
            throw ValidationException::withMessages(['board' => 'You can only undo your own drawing.']);
        }

        $stroke->delete();
        $session->increment('board_version');
    }

    /**
     * Strokes of the running board after $afterId (oldest first, one page).
     *
     * @return array{version:int,strokes:list<array<string,mixed>>,more:bool}
     */
    public function strokes(LearningRoom $room, int $afterId = 0): array
    {
        $session = $this->openSession($room);

        if (! $session) {
            return ['version' => 0, 'strokes' => [], 'more' => false];
        }

        $rows = LearningRoomBoardStroke::query()
            ->where('learning_room_session_id', $session->id)
            ->where('id', '>', max(0, $afterId))
            ->orderBy('id')
            ->limit(self::PAGE + 1)
            ->get();

        return [
            'version' => (int) $session->board_version,
            'strokes' => $rows->take(self::PAGE)->map(fn (LearningRoomBoardStroke $s) => [
                'id' => $s->id,
                'uid' => $s->uid,
                'user_id' => $s->user_id,
            ] + $s->data)->values()->all(),
            'more' => $rows->count() > self::PAGE,
        ];
    }

    private function openSession(LearningRoom $room): ?LearningRoomSession
    {
        return LearningRoomSession::query()
            ->where('learning_room_id', $room->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    private function liveSession(LearningRoom $room): LearningRoomSession
    {
        $session = $room->isLive() ? $this->openSession($room) : null;

        if (! $session) {
            throw ValidationException::withMessages(['status' => 'The whiteboard only works while the class is live.']);
        }

        return $session;
    }
}
