<?php

namespace App\Services\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomPoll;
use App\Models\LearningRoomPollVote;
use App\Models\LearningRoomSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Polls and quick quizzes in the live classroom. The host asks (one open poll
 * at a time — a new one closes the last), each participant of the running
 * session votes once, and the counts — plus the right answer of a quiz — are
 * shown to everyone once the host closes the poll. Until then only room
 * managers see the numbers, so the class is not swayed by them.
 */
class RoomPollService
{
    public const QUESTION_MAX = 300;

    public const OPTION_MAX = 120;

    /** Polls of the running session returned by the feed (newest). */
    public const FEED_LIMIT = 10;

    /**
     * @param  list<mixed>  $options
     */
    public function create(LearningRoom $room, User $actor, string $question, array $options, ?int $correct): LearningRoomPoll
    {
        $question = trim($question);
        $options = array_values(array_filter(
            array_map(fn ($o) => trim((string) $o), $options),
            fn (string $o) => $o !== '',
        ));

        $errors = match (true) {
            $question === '' => ['question' => 'Write the question.'],
            mb_strlen($question) > self::QUESTION_MAX => ['question' => 'The question may not be longer than '.self::QUESTION_MAX.' characters.'],
            count($options) < LearningRoomPoll::MIN_OPTIONS => ['options' => 'Give at least '.LearningRoomPoll::MIN_OPTIONS.' options.'],
            count($options) > LearningRoomPoll::MAX_OPTIONS => ['options' => 'Give at most '.LearningRoomPoll::MAX_OPTIONS.' options.'],
            collect($options)->contains(fn ($o) => mb_strlen($o) > self::OPTION_MAX) => ['options' => 'Options may not be longer than '.self::OPTION_MAX.' characters.'],
            $correct !== null && ($correct < 0 || $correct >= count($options)) => ['correct_option' => 'Pick the right answer from the options.'],
            default => null,
        };

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $session = $this->liveSession($room);

        return DB::transaction(function () use ($room, $actor, $session, $question, $options, $correct) {
            LearningRoomPoll::query()
                ->where('learning_room_session_id', $session->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now(), 'updated_at' => now()]);

            return LearningRoomPoll::create([
                'learning_room_id' => $room->id,
                'learning_room_session_id' => $session->id,
                'created_by' => $actor->id,
                'question' => $question,
                'options' => $options,
                'correct_option' => $correct,
            ]);
        });
    }

    public function vote(LearningRoomPoll $poll, User $user, int $option): void
    {
        $room = $poll->room;
        $session = $this->liveSession($room);

        $rejection = match (true) {
            (int) $poll->learning_room_session_id !== (int) $session->id || ! $poll->isOpen() => 'This poll is closed.',
            $room->isManageableBy($user) => 'Hosts run the poll and do not vote.',
            $option < 0 || $option >= count($poll->options) => 'Choose one of the options.',
            ! LearningRoomAttendance::query()
                ->where('learning_room_session_id', $session->id)
                ->where('user_id', $user->id)
                ->whereNull('removed_at')
                ->exists() => 'Join the class to vote.',
            default => null,
        };

        if ($rejection !== null) {
            throw ValidationException::withMessages(['option' => $rejection]);
        }

        try {
            LearningRoomPollVote::create([
                'learning_room_poll_id' => $poll->id,
                'user_id' => $user->id,
                'choice' => $option,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['option' => 'You have already voted.']);
        }
    }

    public function close(LearningRoomPoll $poll): void
    {
        if ($poll->isOpen()) {
            $poll->forceFill(['closed_at' => now()])->save();
        }
    }

    /**
     * The running session's polls for $viewer, oldest first:
     * ['id','question','options','is_open','is_quiz','total','my_vote',
     *  'results' (per-option counts; managers, or once closed),
     *  'correct_option' (managers, or once closed)].
     *
     * @return list<array<string,mixed>>
     */
    public function feed(LearningRoom $room, User $viewer): array
    {
        $session = $this->openSession($room);

        if (! $session) {
            return [];
        }

        $polls = LearningRoomPoll::query()
            ->where('learning_room_session_id', $session->id)
            ->orderByDesc('id')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->reverse()
            ->values();

        if ($polls->isEmpty()) {
            return [];
        }

        $counts = LearningRoomPollVote::query()
            ->whereIn('learning_room_poll_id', $polls->pluck('id'))
            ->selectRaw('learning_room_poll_id, choice, COUNT(*) as votes')
            ->groupBy('learning_room_poll_id', 'choice')
            ->get()
            ->groupBy('learning_room_poll_id');

        $mine = LearningRoomPollVote::query()
            ->whereIn('learning_room_poll_id', $polls->pluck('id'))
            ->where('user_id', $viewer->id)
            ->pluck('choice', 'learning_room_poll_id');

        $manager = $room->isManageableBy($viewer);

        return $polls->map(function (LearningRoomPoll $poll) use ($counts, $mine, $manager) {
            $results = array_fill(0, count($poll->options), 0);
            foreach ($counts->get($poll->id, collect()) as $row) {
                if (array_key_exists((int) $row->choice, $results)) {
                    $results[(int) $row->choice] = (int) $row->votes;
                }
            }

            $reveal = $manager || ! $poll->isOpen();

            return [
                'id' => $poll->id,
                'question' => $poll->question,
                'options' => array_values($poll->options),
                'is_open' => $poll->isOpen(),
                'is_quiz' => $poll->isQuiz(),
                'total' => array_sum($results),
                'my_vote' => $mine->has($poll->id) ? (int) $mine->get($poll->id) : null,
                'results' => $reveal ? $results : null,
                'correct_option' => $reveal ? $poll->correct_option : null,
            ];
        })->all();
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
            throw ValidationException::withMessages(['status' => 'Polls only run while the class is live.']);
        }

        return $session;
    }
}
