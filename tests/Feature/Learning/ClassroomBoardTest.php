<?php

namespace Tests\Feature\Learning;

use App\Models\LearningRoom;
use App\Models\LearningRoomBoardStroke;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/** The live classroom whiteboard: host switches, who may draw, stroke validation, undo / clear, late joiners. */
class ClassroomBoardTest extends TestCase
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
            'title' => 'Maths live',
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

    private function switchBoard(User $user, LearningRoom $room, array $switches)
    {
        return $this->actingAs($user)->postJson(route('studio.rooms.board', $room->id), $switches);
    }

    private function draw(User $user, LearningRoom $room, array $overrides = [])
    {
        return $this->actingAs($user)->postJson(route('learn.rooms.board.strokes.store', $room), $overrides + [
            'uid' => 'stroke-'.bin2hex(random_bytes(4)),
            'c' => '#2563eb',
            'w' => 4,
            'p' => [100, 200, 300, 400, 500, 600],
        ]);
    }

    private function board(User $viewer, LearningRoom $room, int $after = 0): array
    {
        return $this->actingAs($viewer)->getJson(route('learn.rooms.board', [$room, 'after' => $after]))->assertOk()->json();
    }

    public function test_the_host_opens_the_board_and_everyone_sees_its_state_in_the_feed(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->switchBoard($learner, $room, ['active' => true])->assertForbidden();
        $this->switchBoard($host, $room, ['active' => true])->assertOk()->assertJson(['active' => true, 'all_can_draw' => false]);

        $this->actingAs($learner)->getJson(route('learn.rooms.feed', $room))->assertOk()
            ->assertJsonPath('board.active', true)
            ->assertJsonPath('board.version', 0);
    }

    public function test_only_the_host_draws_until_everyone_is_allowed(): void
    {
        [$host, $learner, $room] = $this->classInProgress();

        $this->draw($host, $room)->assertUnprocessable(); // board closed
        $this->switchBoard($host, $room, ['active' => true])->assertOk();

        $this->draw($host, $room)->assertCreated();
        $this->draw($learner, $room)->assertUnprocessable();

        $this->switchBoard($host, $room, ['all_can_draw' => true])->assertOk();
        $this->draw($learner, $room)->assertCreated();
        $this->draw(User::factory()->create(), $room)->assertUnprocessable(); // not in the class

        $this->assertSame(2, LearningRoomBoardStroke::count());
    }

    public function test_strokes_are_validated(): void
    {
        [$host, , $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true])->assertOk();

        $this->draw($host, $room, ['c' => '#123456'])->assertUnprocessable();
        $this->draw($host, $room, ['c' => 'red; background:url(x)'])->assertUnprocessable();
        $this->draw($host, $room, ['w' => 500])->assertUnprocessable();
        $this->draw($host, $room, ['p' => [1, 2, 3]])->assertUnprocessable();
        $this->draw($host, $room, ['p' => [1, 20000]])->assertUnprocessable();
        $this->draw($host, $room, ['p' => ['1', '2']])->assertUnprocessable();
        $this->draw($host, $room, ['p' => array_fill(0, 3002, 5)])->assertUnprocessable();
        $this->draw($host, $room, ['uid' => '../../x'])->assertUnprocessable();

        $this->assertSame(0, LearningRoomBoardStroke::count());
    }

    public function test_a_retried_stroke_is_stored_once_and_late_joiners_page_through_the_board(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true])->assertOk();

        $this->draw($host, $room, ['uid' => 'same-stroke-1'])->assertCreated();
        $this->draw($host, $room, ['uid' => 'same-stroke-1'])->assertCreated();
        $this->draw($host, $room, ['c' => '#ffffff', 'w' => 40])->assertCreated();

        $board = $this->board($learner, $room);
        $this->assertCount(2, $board['strokes']);
        $this->assertSame('same-stroke-1', $board['strokes'][0]['uid']);
        $this->assertSame([100, 200, 300, 400, 500, 600], $board['strokes'][0]['p']);
        $this->assertFalse($board['more']);

        $this->assertCount(1, $this->board($learner, $room, $board['strokes'][0]['id'])['strokes']);
    }

    public function test_shapes_keep_their_sharp_corner_flag(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true])->assertOk();

        $this->draw($host, $room, ['uid' => 'box-stroke-1', 's' => 1, 'p' => [100, 100, 900, 100, 900, 500, 100, 500, 100, 100]])->assertCreated();
        $this->draw($host, $room, ['uid' => 'pen-stroke-1'])->assertCreated();

        [$box, $pen] = $this->board($learner, $room)['strokes'];
        $this->assertSame(1, $box['s']);
        $this->assertArrayNotHasKey('s', $pen);
    }

    public function test_text_highlighter_and_filled_shapes(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true])->assertOk();

        $this->draw($host, $room, ['uid' => 'text-label-1', 'w' => 34, 'p' => [500, 500], 't' => "  <b>Photo</b>synthesis \n "])->assertCreated();
        $this->draw($host, $room, ['uid' => 'marker-line1', 'w' => 18, 'h' => 1])->assertCreated();
        $this->draw($host, $room, ['uid' => 'filled-box-1', 's' => 1, 'f' => 1, 'p' => [100, 100, 900, 100, 900, 500, 100, 100]])->assertCreated();

        $this->draw($host, $room, ['t' => '', 'p' => [1, 2]])->assertUnprocessable();
        $this->draw($host, $room, ['t' => str_repeat('x', 201), 'p' => [1, 2]])->assertUnprocessable();
        $this->draw($host, $room, ['t' => 'two points', 'p' => [1, 2, 3, 4]])->assertUnprocessable();

        [$text, $marker, $box] = $this->board($learner, $room)['strokes'];
        $this->assertSame('Photosynthesis', $text['t']);
        $this->assertSame(1, $marker['h']);
        $this->assertSame([1, 1], [$box['s'], $box['f']]);
    }

    public function test_undo_is_for_your_own_strokes_and_clear_is_for_the_host(): void
    {
        [$host, $learner, $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true, 'all_can_draw' => true])->assertOk();
        $this->draw($host, $room)->assertCreated();
        $this->draw($learner, $room)->assertCreated();
        [$hostStroke, $learnerStroke] = LearningRoomBoardStroke::orderBy('id')->get()->all();

        $this->actingAs($learner)->deleteJson(route('learn.rooms.board.strokes.destroy', [$room, $hostStroke]))->assertUnprocessable();
        $this->actingAs($learner)->deleteJson(route('learn.rooms.board.strokes.destroy', [$room, $learnerStroke]))
            ->assertOk()->assertJson(['version' => 1]);

        $this->switchBoard($learner, $room, ['clear' => true])->assertForbidden();
        $this->switchBoard($host, $room, ['clear' => true])->assertOk()->assertJson(['version' => 2]);
        $this->assertSame(0, LearningRoomBoardStroke::count());
    }

    public function test_a_new_session_starts_with_a_clean_closed_board(): void
    {
        [$host, , $room] = $this->classInProgress();
        $this->switchBoard($host, $room, ['active' => true])->assertOk();
        $this->draw($host, $room)->assertCreated();

        app(RoomService::class)->end($room, $host);
        app(RoomService::class)->start($room->refresh(), $host);

        $this->assertSame(['active' => false, 'all_can_draw' => false, 'version' => 0],
            $this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->json('board'));
        $this->assertSame([], $this->board($host, $room)['strokes']);
    }
}
