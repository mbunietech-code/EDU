<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomPoll;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/** Polls and quizzes in the live classroom. */
class ClassroomPollsTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->useLiveServer();
    }

    /** @return array{0:User,1:User,2:LearningRoom} host, learner (in the call), room */
    private function classInProgress(): array
    {
        $host = User::factory()->create(['can_teach' => true]);
        $learner = User::factory()->create();
        $room = LearningRoom::create([
            'title' => 'Physics live',
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
        app(RoomService::class)->start($room, $host);
        app(RoomService::class)->join($room->refresh(), $host);
        app(RoomService::class)->join($room, $learner);

        return [$host, $learner, $room];
    }

    private function ask(User $host, LearningRoom $room, array $body = [])
    {
        return $this->actingAs($host)->postJson(route('studio.rooms.polls.store', $room->id), $body + [
            'question' => 'What is the unit of force?',
            'options' => ['Newton', 'Joule', 'Watt'],
            'correct_option' => 0,
        ]);
    }

    private function vote(User $user, LearningRoom $room, LearningRoomPoll $poll, int $option)
    {
        return $this->actingAs($user)->postJson(route('learn.rooms.polls.vote', [$room, $poll]), ['option' => $option]);
    }

    private function pollsFor(User $viewer, LearningRoom $room): array
    {
        return $this->actingAs($viewer)->getJson(route('learn.rooms.feed', $room))->assertOk()->json('polls');
    }

    public function test_results_stay_hidden_from_learners_until_the_host_closes_the_quiz(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->ask($host, $room)->assertCreated();
        $poll = LearningRoomPoll::firstOrFail();

        $this->vote($learner, $room, $poll, 1)->assertCreated();

        $learnerView = $this->pollsFor($learner, $room)[0];
        $this->assertSame(1, $learnerView['my_vote']);
        $this->assertSame(1, $learnerView['total']);
        $this->assertNull($learnerView['results']);
        $this->assertNull($learnerView['correct_option']);

        $hostView = $this->pollsFor($host, $room)[0];
        $this->assertSame([0, 1, 0], $hostView['results']);
        $this->assertSame(0, $hostView['correct_option']);

        $this->actingAs($host)->postJson(route('studio.rooms.polls.close', [$room->id, $poll->id]))->assertOk();

        $closed = $this->pollsFor($learner, $room)[0];
        $this->assertFalse($closed['is_open']);
        $this->assertTrue($closed['is_quiz']);
        $this->assertSame([0, 1, 0], $closed['results']);
        $this->assertSame(0, $closed['correct_option']);
    }

    public function test_each_learner_votes_once_and_only_while_open(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->ask($host, $room)->assertCreated();
        $poll = LearningRoomPoll::firstOrFail();

        $this->vote($learner, $room, $poll, 0)->assertCreated();
        $this->vote($learner, $room, $poll, 2)->assertUnprocessable()->assertJsonValidationErrors('option');
        $this->vote($learner, $room, $poll, 9)->assertUnprocessable();

        $late = User::factory()->create();
        app(RoomService::class)->join($room, $late);
        $this->actingAs($host)->postJson(route('studio.rooms.polls.close', [$room->id, $poll->id]))->assertOk();
        $this->vote($late, $room, $poll, 0)->assertUnprocessable();

        $this->assertSame(1, $poll->votes()->count());
    }

    public function test_outsiders_and_hosts_cannot_vote(): void
    {
        [$host, , $room] = $this->classInProgress();
        $this->ask($host, $room)->assertCreated();
        $poll = LearningRoomPoll::firstOrFail();

        $this->vote(User::factory()->create(), $room, $poll, 0)->assertUnprocessable();
        $this->vote($host, $room, $poll, 0)->assertUnprocessable();
        $this->assertSame(0, $poll->votes()->count());
    }

    public function test_only_the_host_asks_and_closes_and_a_new_poll_closes_the_old_one(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->ask($learner, $room)->assertForbidden();

        $this->ask($host, $room)->assertCreated();
        $first = LearningRoomPoll::firstOrFail();
        $this->actingAs($learner)->postJson(route('studio.rooms.polls.close', [$room->id, $first->id]))->assertForbidden();

        $this->ask($host, $room, ['question' => 'Ready for the next topic?', 'options' => ['Yes', 'No'], 'correct_option' => null])->assertCreated();

        $this->assertNotNull($first->fresh()->closed_at);
        $polls = $this->pollsFor($learner, $room);
        $this->assertCount(2, $polls);
        $this->assertFalse($polls[1]['is_quiz']);
        $this->assertTrue($polls[1]['is_open']);
    }

    public function test_poll_input_is_validated(): void
    {
        [$host, , $room] = $this->classInProgress();

        $this->ask($host, $room, ['options' => ['Only one', '  ']])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->ask($host, $room, ['options' => array_fill(0, 7, 'x')])->assertUnprocessable()->assertJsonValidationErrors('options');
        $this->ask($host, $room, ['correct_option' => 5])->assertUnprocessable()->assertJsonValidationErrors('correct_option');
        $this->ask($host, $room, ['question' => '   '])->assertUnprocessable();

        $this->assertSame(0, LearningRoomPoll::count());
    }

    public function test_a_poll_of_another_room_is_not_found(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->ask($host, $room)->assertCreated();
        $poll = LearningRoomPoll::firstOrFail();

        $other = LearningRoom::create([
            'title' => 'Other', 'host_id' => $host->id, 'created_by' => $host->id,
            'status' => 'scheduled', 'access' => 'public', 'scheduled_at' => now()->addHour(), 'duration_minutes' => 60,
        ]);

        // The other room is not live, so "join" may refuse (403) before the room check (404).
        $this->assertContains($this->vote($learner, $other, $poll, 0)->status(), [403, 404]);
        $this->assertSame(0, $poll->votes()->count());
    }
}
