<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMember;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\User;
use App\Notifications\Learning\LiveSessionCancelled;
use App\Notifications\Learning\LiveSessionScheduled;
use App\Services\Learning\RoomService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Learning\Concerns\UsesLiveServer;
use Tests\TestCase;

/**
 * Teaching Studio — live room management: permission matrix, the room form
 * (validation, drafts, scheduling, host choice), lifecycle actions
 * (publish / start / end / cancel / announce), members and participants,
 * attendance with CSV export, recordings (chunked upload → publish as
 * lesson → delete), session records and room deletion.
 */
class StudioRoomsTest extends TestCase
{
    use RefreshDatabase;
    use UsesLiveServer;

    private const DISKS = ['private', 'public', 'local'];

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Like Storage::fake(), in a folder of our own so a parallel run cannot wipe it mid-upload.
        $this->diskRoot = storage_path('framework/testing/disks/studio-rooms-'.getmypid());
        foreach (self::DISKS as $disk) {
            $root = $this->diskRoot.'/'.$disk;
            (new Filesystem)->cleanDirectory($root);
            Storage::set($disk, Storage::createLocalDriver(array_merge(
                (array) config("filesystems.disks.{$disk}", []),
                ['driver' => 'local', 'root' => $root, 'throw' => false],
            )));
        }

        $this->useLiveServer();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    // --- Fixtures --------------------------------------------------------
    private function instructor(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['can_teach' => true], $attrs));
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'super_admin', 'permissions' => null]);
    }

    private function restrictedAdmin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => $permissions]);
    }

    private function category(string $name = 'Science'): LearningCategory
    {
        return LearningCategory::create(['name' => $name.' '.uniqid()]);
    }

    private function course(LearningCategory $category, ?User $instructor = null, array $attrs = []): LearningCourse
    {
        return LearningCourse::create(array_merge([
            'learning_category_id' => $category->id,
            'title' => 'Course '.uniqid(),
            'status' => 'published',
            'access' => 'open',
            'instructor_id' => $instructor?->id,
        ], $attrs));
    }

    private function room(User $host, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => 'Room '.uniqid(),
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
        ], $attrs));
    }

    private function liveRoom(User $host, array $attrs = []): LearningRoom
    {
        $room = $this->room($host, $attrs);
        app(RoomService::class)->start($room, $host);

        return $room->refresh();
    }

    /** Form fields for a room starting tomorrow at 10:00 (app timezone). */
    private function formData(array $overrides = []): array
    {
        $start = now((string) config('app.timezone'))->addDay()->setTime(10, 0);

        return array_merge([
            'title' => 'Weekly Q&A',
            'description' => 'Bring your questions.',
            'scheduled_date' => $start->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'duration_minutes' => 60,
            'access' => 'public',
            'chat_enabled' => '1',
            'questions_enabled' => '1',
            'allow_participant_media' => '0',
            'action' => 'draft',
        ], $overrides);
    }

    private function mp4Bytes(int $size = 3000): string
    {
        $head = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";
        $mdat = pack('N', $size - strlen($head)).'mdat';

        return str_pad($head.$mdat, $size, "\x00");
    }

    /** Full upload through the real chunked endpoints; returns the completed token. */
    private function uploadViaHttp(User $user, string $bytes, string $purpose = 'recording', string $name = 'session.mp4'): string
    {
        $token = $this->actingAs($user)
            ->postJson(route('studio.uploads.init'), ['purpose' => $purpose, 'filename' => $name, 'size' => strlen($bytes)])
            ->assertCreated()
            ->json('token');

        foreach (str_split($bytes, 1024) as $i => $part) {
            $this->actingAs($user)
                ->post(route('studio.uploads.chunk', $token), [
                    'index' => $i,
                    'chunk' => UploadedFile::fake()->createWithContent('blob', $part),
                ], ['Accept' => 'application/json'])
                ->assertOk();
        }

        $this->actingAs($user)->postJson(route('studio.uploads.complete', $token))->assertOk();

        return $token;
    }

    // --- Permission matrix -----------------------------------------------
    public function test_plain_member_is_forbidden_on_studio_room_routes(): void
    {
        $member = User::factory()->create();
        $room = $this->room($this->instructor());

        $this->actingAs($member)->get(route('studio.rooms.index'))->assertForbidden();
        $this->actingAs($member)->get(route('studio.rooms.create'))->assertForbidden();
        $this->actingAs($member)->post(route('studio.rooms.store'), $this->formData())->assertForbidden();
        $this->actingAs($member)->get(route('studio.rooms.show', $room))->assertForbidden();
        $this->actingAs($member)->get(route('studio.rooms.attendance', $room))->assertForbidden();
        $this->actingAs($member)->post(route('studio.rooms.start', $room))->assertForbidden();
        $this->actingAs($member)->delete(route('studio.rooms.destroy', $room), ['reason' => 'nope'])->assertForbidden();

        $this->assertSame('scheduled', $room->refresh()->status);

        // Lesson-only staff opening the rooms list are sent to the lessons instead.
        $this->actingAs($this->restrictedAdmin(['learning.view']))->get(route('studio.rooms.index'))
            ->assertRedirect(route('studio.videos.index'));
        $this->actingAs($this->restrictedAdmin(['learning.view']))->get(route('studio.rooms.show', $room))->assertForbidden();
    }

    public function test_instructor_sees_and_manages_only_their_own_rooms(): void
    {
        $alice = $this->instructor();
        $bob = $this->instructor();
        $mine = $this->room($alice, ['title' => 'Alice algebra room']);
        $theirs = $this->room($bob, ['title' => 'Bob biology room']);

        $this->actingAs($alice)->get(route('studio.rooms.index'))
            ->assertOk()
            ->assertSee('Alice algebra room')
            ->assertDontSee('Bob biology room')
            ->assertSee('Create room');

        $this->actingAs($alice)->get(route('studio.rooms.show', $mine))->assertOk()->assertSee('Start session now');
        $this->actingAs($alice)->get(route('studio.rooms.edit', $mine))->assertOk();
        $this->actingAs($alice)->get(route('studio.rooms.participants', $mine))->assertOk();
        $this->actingAs($alice)->get(route('studio.rooms.attendance', $mine))->assertOk();

        $this->actingAs($alice)->get(route('studio.rooms.show', $theirs))->assertForbidden();
        $this->actingAs($alice)->get(route('studio.rooms.edit', $theirs))->assertForbidden();
        $this->actingAs($alice)->put(route('studio.rooms.update', $theirs), $this->formData(['title' => 'Hijacked']))->assertForbidden();
        $this->actingAs($alice)->post(route('studio.rooms.start', $theirs))->assertForbidden();
        $this->actingAs($alice)->post(route('studio.rooms.cancel', $theirs))->assertForbidden();
        $this->actingAs($alice)->get(route('studio.rooms.attendance', $theirs))->assertForbidden();
        $this->actingAs($alice)->get(route('studio.rooms.participants', $theirs))->assertForbidden();
        $this->actingAs($alice)->delete(route('studio.rooms.destroy', $theirs), ['reason' => 'not mine'])->assertForbidden();

        $this->assertSame('Bob biology room', $theirs->refresh()->title);
        $this->assertSame('scheduled', $theirs->status);
    }

    public function test_rooms_view_admin_is_read_only_including_attendance(): void
    {
        $viewer = $this->restrictedAdmin(['rooms.view']);
        $host = $this->instructor();
        $room = $this->room($host, ['title' => 'Chemistry live']);
        $other = $this->room($this->instructor(), ['title' => 'Physics live']);

        $this->actingAs($viewer)->get(route('studio.rooms.index'))
            ->assertOk()
            ->assertSee('Chemistry live')
            ->assertSee('Physics live')
            ->assertDontSee('Create room');

        $this->actingAs($viewer)->get(route('studio.rooms.show', $room))->assertOk()
            ->assertSee('only its host or a room manager can change it')
            ->assertDontSee('Start session now');
        $this->actingAs($viewer)->get(route('studio.rooms.participants', $room))->assertOk();
        $this->actingAs($viewer)->get(route('studio.rooms.attendance', $room))->assertOk();

        $this->actingAs($viewer)->get(route('studio.rooms.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.store'), $this->formData())->assertForbidden();
        $this->actingAs($viewer)->get(route('studio.rooms.edit', $room))->assertForbidden();
        $this->actingAs($viewer)->put(route('studio.rooms.update', $room), $this->formData())->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.publish', $room))->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.start', $room))->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.cancel', $room))->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.members.store', $room), ['user_ids' => [$host->id]])->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.rooms.recordings.store', $room), ['upload_token' => str_repeat('a', 40)])->assertForbidden();
        $this->actingAs($viewer)->delete(route('studio.rooms.destroy', $other), ['reason' => 'read only'])->assertForbidden();

        $live = $this->liveRoom($host);
        $this->actingAs($viewer)->postJson(route('studio.rooms.end', $live))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('studio.rooms.announce', $live), ['body' => 'Hi'])->assertForbidden();

        // Attendance export works for read-only staff.
        $this->actingAs($viewer)->get(route('studio.rooms.attendance.export', $live))->assertOk();
    }

    public function test_rooms_manager_has_full_control_and_chooses_the_host(): void
    {
        $manager = $this->restrictedAdmin(['rooms.manage']);
        $teacher = $this->instructor(['name' => 'Teacher Tumaini']);
        $plain = User::factory()->create(['name' => 'Plain Pendo']);

        $this->actingAs($manager)->get(route('studio.rooms.create'))
            ->assertOk()
            ->assertSee('Teacher Tumaini')
            ->assertDontSee('Plain Pendo');

        $this->actingAs($manager)->post(route('studio.rooms.store'), $this->formData(['host_id' => $teacher->id, 'title' => 'Hosted by Tumaini']))
            ->assertRedirect();

        $room = LearningRoom::where('title', 'Hosted by Tumaini')->firstOrFail();
        $this->assertSame($teacher->id, (int) $room->host_id);
        $this->assertSame($manager->id, (int) $room->created_by);

        // A plain member cannot be host.
        $this->actingAs($manager)->post(route('studio.rooms.store'), $this->formData(['host_id' => $plain->id, 'title' => 'Bad host']))
            ->assertSessionHasErrors('host_id');

        // Someone else's room: full control.
        $theirs = $this->room($this->instructor());
        $this->actingAs($manager)->get(route('studio.rooms.edit', $theirs))->assertOk();
        $this->actingAs($manager)->put(route('studio.rooms.update', $theirs), $this->formData(['title' => 'Renamed by manager', 'action' => 'save']))
            ->assertRedirect(route('studio.rooms.show', $theirs));
        $this->assertSame('Renamed by manager', $theirs->refresh()->title);

        // An instructor cannot hand a room to someone else.
        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['host_id' => $manager->id, 'title' => 'Mine']))
            ->assertRedirect();
        $this->assertSame($teacher->id, (int) LearningRoom::where('title', 'Mine')->value('host_id'));
    }

    public function test_super_admin_can_do_everything(): void
    {
        $admin = $this->superAdmin();
        $room = $this->room($this->instructor(), ['status' => 'draft']);

        $this->actingAs($admin)->get(route('studio.rooms.index'))->assertOk()->assertSee($room->title);
        $this->actingAs($admin)->get(route('studio.rooms.show', $room))->assertOk();
        $this->actingAs($admin)->get(route('studio.rooms.edit', $room))->assertOk();
        $this->actingAs($admin)->post(route('studio.rooms.publish', $room), ['notify' => 0])->assertRedirect();
        $this->assertSame('scheduled', $room->refresh()->status);
        $this->actingAs($admin)->delete(route('studio.rooms.destroy', $room), ['reason' => 'cleanup'])->assertRedirect(route('studio.rooms.index'));
        $this->assertSoftDeleted($room);
    }

    // --- Form ----------------------------------------------------------
    public function test_create_validation_rules(): void
    {
        $teacher = $this->instructor();
        $science = $this->category('Science');
        $arts = $this->category('Arts');
        $myCourse = $this->course($science, $teacher);
        $otherTopic = LearningTopic::create(['learning_course_id' => $this->course($science, $teacher)->id, 'title' => 'Elsewhere']);
        $foreignCourse = $this->course($science, $this->instructor());
        $yesterday = now((string) config('app.timezone'))->subDay()->format('Y-m-d');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['scheduled_date' => $yesterday, 'action' => 'schedule']))
            ->assertSessionHasErrors('scheduled_date');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['scheduled_date' => '', 'scheduled_time' => '', 'action' => 'schedule']))
            ->assertSessionHasErrors('scheduled_date');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['access' => 'private']))
            ->assertSessionHasErrors('user_ids');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['access' => 'private', 'user_ids' => [$teacher->id]]))
            ->assertSessionHasErrors('user_ids'); // the host alone does not count

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData([
            'learning_category_id' => $arts->id, 'learning_course_id' => $myCourse->id,
        ]))->assertSessionHasErrors('learning_course_id');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData([
            'learning_category_id' => $science->id, 'learning_course_id' => $myCourse->id, 'learning_topic_id' => $otherTopic->id,
        ]))->assertSessionHasErrors('learning_topic_id');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['access' => 'course']))
            ->assertSessionHasErrors('learning_course_id');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['access' => 'category']))
            ->assertSessionHasErrors('learning_category_id');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['duration_minutes' => 2]))
            ->assertSessionHasErrors('duration_minutes');

        // Product rule: instructors link rooms only to courses they teach.
        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData([
            'learning_category_id' => $science->id, 'learning_course_id' => $foreignCourse->id,
        ]))->assertSessionHasErrors('learning_course_id');

        $this->assertSame(0, LearningRoom::count());
    }

    public function test_create_as_draft_and_as_scheduled_with_members(): void
    {
        $teacher = $this->instructor();
        $learner = User::factory()->create();
        $invitee = User::factory()->create();
        $science = $this->category();
        $course = $this->course($science, $teacher);

        $this->actingAs($teacher)->get(route('studio.rooms.create'))->assertOk()->assertSee('Save as draft')->assertSee('Schedule');

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData(['title' => 'Draft one']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $draft = LearningRoom::where('title', 'Draft one')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame($teacher->id, (int) $draft->host_id);
        $this->assertFalse($draft->allow_participant_media);
        $this->assertTrue($draft->chat_enabled);
        $this->assertSame('10:00', $draft->scheduled_at->copy()->setTimezone(config('app.timezone'))->format('H:i'));
        Notification::assertNothingSent();

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData([
            'title' => 'Scheduled public',
            'action' => 'schedule',
            'notify' => '1',
            'learning_category_id' => $science->id,
            'learning_course_id' => $course->id,
        ]))->assertRedirect();

        $scheduled = LearningRoom::where('title', 'Scheduled public')->firstOrFail();
        $this->assertSame('scheduled', $scheduled->status);
        $this->assertSame($course->id, (int) $scheduled->learning_course_id);
        $this->assertSame($science->id, (int) $scheduled->learning_category_id);
        Notification::assertSentTo($learner, LiveSessionScheduled::class);

        $this->actingAs($teacher)->post(route('studio.rooms.store'), $this->formData([
            'title' => 'Private circle',
            'access' => 'private',
            'user_ids' => [$invitee->id, $teacher->id],
            'members_present' => '1',
        ]))->assertRedirect();

        $private = LearningRoom::where('title', 'Private circle')->firstOrFail();
        $this->assertSame([$invitee->id], $private->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        $this->actingAs($teacher)->get(route('studio.rooms.show', $private))->assertOk()->assertSee('1 invited');
        $this->actingAs($teacher)->get(route('studio.rooms.edit', $private))->assertOk()->assertSee('Private circle');
    }

    public function test_live_room_edit_only_changes_text_and_toggles(): void
    {
        $teacher = $this->instructor();
        $room = $this->liveRoom($teacher, ['title' => 'Live now']);
        $originalStart = $room->scheduled_at;

        $this->actingAs($teacher)->get(route('studio.rooms.edit', $room))->assertOk()->assertSee('This room is live right now.');

        $this->actingAs($teacher)->put(route('studio.rooms.update', $room), [
            'title' => 'Live renamed',
            'description' => 'Updated',
            'chat_enabled' => '0',
            'questions_enabled' => '1',
            'allow_participant_media' => '1',
            'access' => 'private',
            'scheduled_date' => '2020-01-01',
            'scheduled_time' => '08:00',
            'duration_minutes' => 5,
        ])->assertRedirect(route('studio.rooms.show', $room));

        $room->refresh();
        $this->assertSame('Live renamed', $room->title);
        $this->assertFalse($room->chat_enabled);
        $this->assertSame('public', $room->access);
        $this->assertSame(60, $room->duration_minutes);
        $this->assertTrue($originalStart->equalTo($room->scheduled_at));
        $this->assertSame('live', $room->status);
    }

    public function test_edit_draft_and_schedule_and_publish(): void
    {
        $teacher = $this->instructor();
        $learner = User::factory()->create();
        $draft = $this->room($teacher, ['status' => 'draft', 'scheduled_at' => null]);

        // Publishing a draft without a date fails with a friendly flash.
        $this->actingAs($teacher)->from(route('studio.rooms.show', $draft))
            ->post(route('studio.rooms.publish', $draft))
            ->assertRedirect(route('studio.rooms.show', $draft))
            ->assertSessionHas('error');
        $this->assertSame('draft', $draft->refresh()->status);

        // Save & schedule from the edit form.
        $this->actingAs($teacher)->put(route('studio.rooms.update', $draft), $this->formData(['title' => 'Now scheduled', 'action' => 'schedule', 'notify' => '1']))
            ->assertRedirect(route('studio.rooms.show', $draft));
        $draft->refresh();
        $this->assertSame('scheduled', $draft->status);
        $this->assertSame('Now scheduled', $draft->title);
        Notification::assertSentTo($learner, LiveSessionScheduled::class);

        // Publish button for a draft with a date.
        $other = $this->room($teacher, ['status' => 'draft']);
        $this->actingAs($teacher)->post(route('studio.rooms.publish', $other), ['notify' => '1'])
            ->assertRedirect(route('studio.rooms.show', $other))
            ->assertSessionHas('success');
        $this->assertSame('scheduled', $other->refresh()->status);

        // Moving the start into the past is refused.
        $this->actingAs($teacher)->put(route('studio.rooms.update', $other), $this->formData([
            'scheduled_date' => now((string) config('app.timezone'))->subDays(2)->format('Y-m-d'),
            'action' => 'save',
        ]))->assertSessionHasErrors('scheduled_date');
    }

    // --- Lifecycle ---------------------------------------------------------
    public function test_start_redirects_to_the_live_room_and_opens_a_session(): void
    {
        $teacher = $this->instructor();
        $room = $this->room($teacher);

        $this->actingAs($teacher)->post(route('studio.rooms.start', $room))
            ->assertRedirect(route('learn.rooms.live', $room));

        $room->refresh();
        $this->assertSame('live', $room->status);
        $this->assertSame(1, $room->sessions()->whereNull('ended_at')->count());

        // Idempotent while live; JSON callers get the live URL.
        $this->actingAs($teacher)->postJson(route('studio.rooms.start', $room))
            ->assertOk()
            ->assertJson(['status' => 'live', 'live_url' => route('learn.rooms.live', $room)]);
        $this->assertSame(1, $room->sessions()->count());

        // Index shows the live room with Open / End.
        $this->actingAs($teacher)->get(route('studio.rooms.index', ['tab' => 'live']))
            ->assertOk()->assertSee($room->title)->assertSee('LIVE');

        // A cancelled room cannot start.
        $cancelled = $this->room($teacher, ['status' => 'cancelled']);
        $this->actingAs($teacher)->from(route('studio.rooms.show', $cancelled))
            ->post(route('studio.rooms.start', $cancelled))
            ->assertRedirect(route('studio.rooms.show', $cancelled))
            ->assertSessionHas('error');
        $this->actingAs($teacher)->postJson(route('studio.rooms.start', $cancelled))->assertStatus(422);
        $this->assertSame('cancelled', $cancelled->refresh()->status);
    }

    public function test_end_works_as_json_and_as_redirect(): void
    {
        $teacher = $this->instructor();

        $a = $this->liveRoom($teacher);
        $this->actingAs($teacher)->postJson(route('studio.rooms.end', $a))->assertOk()->assertJson(['status' => 'completed']);
        $this->assertSame('completed', $a->refresh()->status);
        $this->assertNotNull($a->sessions()->first()->ended_at);

        // Ending again: 422 for JSON.
        $this->actingAs($teacher)->postJson(route('studio.rooms.end', $a))->assertStatus(422);

        $b = $this->liveRoom($teacher);
        $this->actingAs($teacher)->post(route('studio.rooms.end', $b))
            ->assertRedirect(route('studio.rooms.show', $b))
            ->assertSessionHas('success');
        $this->assertSame('completed', $b->refresh()->status);
    }

    public function test_cancel_with_a_reason(): void
    {
        $teacher = $this->instructor();
        $learner = User::factory()->create();
        $room = $this->room($teacher);

        $this->actingAs($teacher)->post(route('studio.rooms.cancel', $room), ['reason' => str_repeat('x', 501)])
            ->assertSessionHasErrors('reason');

        $this->actingAs($teacher)->post(route('studio.rooms.cancel', $room), ['reason' => 'Instructor is unwell'])
            ->assertRedirect(route('studio.rooms.show', $room));

        $room->refresh();
        $this->assertSame('cancelled', $room->status);
        $this->assertSame('Instructor is unwell', $room->cancel_reason);
        Notification::assertSentTo($learner, LiveSessionCancelled::class);

        $this->actingAs($teacher)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Instructor is unwell')->assertSee('Schedule again');
    }

    public function test_announcements_only_while_live(): void
    {
        $teacher = $this->instructor();
        $room = $this->room($teacher);

        $this->actingAs($teacher)->postJson(route('studio.rooms.announce', $room), ['body' => 'Too early'])
            ->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($teacher)->postJson(route('studio.rooms.announce', $room), ['body' => ''])->assertStatus(422);

        app(RoomService::class)->start($room, $teacher);
        $room->refresh();

        $this->actingAs($teacher)->postJson(route('studio.rooms.announce', $room), ['body' => 'Starting in 2 minutes'])
            ->assertCreated()
            ->assertJsonPath('message.type', 'announcement')
            ->assertJsonPath('message.body', 'Starting in 2 minutes');

        $this->actingAs($teacher)->post(route('studio.rooms.announce', $room), ['body' => 'Form post'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, LearningRoomMessage::where('learning_room_id', $room->id)->where('type', 'announcement')->count());

        $this->actingAs($teacher)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Post announcement');
    }

    // --- Members & participants -------------------------------------------
    public function test_members_can_be_added_and_removed(): void
    {
        $teacher = $this->instructor();
        $a = User::factory()->create(['name' => 'Asha Member']);
        $b = User::factory()->create(['name' => 'Baraka Member']);
        $inactive = User::factory()->create(['status' => 'suspended']);
        $room = $this->room($teacher, ['access' => 'private']);

        $this->actingAs($teacher)->post(route('studio.rooms.members.store', $room), ['user_ids' => [$a->id, $b->id, $teacher->id]])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, $room->members()->count());
        $this->assertSame($teacher->id, (int) LearningRoomMember::where('user_id', $a->id)->value('added_by'));

        // Adding again is a no-op; inactive accounts are refused.
        $this->actingAs($teacher)->post(route('studio.rooms.members.store', $room), ['user_ids' => [$a->id]])->assertSessionHas('success');
        $this->assertSame(2, $room->members()->count());
        $this->actingAs($teacher)->post(route('studio.rooms.members.store', $room), ['user_ids' => [$inactive->id]])->assertSessionHasErrors('user_ids.0');

        $this->actingAs($teacher)->get(route('studio.rooms.participants', $room))->assertOk()->assertSee('Asha Member')->assertSee('Baraka Member');

        $member = LearningRoomMember::where('learning_room_id', $room->id)->where('user_id', $a->id)->firstOrFail();
        $this->actingAs($teacher)->delete(route('studio.rooms.members.destroy', [$room, $member]))->assertRedirect()->assertSessionHas('success');
        $this->assertSame([$b->id], $room->members()->pluck('user_id')->map(fn ($id) => (int) $id)->all());

        // A member of another room is not reachable through this room (scoped binding).
        $otherRoom = $this->room($teacher, ['access' => 'private']);
        $foreign = LearningRoomMember::create(['learning_room_id' => $otherRoom->id, 'user_id' => $a->id]);
        $this->actingAs($teacher)->delete(route('studio.rooms.members.destroy', [$room, $foreign]))->assertNotFound();

        // Public rooms have no invitation list.
        $public = $this->room($teacher);
        $this->actingAs($teacher)->post(route('studio.rooms.members.store', $public), ['user_ids' => [$a->id]])->assertSessionHasErrors('user_ids');
    }

    public function test_remove_participant_disconnects_them_on_the_video_server(): void
    {
        $teacher = $this->instructor();
        $learner = User::factory()->create(['name' => 'Neema Learner']);
        $room = $this->liveRoom($teacher);
        $service = app(RoomService::class);

        $service->join($room, $learner);
        $service->presence($room, $learner);

        $this->actingAs($teacher)->get(route('studio.rooms.participants', $room))->assertOk()->assertSee('Neema Learner')->assertSee('1 present');

        $this->actingAs($teacher)->postJson(route('studio.rooms.participants.remove', [$room, $learner]))
            ->assertOk()
            ->assertExactJson(['removed' => true, 'user_id' => $learner->id]);

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => str_ends_with($request->url(), '/twirp/livekit.RoomService/RemoveParticipant')
            && $request['room'] === $room->provider_room && $request['identity'] === 'user-'.$learner->id);

        $attendance = LearningRoomAttendance::where('learning_room_id', $room->id)->where('user_id', $learner->id)->firstOrFail();
        $this->assertNotNull($attendance->removed_at);
        $this->assertSame($teacher->id, (int) $attendance->removed_by);

        // The host cannot be removed; non-live rooms refuse.
        $this->actingAs($this->restrictedAdmin(['rooms.manage']))
            ->postJson(route('studio.rooms.participants.remove', [$room, $teacher]))->assertStatus(422);

        $idle = $this->room($teacher);
        $this->actingAs($teacher)->from(route('studio.rooms.participants', $idle))
            ->post(route('studio.rooms.participants.remove', [$idle, $learner]))
            ->assertRedirect(route('studio.rooms.participants', $idle))
            ->assertSessionHas('error');
    }

    // --- Attendance --------------------------------------------------------
    public function test_attendance_page_and_csv_export_are_injection_safe(): void
    {
        $teacher = $this->instructor();
        $room = $this->room($teacher, ['title' => 'Maths revision', 'status' => 'completed']);
        $started = Carbon::create(2026, 9, 1, 9, 0, 0, config('app.timezone'));
        $session = LearningRoomSession::create([
            'learning_room_id' => $room->id,
            'started_by' => $teacher->id,
            'started_at' => $started,
            'ended_at' => $started->copy()->addHour(),
            'peak_participants' => 3,
        ]);

        $evil = [
            '=HYPERLINK("http://evil.test","x")',
            '+1+2',
            '-3',
            '@SUM(A1)',
        ];
        foreach ($evil as $i => $name) {
            $user = User::factory()->create(['name' => $name]);
            LearningRoomAttendance::create([
                'learning_room_session_id' => $session->id,
                'learning_room_id' => $room->id,
                'user_id' => $user->id,
                'role' => 'participant',
                'first_joined_at' => $started->copy()->addMinutes($i),
                'last_seen_at' => $started->copy()->addMinutes(50),
                'left_at' => $started->copy()->addMinutes(50),
                'total_seconds' => 3725,
                'join_count' => 2,
            ]);
        }

        $this->actingAs($teacher)->get(route('studio.rooms.attendance', $room))
            ->assertOk()
            ->assertSee('1:02:05')
            ->assertSee('@SUM(A1)')
            ->assertSee('Export CSV');

        // Session of another room is ignored (falls back to this room's latest).
        $this->actingAs($teacher)->get(route('studio.rooms.attendance', ['room' => $room, 'session' => 999999]))->assertOk();

        $response = $this->actingAs($teacher)->get(route('studio.rooms.attendance.export', ['room' => $room, 'session' => $session->id]));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString($room->slug.'-2026-09-01.csv', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil.test"",""x"")"', $csv);
        $this->assertStringContainsString("'+1+2", $csv);
        $this->assertStringContainsString("'-3", $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
        $this->assertStringContainsString('1:02:05', $csv);

        $rows = array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame('Name', $rows[0][0]);
        $this->assertCount(1 + count($evil), $rows);
        foreach (array_slice($rows, 1) as $row) {
            $this->assertStringStartsWith("'", $row[0]);
        }
    }

    // --- Recordings ----------------------------------------------------------
    public function test_recording_upload_publish_as_lesson_and_delete(): void
    {
        $teacher = $this->instructor();
        $category = $this->category();
        $room = $this->room($teacher, ['learning_category_id' => $category->id, 'status' => 'completed']);
        $session = LearningRoomSession::create([
            'learning_room_id' => $room->id,
            'started_by' => $teacher->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $foreignSession = LearningRoomSession::create([
            'learning_room_id' => $this->room($teacher)->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);

        $this->actingAs($teacher)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Upload a recording');

        // A session of another room is refused.
        $token = $this->uploadViaHttp($teacher, $this->mp4Bytes());
        $this->actingAs($teacher)->post(route('studio.rooms.recordings.store', $room), [
            'upload_token' => $token,
            'learning_room_session_id' => $foreignSession->id,
        ])->assertSessionHasErrors('learning_room_session_id');

        $this->actingAs($teacher)->post(route('studio.rooms.recordings.store', $room), [
            'upload_token' => $token,
            'learning_room_session_id' => $session->id,
            'duration_seconds' => 125,
        ])->assertRedirect(route('studio.rooms.show', $room).'#recordings');

        $recording = LearningRoomRecording::where('learning_room_id', $room->id)->firstOrFail();
        $this->assertSame('upload', $recording->source);
        $this->assertSame('ready', $recording->status);
        $this->assertSame($session->id, (int) $recording->learning_room_session_id);
        $this->assertSame(125, $recording->duration_seconds);
        $this->assertFalse($recording->is_shared);
        $this->assertStringStartsWith('learning/recordings/', $recording->path);
        Storage::disk($recording->disk)->assertExists($recording->path);

        // Using the same token twice fails.
        $this->actingAs($teacher)->post(route('studio.rooms.recordings.store', $room), ['upload_token' => $token])
            ->assertSessionHasErrors('upload_token');

        // Share toggle (form + JSON).
        $this->actingAs($teacher)->put(route('studio.rooms.recordings.update', [$room, $recording]), ['is_shared' => 1])->assertRedirect();
        $this->assertTrue($recording->refresh()->is_shared);
        $this->actingAs($teacher)->putJson(route('studio.rooms.recordings.update', [$room, $recording]), ['is_shared' => false])
            ->assertOk()->assertJson(['is_shared' => false]);

        // Publish as lesson: the file is MOVED to the lesson (no copy).
        $path = $recording->path;
        $filesBefore = count(Storage::disk('private')->allFiles());

        $response = $this->actingAs($teacher)->post(route('studio.rooms.recordings.publish', [$room, $recording]));
        $video = LearningVideo::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('studio.videos.edit', $video));

        $recording->refresh();
        $this->assertNull($recording->path);
        $this->assertSame($video->id, (int) $recording->learning_video_id);
        $this->assertSame($path, $video->path);
        $this->assertSame('draft', $video->status);
        $this->assertSame($category->id, (int) $video->learning_category_id);
        $this->assertSame($filesBefore, count(Storage::disk('private')->allFiles()));
        Storage::disk('private')->assertExists($path);

        // Publishing twice is refused.
        $this->actingAs($teacher)->post(route('studio.rooms.recordings.publish', [$room, $recording]))->assertSessionHas('error');

        // Delete a second recording: row and file gone; the lesson's file is untouched.
        $token2 = $this->uploadViaHttp($teacher, $this->mp4Bytes(2500), name: 'second.mp4');
        $this->actingAs($teacher)->post(route('studio.rooms.recordings.store', $room), ['upload_token' => $token2])->assertRedirect();
        $second = LearningRoomRecording::where('learning_room_id', $room->id)->whereNotNull('path')->firstOrFail();

        $this->actingAs($teacher)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('Published as lesson')->assertSee('second.mp4');

        $this->actingAs($teacher)->delete(route('studio.rooms.recordings.destroy', [$room, $second]), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($teacher)->delete(route('studio.rooms.recordings.destroy', [$room, $second]), ['reason' => 'Duplicate upload'])
            ->assertRedirect(route('studio.rooms.show', $room).'#recordings');

        $this->assertDatabaseMissing('learning_room_recordings', ['id' => $second->id]);
        Storage::disk('private')->assertMissing($second->path);
        Storage::disk('private')->assertExists($path);

        // A recording of another room cannot be reached through this room.
        $elsewhere = LearningRoomRecording::create(['learning_room_id' => $foreignSession->learning_room_id, 'source' => 'upload']);
        $this->actingAs($teacher)->delete(route('studio.rooms.recordings.destroy', [$room, $elsewhere]), ['reason' => 'nope nope'])->assertNotFound();
    }

    // --- Sessions & deletion ----------------------------------------------
    public function test_session_records_can_be_deleted_once_ended(): void
    {
        $teacher = $this->instructor();
        $room = $this->room($teacher, ['status' => 'completed']);
        $ended = LearningRoomSession::create([
            'learning_room_id' => $room->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);
        LearningRoomAttendance::create([
            'learning_room_session_id' => $ended->id,
            'learning_room_id' => $room->id,
            'user_id' => User::factory()->create()->id,
            'total_seconds' => 60,
        ]);

        $this->actingAs($teacher)->get(route('studio.rooms.show', $room))->assertOk()->assertSee('1 attendance record');

        $this->actingAs($teacher)->delete(route('studio.rooms.sessions.destroy', [$room, $ended]), ['reason' => 'Test session'])
            ->assertRedirect(route('studio.rooms.show', $room).'#sessions');
        $this->assertDatabaseMissing('learning_room_sessions', ['id' => $ended->id]);
        $this->assertSame(0, LearningRoomAttendance::where('learning_room_session_id', $ended->id)->count());

        // A running session cannot be deleted.
        $live = $this->liveRoom($teacher);
        $running = $live->sessions()->firstOrFail();
        $this->actingAs($teacher)->from(route('studio.rooms.show', $live))
            ->delete(route('studio.rooms.sessions.destroy', [$live, $running]), ['reason' => 'Too soon'])
            ->assertRedirect(route('studio.rooms.show', $live))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('learning_room_sessions', ['id' => $running->id]);
    }

    public function test_room_delete_is_blocked_while_live_and_soft_deletes_otherwise(): void
    {
        $teacher = $this->instructor();
        $live = $this->liveRoom($teacher);

        $this->actingAs($teacher)->get(route('studio.rooms.index'))->assertOk()->assertSee('End the live session before deleting the room');

        $this->actingAs($teacher)->from(route('studio.rooms.show', $live))
            ->delete(route('studio.rooms.destroy', $live), ['reason' => 'Oops'])
            ->assertRedirect(route('studio.rooms.show', $live))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($live);

        $this->actingAs($teacher)->delete(route('studio.rooms.destroy', $live), ['reason' => ''])->assertSessionHasErrors('reason');

        $done = $this->room($teacher, ['status' => 'completed']);
        $this->actingAs($teacher)->delete(route('studio.rooms.destroy', $done), ['reason' => 'No longer needed'])
            ->assertRedirect(route('studio.rooms.index'))
            ->assertSessionHas('success');
        $this->assertSoftDeleted($done);
        $this->actingAs($teacher)->get(route('studio.rooms.show', $done))->assertNotFound();
    }

    public function test_index_tabs_counts_search_and_participants(): void
    {
        $teacher = $this->instructor();
        $this->room($teacher, ['title' => 'Draft algebra', 'status' => 'draft']);
        $this->room($teacher, ['title' => 'Upcoming geometry']);
        $this->room($teacher, ['title' => 'Old calculus', 'status' => 'cancelled']);
        $private = $this->room($teacher, ['title' => 'Private stats', 'access' => 'private']);
        LearningRoomMember::create(['learning_room_id' => $private->id, 'user_id' => User::factory()->create()->id]);
        $live = $this->liveRoom($teacher, ['title' => 'Live trigonometry']);
        app(RoomService::class)->join($live, User::factory()->create());

        $this->actingAs($teacher)->get(route('studio.rooms.index'))
            ->assertOk()
            ->assertSeeInOrder(['Live trigonometry', 'Upcoming geometry'])
            ->assertSee('1 invited')
            ->assertSee('present now');

        $this->actingAs($teacher)->get(route('studio.rooms.index', ['tab' => 'drafts']))
            ->assertOk()->assertSee('Draft algebra')->assertDontSee('Upcoming geometry');

        $this->actingAs($teacher)->get(route('studio.rooms.index', ['tab' => 'cancelled']))
            ->assertOk()->assertSee('Old calculus')->assertDontSee('Draft algebra');

        $this->actingAs($teacher)->get(route('studio.rooms.index', ['q' => 'geometry']))
            ->assertOk()->assertSee('Upcoming geometry')->assertDontSee('Draft algebra');

        $this->actingAs($teacher)->get(route('studio.rooms.index', ['tab' => 'completed']))
            ->assertOk()->assertSee('No rooms here');

        $this->actingAs($this->instructor())->get(route('studio.rooms.index'))
            ->assertOk()->assertSee('No live rooms yet');
    }
}
