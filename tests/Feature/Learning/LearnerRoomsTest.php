<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomMessage;
use App\Models\LearningRoomRecording;
use App\Models\User;
use App\Services\Learning\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * V3 — learner live rooms & the live classroom: listing tabs and visibility,
 * room page, calendar export, classroom page config, join / presence / leave /
 * feed endpoints and in-room messages.
 */
class LearnerRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        config([
            'learning.live.provider' => 'jitsi',
            'learning.live.jitsi.domain' => 'meet.jit.si',
            'learning.live.jitsi.app_id' => null,
            'learning.live.jitsi.app_secret' => null,
        ]);
    }

    // --- Fixtures --------------------------------------------------------
    private function member(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function instructor(): User
    {
        return User::factory()->create(['can_teach' => true]);
    }

    private function category(string $name = 'Science'): LearningCategory
    {
        return LearningCategory::create(['name' => $name]);
    }

    private function course(LearningCategory $category, array $attrs = []): LearningCourse
    {
        return LearningCourse::create(array_merge([
            'learning_category_id' => $category->id,
            'title' => 'Course '.uniqid(),
            'status' => 'published',
            'access' => 'enrolled',
            'published_at' => now(),
        ], $attrs));
    }

    private function room(User $host, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => 'Room '.uniqid(),
            'host_id' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
        ], $attrs));
    }

    private function enroll(User $user, LearningCourse $course): void
    {
        LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $course->id, 'enrolled_at' => now()]);
    }

    private function service(): RoomService
    {
        return app(RoomService::class);
    }

    /** A scheduled room started by its host (so it has an open session). */
    private function liveRoom(User $host, array $attrs = []): LearningRoom
    {
        $room = $this->room($host, $attrs);
        $this->service()->start($room, $host);

        return $room->fresh();
    }

    // --- Index -------------------------------------------------------------
    public function test_index_tabs_follow_visibility_for_each_kind_of_user(): void
    {
        $host = $this->instructor();
        $other = $this->instructor();
        $learner = $this->member();
        $category = $this->category('Mathematics');
        $enrolledCourse = $this->course($category);
        $otherCourse = $this->course($this->category('History'));
        $this->enroll($learner, $enrolledCourse);

        $live = $this->room($host, ['title' => 'Public live class', 'status' => 'live', 'started_at' => now()]);
        $upcoming = $this->room($host, ['title' => 'Public upcoming class']);
        $completed = $this->room($host, ['title' => 'Public finished class', 'status' => 'completed', 'scheduled_at' => now()->subDay()]);
        $privateMember = $this->room($host, ['title' => 'Private with membership', 'access' => 'private']);
        $privateMember->members()->create(['user_id' => $learner->id]);
        $privateOther = $this->room($other, ['title' => 'Private without membership', 'access' => 'private']);
        $courseRoom = $this->room($host, ['title' => 'Enrolled course class', 'access' => 'course', 'learning_course_id' => $enrolledCourse->id, 'learning_category_id' => $category->id]);
        $categoryRoom = $this->room($host, ['title' => 'Category learners class', 'access' => 'category', 'learning_category_id' => $category->id]);
        $notEnrolled = $this->room($host, ['title' => 'Other course class', 'access' => 'course', 'learning_course_id' => $otherCourse->id]);
        $draft = $this->room($other, ['title' => 'Secret draft class', 'status' => 'draft']);

        $all = $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'all']))->assertOk();
        foreach ([$live, $upcoming, $completed, $privateMember, $courseRoom, $categoryRoom] as $visible) {
            $all->assertSee($visible->title);
        }
        foreach ([$privateOther, $notEnrolled, $draft] as $hidden) {
            $all->assertDontSee($hidden->title);
        }
        $all->assertViewHas('counts', ['live' => 1, 'upcoming' => 4, 'completed' => 1, 'all' => 6]);

        $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'live']))
            ->assertOk()->assertSee($live->title)->assertDontSee($upcoming->title)->assertSee('Join now');

        $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'upcoming']))
            ->assertOk()->assertSee($upcoming->title)->assertDontSee($completed->title)->assertDontSee($live->title);

        $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'completed']))
            ->assertOk()->assertSee($completed->title)->assertDontSee($upcoming->title);

        // Default tab is "live" while something is on air.
        $this->actingAs($learner)->get(route('learn.rooms.index'))->assertViewHas('tab', 'live');

        // Filters: search and category.
        $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'all', 'q' => 'membership']))
            ->assertOk()->assertSee($privateMember->title)->assertDontSee($upcoming->title);
        $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'all', 'category' => $category->slug]))
            ->assertOk()->assertSee($courseRoom->title)->assertSee($categoryRoom->title)->assertDontSee($upcoming->title);

        // The other host sees their own private room, but the index never lists drafts.
        $this->actingAs($other)->get(route('learn.rooms.index', ['tab' => 'all']))
            ->assertOk()->assertSee($privateOther->title)->assertDontSee($draft->title)
            ->assertDontSee($privateMember->title);

        // Room staff see everything that is not a draft.
        $staff = $this->member(['is_admin' => true, 'role' => 'admin', 'permissions' => ['rooms.view']]);
        $this->actingAs($staff)->get(route('learn.rooms.index', ['tab' => 'all']))
            ->assertOk()->assertSee($notEnrolled->title)->assertSee($privateOther->title)->assertDontSee($draft->title);
    }

    public function test_index_empty_states(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('learn.rooms.index', ['tab' => 'live']))
            ->assertOk()->assertSee('No live sessions available.');
        $this->actingAs($user)->get(route('learn.rooms.index', ['tab' => 'upcoming']))
            ->assertOk()->assertSee('No upcoming classes.');
        $this->actingAs($user)->get(route('learn.rooms.index', ['tab' => 'completed']))
            ->assertOk()->assertSee('No completed classes yet.');
        $this->actingAs($user)->get(route('learn.rooms.index', ['tab' => 'all', 'q' => 'nothing']))
            ->assertOk()->assertSee('No live classes match your filters');
    }

    // --- Show ----------------------------------------------------------------
    public function test_show_page_details_permissions_and_recordings(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->room($host, [
            'title' => 'Photosynthesis live',
            'description' => "**Bring notes** <script>alert(1)</script>",
            'status' => 'completed',
            'scheduled_at' => now()->subDays(2),
        ]);

        Storage::disk('private')->put('learning/recordings/a.mp4', 'video');
        Storage::disk('private')->put('learning/recordings/b.mp4', 'video');
        $shared = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'source' => 'upload', 'status' => 'ready', 'disk' => 'private',
            'path' => 'learning/recordings/a.mp4', 'mime' => 'video/mp4', 'size_bytes' => 5, 'is_shared' => true,
        ]);
        $hidden = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'source' => 'upload', 'status' => 'ready', 'disk' => 'private',
            'path' => 'learning/recordings/b.mp4', 'mime' => 'video/mp4', 'size_bytes' => 5, 'is_shared' => false,
        ]);

        $page = $this->actingAs($learner)->get(route('learn.rooms.show', $room))->assertOk()
            ->assertSee('Photosynthesis live')
            ->assertSee('<strong>Bring notes</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(route('learn.rooms.recordings.stream', [$room, $shared]), false)
            ->assertDontSee(route('learn.rooms.recordings.stream', [$room, $hidden]), false)
            ->assertDontSee('Manage in studio')
            ->assertSee(route('learn.instructors.show', $host), false);
        $page->assertViewHas('isManager', false);

        // The host gets the quick actions (and sees the unshared recording).
        $this->actingAs($host)->get(route('learn.rooms.show', $room))->assertOk()
            ->assertSee('Manage in studio')
            ->assertSee('Start a new session')
            ->assertSee(route('studio.rooms.start', $room->id), false)
            ->assertSee(route('learn.rooms.recordings.stream', [$room, $hidden]), false);

        // Private room: members only.
        $private = $this->room($host, ['access' => 'private', 'title' => 'Members only']);
        $this->actingAs($learner)->get(route('learn.rooms.show', $private))->assertForbidden();
        $private->members()->create(['user_id' => $learner->id]);
        $this->actingAs($learner)->get(route('learn.rooms.show', $private))->assertOk()->assertSee('Add to calendar');

        // Drafts are hidden from learners.
        $draft = $this->room($host, ['status' => 'draft']);
        $this->actingAs($learner)->get(route('learn.rooms.show', $draft))->assertForbidden();

        // Cancelled banner with the reason.
        $cancelled = $this->room($host, ['status' => 'cancelled', 'cancel_reason' => 'Instructor is unwell']);
        $this->actingAs($learner)->get(route('learn.rooms.show', $cancelled))->assertOk()
            ->assertSee('This class was cancelled.')->assertSee('Instructor is unwell');
    }

    public function test_show_live_room_offers_join_and_participant_count(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host);
        $this->service()->join($room, $host);

        $this->actingAs($learner)->get(route('learn.rooms.show', $room))->assertOk()
            ->assertSee('Join live class')
            ->assertSee(route('learn.rooms.live', $room), false)
            ->assertViewHas('liveCount', 1);
    }

    // --- ICS -------------------------------------------------------------------
    public function test_ics_export_is_rfc5545_and_requires_a_schedule(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->room($host, [
            'title' => 'Maths, part 1; intro \\ basics',
            'description' => "Line one\nLine two with a very long sentence that keeps going and going so that the folding logic has to kick in properly — ümlauts too.",
            'scheduled_at' => now()->setTimezone(config('app.timezone'))->addDays(3)->setTime(14, 30),
            'duration_minutes' => 90,
        ]);

        $response = $this->actingAs($learner)->get(route('learn.rooms.ics', $room))->assertOk();
        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        $body = $response->getContent();
        $start = $room->scheduled_at->copy()->utc()->format('Ymd\THis\Z');
        $end = $room->scheduled_at->copy()->addMinutes(90)->utc()->format('Ymd\THis\Z');

        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $body);
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $body);
        $this->assertStringContainsString("DTSTART:{$start}\r\n", $body);
        $this->assertStringContainsString("DTEND:{$end}\r\n", $body);
        $this->assertStringContainsString("UID:learning-room-{$room->id}@", $body);
        $this->assertMatchesRegularExpression('/DTSTAMP:\d{8}T\d{6}Z\r\n/', $body);
        $this->assertStringContainsString('SUMMARY:Maths\\, part 1\\; intro \\\\ basics', $body);
        $this->assertStringContainsString('Line one\\nLine two', str_replace("\r\n ", '', $body));
        $this->assertStringContainsString('URL:'.route('learn.rooms.show', $room), str_replace("\r\n ", '', $body));
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $body);

        // Every physical line is at most 75 octets and no bare LF is used.
        $this->assertStringNotContainsString("\n", str_replace("\r\n", '', $body));
        foreach (explode("\r\n", rtrim($body, "\r\n")) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'Line too long: '.$line);
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'Folding split a UTF-8 character.');
        }

        $unscheduled = $this->room($host, ['status' => 'draft', 'scheduled_at' => null]);
        $this->actingAs($host)->get(route('learn.rooms.ics', $unscheduled))->assertNotFound();

        $private = $this->room($host, ['access' => 'private']);
        $this->actingAs($learner)->get(route('learn.rooms.ics', $private))->assertForbidden();
    }

    // --- Classroom page ----------------------------------------------------------
    public function test_live_page_renders_config_with_manager_urls_only_for_managers(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host, ['title' => 'Chemistry lab live', 'allow_participant_media' => false]);

        $learnerPage = $this->actingAs($learner)->get(route('learn.rooms.live', $room))->assertOk()
            ->assertSee('learnClassroom(', false)
            ->assertSee('Chemistry lab live');
        $config = $learnerPage->viewData('config');
        $this->assertSame(route('learn.rooms.join', $room), $config['urls']['join']);
        $this->assertStringContainsString('__ID__', $config['urls']['messages']['answer']);
        $this->assertStringContainsString('__ID__', $config['urls']['messages']['destroy']);
        $this->assertArrayNotHasKey('studio', $config['urls']);
        $this->assertFalse($config['viewer']['is_manager']);
        $this->assertFalse($config['room']['allow_participant_media']);
        $this->assertSame('live', $config['room']['status']);
        $this->assertNull($config['provider']['demoWarning']);
        $this->assertSame(config('learning.poll_interval_ms'), $config['pollMs']);
        $this->assertSame(config('learning.presence_interval_ms'), $config['presenceMs']);

        $hostConfig = $this->actingAs($host)->get(route('learn.rooms.live', $room))->assertOk()->viewData('config');
        $this->assertTrue($hostConfig['viewer']['is_manager']);
        $this->assertSame(route('studio.rooms.start', $room->id), $hostConfig['urls']['studio']['start']);
        $this->assertSame(route('studio.rooms.end', $room->id), $hostConfig['urls']['studio']['end']);
        $this->assertSame(route('studio.rooms.announce', $room->id), $hostConfig['urls']['studio']['announce']);
        $this->assertStringEndsWith('/participants/__ID__/remove', $hostConfig['urls']['studio']['remove']);
        $this->assertTrue($hostConfig['provider']['isDemo']);
        $this->assertNotEmpty($hostConfig['provider']['demoWarning']);

        $private = $this->room($host, ['access' => 'private']);
        $this->actingAs($learner)->get(route('learn.rooms.live', $private))->assertForbidden();
    }

    // --- Join --------------------------------------------------------------------
    public function test_join_returns_the_client_config_when_live(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host);

        $response = $this->actingAs($learner)->postJson(route('learn.rooms.join', $room))->assertOk()
            ->assertJsonStructure([
                'attendance_id',
                'config' => ['provider', 'domain', 'scriptUrl', 'roomName', 'jwt', 'isDemo', 'supportsRecording',
                    'moderator', 'user' => ['id', 'name', 'email'], 'configOverwrite', 'interfaceConfigOverwrite'],
            ]);
        $this->assertSame($room->provider_room, $response->json('config.roomName'));
        $this->assertFalse($response->json('config.moderator'));
        $this->assertSame(
            LearningRoomAttendance::where('user_id', $learner->id)->value('id'),
            $response->json('attendance_id'),
        );

        $this->actingAs($host)->postJson(route('learn.rooms.join', $room))->assertOk()->assertJsonPath('config.moderator', true);
    }

    public function test_join_maps_room_state_and_provider_errors(): void
    {
        $host = $this->instructor();
        $learner = $this->member();

        $scheduled = $this->room($host);
        $this->actingAs($learner)->postJson(route('learn.rooms.join', $scheduled))
            ->assertStatus(409)->assertJsonPath('reason', 'not_live')->assertJsonStructure(['reason', 'message']);

        $room = $this->liveRoom($host);
        $this->service()->removeParticipant($room, $learner, $host);
        $this->actingAs($learner)->postJson(route('learn.rooms.join', $room))
            ->assertForbidden()->assertJsonPath('reason', 'removed');

        $suspended = $this->member(['status' => 'suspended']);
        $this->actingAs($suspended)->postJson(route('learn.rooms.join', $room))
            ->assertForbidden()->assertJsonPath('reason', 'inactive');

        $private = $this->liveRoom($host, ['access' => 'private']);
        $this->actingAs($learner)->postJson(route('learn.rooms.join', $private))->assertForbidden();

        // JaaS selected without keys → 503; learners get a generic message, the host the details.
        config([
            'learning.live.provider' => 'jaas',
            'learning.live.jaas.app_id' => null,
            'learning.live.jaas.api_key_id' => null,
            'learning.live.jaas.private_key' => null,
            'learning.live.jaas.private_key_path' => null,
        ]);
        $other = $this->member();
        $response = $this->actingAs($other)->postJson(route('learn.rooms.join', $room))
            ->assertStatus(503)->assertJsonPath('reason', 'provider_unavailable');
        $this->assertStringNotContainsString('LEARNING_JAAS', $response->json('message'));
        $this->assertSame(0, LearningRoomAttendance::where('user_id', $other->id)->count());

        $this->actingAs($host)->postJson(route('learn.rooms.join', $room))
            ->assertStatus(503)->assertJsonPath('reason', 'provider_unavailable');
    }

    // --- Presence, leave, feed -----------------------------------------------
    public function test_presence_and_leave(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host);
        $this->actingAs($learner)->postJson(route('learn.rooms.join', $room))->assertOk();

        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $room), ['jitsi_id' => 'a1b2-c3_d4'])
            ->assertOk()->assertExactJson(['status' => 'live', 'removed' => false]);
        $attendance = LearningRoomAttendance::where('user_id', $learner->id)->firstOrFail();
        $this->assertSame('a1b2-c3_d4', $attendance->jitsi_participant_id);

        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $room), ['jitsi_id' => 'bad id!'])
            ->assertStatus(422)->assertJsonValidationErrors('jitsi_id');
        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $room), ['jitsi_id' => str_repeat('a', 65)])
            ->assertStatus(422);
        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $room))->assertOk();

        // sendBeacon posts multipart FormData with _token (no JSON headers).
        $this->actingAs($learner)
            ->post(route('learn.rooms.leave', $room), ['_token' => csrf_token()], ['Content-Type' => 'multipart/form-data'])
            ->assertNoContent();
        $this->assertNotNull($attendance->fresh()->left_at);

        // Removed users learn about it from the heartbeat.
        $this->service()->removeParticipant($room, $learner, $host);
        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $room))
            ->assertOk()->assertJsonPath('removed', true);

        $private = $this->liveRoom($host, ['access' => 'private']);
        $this->actingAs($learner)->postJson(route('learn.rooms.presence', $private))->assertForbidden();
        $this->actingAs($learner)->post(route('learn.rooms.leave', $private))->assertForbidden();
    }

    public function test_feed_shape_and_participant_privacy(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host);
        $service = $this->service();
        $service->join($room, $host);
        $service->join($room, $learner);
        $service->presence($room, $learner, 'learnerJitsi1');
        $first = $service->postMessage($room, $learner, 'chat', 'Hello class');

        $feed = $this->actingAs($learner)->getJson(route('learn.rooms.feed', $room))->assertOk()
            ->assertJsonStructure([
                'cursor',
                'room' => ['status', 'status_label', 'started_at', 'chat_enabled', 'questions_enabled', 'allow_participant_media', 'title'],
                'me' => ['removed', 'is_host'],
                'messages' => [['id', 'type', 'body', 'user' => ['id', 'name'], 'is_host', 'is_answered', 'is_deleted', 'created_at', 'can_delete', 'can_answer']],
                'updates',
                'participants' => [['user_id', 'name', 'role', 'jitsi_id', 'is_me']],
                'counts' => ['participants', 'questions_open'],
            ])
            ->assertJsonPath('room.status', 'live')
            ->assertJsonPath('me.is_host', false)
            ->assertJsonPath('counts.participants', 2)
            ->assertJsonPath('messages.0.body', 'Hello class');
        $this->assertSame([null, null], array_column($feed->json('participants'), 'jitsi_id'));

        $hostFeed = $this->actingAs($host)->getJson(route('learn.rooms.feed', $room))->assertOk()->assertJsonPath('me.is_host', true);
        $this->assertContains('learnerJitsi1', array_column($hostFeed->json('participants'), 'jitsi_id'));

        $second = $service->postMessage($room, $host, 'chat', 'Welcome!');
        $this->actingAs($learner)->getJson(route('learn.rooms.feed', ['room' => $room, 'after' => $first->id, 'since' => $feed->json('cursor')]))
            ->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.id', $second->id);

        $this->actingAs($learner)->getJson(route('learn.rooms.feed', ['room' => $room, 'after' => -1]))->assertStatus(422);
        $this->actingAs($learner)->getJson(route('learn.rooms.feed', ['room' => $room, 'since' => 'not-a-date']))->assertStatus(422);

        $private = $this->liveRoom($host, ['access' => 'private']);
        $this->actingAs($learner)->getJson(route('learn.rooms.feed', $private))->assertForbidden();
    }

    // --- Messages ----------------------------------------------------------------
    public function test_store_chat_and_question_messages(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $room = $this->liveRoom($host);

        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => '  Good morning <b>all</b>  '])
            ->assertCreated()
            ->assertJsonPath('type', 'chat')
            ->assertJsonPath('body', 'Good morning <b>all</b>')
            ->assertJsonPath('user.id', $learner->id)
            ->assertJsonPath('can_delete', true)
            ->assertJsonPath('can_answer', false);

        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'question', 'body' => 'What is ATP?'])
            ->assertCreated()->assertJsonPath('type', 'question')->assertJsonPath('is_answered', false);

        // Announcements never go through this endpoint (hosts use studio.rooms.announce).
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'announcement', 'body' => 'Listen up'])
            ->assertStatus(422)->assertJsonValidationErrors('type');
        $this->actingAs($host)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'announcement', 'body' => 'Listen up'])
            ->assertStatus(422);
        $this->assertSame(0, LearningRoomMessage::where('type', 'announcement')->count());

        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => str_repeat('x', 2001)])
            ->assertStatus(422)->assertJsonValidationErrors('body');

        // Chat turned off → 422 for learners, the host may still post.
        $room->update(['chat_enabled' => false]);
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => 'Anyone?'])
            ->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($host)->postJson(route('learn.rooms.messages.store', $room), ['type' => 'chat', 'body' => 'Host note'])
            ->assertCreated()->assertJsonPath('is_host', true);

        // Not live → 422.
        $scheduled = $this->room($host);
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $scheduled), ['type' => 'chat', 'body' => 'Early'])
            ->assertStatus(422);

        // Cannot post into a room you cannot see.
        $private = $this->liveRoom($host, ['access' => 'private']);
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.store', $private), ['type' => 'chat', 'body' => 'Hi'])
            ->assertForbidden();
    }

    public function test_answer_and_delete_permissions(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $classmate = $this->member();
        $room = $this->liveRoom($host);
        $service = $this->service();

        $question = $service->postMessage($room, $learner, 'question', 'Is this on the exam?');
        $chat = $service->postMessage($room, $learner, 'chat', 'Hi everyone');

        $this->actingAs($classmate)->postJson(route('learn.rooms.messages.answer', [$room, $question]))->assertForbidden();
        $this->actingAs($learner)->postJson(route('learn.rooms.messages.answer', [$room, $question]))->assertForbidden();
        $this->actingAs($host)->postJson(route('learn.rooms.messages.answer', [$room, $question]))
            ->assertOk()->assertJsonPath('is_answered', true)->assertJsonPath('can_answer', false);
        $this->assertTrue($question->fresh()->is_answered);

        // Only questions can be answered.
        $this->actingAs($host)->postJson(route('learn.rooms.messages.answer', [$room, $chat]))->assertStatus(422);

        $this->actingAs($classmate)->deleteJson(route('learn.rooms.messages.destroy', [$room, $chat]))->assertForbidden();
        $this->actingAs($learner)->deleteJson(route('learn.rooms.messages.destroy', [$room, $chat]))
            ->assertOk()->assertJsonPath('is_deleted', true)->assertJsonPath('body', '');
        $this->assertTrue($chat->fresh()->is_deleted);

        $other = $service->postMessage($room, $classmate, 'chat', 'Off topic');
        $this->actingAs($host)->deleteJson(route('learn.rooms.messages.destroy', [$room, $other]))
            ->assertOk()->assertJsonPath('is_deleted', true);
    }

    public function test_messages_of_another_room_are_not_found(): void
    {
        $host = $this->instructor();
        $learner = $this->member();
        $roomA = $this->liveRoom($host);
        $roomB = $this->liveRoom($host);
        $foreign = $this->service()->postMessage($roomB, $learner, 'question', 'In room B');

        $this->actingAs($host)->postJson(route('learn.rooms.messages.answer', [$roomA, $foreign]))->assertNotFound();
        $this->actingAs($learner)->deleteJson(route('learn.rooms.messages.destroy', [$roomA, $foreign]))->assertNotFound();
        $this->assertFalse($foreign->fresh()->is_answered);
        $this->assertFalse($foreign->fresh()->is_deleted);
    }
}
