<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomAttendance;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Notifications\Learning\InstructorAccessGranted;
use App\Services\Learning\LearningAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Admin → Learning: permission matrix, every page with and without data,
 * catalogue CRUD (categories, courses, topics), enrolments, instructor
 * access (including the card on the admin user page), attendance, trash and
 * the analytics overview.
 */
class AdminLearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['learning.live.provider' => 'jitsi', 'learning.live.jitsi.domain' => 'meet.jit.si']);
        Notification::fake();
    }

    // --- Fixtures --------------------------------------------------------
    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'super_admin', 'permissions' => null]);
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'admin', 'permissions' => $permissions]);
    }

    private function category(string $name = 'Programming'): LearningCategory
    {
        return LearningCategory::create(['name' => $name.' '.uniqid()]);
    }

    private function course(LearningCategory $category, array $attrs = []): LearningCourse
    {
        return LearningCourse::create(array_merge([
            'learning_category_id' => $category->id,
            'title' => 'Course '.uniqid(),
            'status' => 'published',
            'access' => 'open',
        ], $attrs));
    }

    private function video(LearningCategory $category, ?LearningCourse $course = null, array $attrs = []): LearningVideo
    {
        return LearningVideo::create(array_merge([
            'learning_category_id' => $category->id,
            'learning_course_id' => $course?->id,
            'title' => 'Lesson '.uniqid(),
            'status' => 'draft',
            'visibility' => 'course',
            'duration_seconds' => 120,
        ], $attrs));
    }

    private function room(User $host, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => 'Room '.uniqid(),
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'completed',
            'access' => 'public',
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 60,
        ], $attrs));
    }

    /** A small world touching every admin page. */
    private function world(): array
    {
        $instructor = User::factory()->create(['can_teach' => true]);
        $learner = User::factory()->create();
        $category = $this->category();
        $course = $this->course($category, ['instructor_id' => $instructor->id]);
        $topic = LearningTopic::create(['learning_course_id' => $course->id, 'title' => 'Basics']);
        $video = $this->video($category, $course, [
            'learning_topic_id' => $topic->id,
            'status' => 'published',
            'published_at' => now(),
            'views' => 7,
            'instructor_id' => $instructor->id,
        ]);
        LearningEnrollment::create(['user_id' => $learner->id, 'learning_course_id' => $course->id, 'enrolled_at' => now()]);
        LearningVideoProgress::create([
            'user_id' => $learner->id,
            'learning_video_id' => $video->id,
            'percent' => 100,
            'completed_at' => now(),
            'last_watched_at' => now(),
        ]);

        $room = $this->room($instructor);
        $session = LearningRoomSession::create([
            'learning_room_id' => $room->id,
            'started_by' => $instructor->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);
        LearningRoomAttendance::create([
            'learning_room_session_id' => $session->id,
            'learning_room_id' => $room->id,
            'user_id' => $learner->id,
            'role' => 'participant',
            'first_joined_at' => now()->subHours(3),
            'last_seen_at' => now()->subHours(2),
            'total_seconds' => 1800,
            'join_count' => 1,
        ]);

        return compact('instructor', 'learner', 'category', 'course', 'topic', 'video', 'room', 'session');
    }

    private function readPages(array $w): array
    {
        return [
            route('admin.learning.dashboard'),
            route('admin.learning.categories.index'),
            route('admin.learning.courses.index'),
            route('admin.learning.courses.show', $w['course']),
            route('admin.learning.courses.show', [$w['course'], 'tab' => 'topics']),
            route('admin.learning.courses.show', [$w['course'], 'tab' => 'lessons']),
            route('admin.learning.courses.show', [$w['course'], 'tab' => 'enrolments']),
            route('admin.learning.topics.index'),
            route('admin.learning.instructors.index'),
            route('admin.learning.progress.index'),
            route('admin.learning.progress.index', ['tab' => 'courses']),
            route('admin.learning.progress.show', $w['learner']),
        ];
    }

    // --- Pages -------------------------------------------------------------
    public function test_every_page_renders_with_data(): void
    {
        $w = $this->world();
        $admin = $this->superAdmin();

        foreach ([...$this->readPages($w), route('admin.learning.courses.create'), route('admin.learning.courses.edit', $w['course']),
            route('admin.learning.attendance.index'), route('admin.learning.trash.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.learning.courses.index'))->assertSee($w['course']->title);
        $this->actingAs($admin)->get(route('admin.learning.attendance.index'))->assertSee($w['room']->title)->assertSee('0:30:00');
        $this->actingAs($admin)->get(route('admin.learning.progress.index'))->assertSee($w['learner']->name);
    }

    public function test_every_page_renders_when_empty(): void
    {
        $admin = $this->superAdmin();

        foreach ([
            'admin.learning.dashboard', 'admin.learning.categories.index', 'admin.learning.courses.index',
            'admin.learning.topics.index', 'admin.learning.instructors.index', 'admin.learning.progress.index',
            'admin.learning.attendance.index', 'admin.learning.trash.index', 'admin.learning.courses.create',
        ] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.learning.attendance.index'))->assertSee('No live sessions yet');
        $this->actingAs($admin)->get(route('admin.learning.trash.index'))->assertSee('No deleted categories');
    }

    // --- Permission matrix ------------------------------------------------
    public function test_plain_users_and_admins_without_learning_permissions_are_refused(): void
    {
        $w = $this->world();

        foreach ([User::factory()->create(), $this->admin(['users.view'])] as $user) {
            foreach ([...$this->readPages($w), route('admin.learning.attendance.index'), route('admin.learning.trash.index')] as $url) {
                $this->assertContains($this->actingAs($user)->get($url)->status(), [302, 403], $url);
            }
            $this->assertContains($this->actingAs($user)->post(route('admin.learning.categories.store'), ['name' => 'X'])->status(), [302, 403]);
        }

        $this->assertDatabaseMissing('learning_categories', ['name' => 'X']);
    }

    public function test_learning_view_is_read_only(): void
    {
        $w = $this->world();
        $viewer = $this->admin(['learning.view']);

        foreach ($this->readPages($w) as $url) {
            $this->actingAs($viewer)->get($url)->assertOk();
        }

        $this->actingAs($viewer)->get(route('admin.learning.courses.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.learning.courses.edit', $w['course']))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.learning.categories.store'), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.learning.courses.destroy', $w['course']), ['reason' => 'test'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.learning.instructors.store'), ['user_id' => $w['learner']->id])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.learning.attendance.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.learning.trash.index'))->assertForbidden();

        // Manage-only controls are not offered.
        $this->actingAs($viewer)->get(route('admin.learning.instructors.index'))->assertDontSee('Grant instructor access');
    }

    public function test_rooms_view_sees_attendance_only(): void
    {
        $w = $this->world();
        $roomsViewer = $this->admin(['rooms.view']);

        $this->actingAs($roomsViewer)->get(route('admin.learning.attendance.index'))->assertOk()->assertSee($w['room']->title);
        $this->actingAs($roomsViewer)->get(route('admin.learning.attendance.index', ['room' => $w['room']->id, 'from' => now()->subDays(2)->format('Y-m-d')]))->assertOk();
        $this->actingAs($roomsViewer)->get(route('admin.learning.attendance.index', ['from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
        $this->actingAs($roomsViewer)->get(route('admin.learning.dashboard'))->assertForbidden();
        $this->actingAs($roomsViewer)->get(route('admin.learning.trash.index'))->assertForbidden();
    }

    // --- Categories ---------------------------------------------------------
    public function test_category_crud_and_blocked_delete(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage']);

        $this->actingAs($manager)->post(route('admin.learning.categories.store'), ['name' => '  Networking ', 'icon' => 'wifi'])
            ->assertRedirect()->assertSessionHas('success');
        $category = LearningCategory::where('name', 'Networking')->firstOrFail();

        $this->actingAs($manager)->post(route('admin.learning.categories.store'), ['name' => '', 'icon' => '<script>'])
            ->assertSessionHasErrors(['name', 'icon']);

        $this->actingAs($manager)->put(route('admin.learning.categories.update', $category), ['name' => 'Networks', 'position' => 4])
            ->assertSessionHas('success');
        $this->assertSame(['Networks', 4], [$category->fresh()->name, (int) $category->fresh()->position]);

        $course = $this->course($category);
        $this->actingAs($manager)->delete(route('admin.learning.categories.destroy', $category), ['reason' => 'cleanup'])
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($category);

        $this->actingAs($manager)->delete(route('admin.learning.categories.destroy', $category))->assertSessionHasErrors('reason');

        $course->delete();
        $this->actingAs($manager)->delete(route('admin.learning.categories.destroy', $category), ['reason' => 'cleanup'])
            ->assertSessionHas('success');
        $this->assertSoftDeleted($category);
    }

    // --- Courses ---------------------------------------------------------------
    public function test_course_create_update_and_notify_on_publish(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage']);
        $instructor = User::factory()->create(['can_teach' => true]);
        $category = $this->category();

        $this->actingAs($manager)->post(route('admin.learning.courses.store'), [
            'learning_category_id' => $category->id,
            'title' => 'Laravel from zero',
            'level' => 'beginner',
            'instructor_id' => $instructor->id,
            'access' => 'open',
            'status' => 'draft',
        ])->assertRedirect()->assertSessionHas('success');

        $course = LearningCourse::where('title', 'Laravel from zero')->firstOrFail();
        $this->assertSame([$instructor->id, 'draft', null], [(int) $course->instructor_id, $course->status, $course->published_at]);

        $this->actingAs($manager)->put(route('admin.learning.courses.update', $course), [
            'learning_category_id' => $category->id,
            'title' => 'Laravel from zero',
            'access' => 'enrolled',
            'status' => 'published',
        ])->assertRedirect(route('admin.learning.courses.show', $course));

        $course->refresh();
        $this->assertSame(['published', 'enrolled'], [$course->status, $course->access]);
        $this->assertNotNull($course->published_at);

        $this->actingAs($manager)->post(route('admin.learning.courses.store'), [
            'learning_category_id' => $category->id,
            'title' => '',
            'access' => 'everyone',
            'status' => 'live',
            'instructor_id' => User::factory()->create()->id, // not an instructor
        ])->assertSessionHasErrors(['title', 'access', 'status', 'instructor_id']);
    }

    public function test_course_delete_is_blocked_by_lessons_unless_with_videos(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage']);
        $category = $this->category();
        $course = $this->course($category);
        $video = $this->video($category, $course);

        $this->actingAs($manager)->delete(route('admin.learning.courses.destroy', $course), ['reason' => 'retired'])
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($course);

        $this->actingAs($manager)->delete(route('admin.learning.courses.destroy', $course), ['reason' => 'retired', 'with_videos' => 1])
            ->assertRedirect(route('admin.learning.courses.index'))->assertSessionHas('success');
        $this->assertSoftDeleted($course);
        $this->assertSoftDeleted($video);
    }

    // --- Topics -------------------------------------------------------------------
    public function test_topic_crud(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage']);
        $category = $this->category();
        $course = $this->course($category);

        $this->actingAs($manager)->post(route('admin.learning.topics.store'), ['learning_course_id' => $course->id, 'title' => 'Routing'])
            ->assertSessionHas('success');
        $topic = LearningTopic::where('title', 'Routing')->firstOrFail();
        $video = $this->video($category, $course, ['learning_topic_id' => $topic->id]);

        $this->actingAs($manager)->post(route('admin.learning.topics.store'), ['learning_course_id' => 999999, 'title' => ''])
            ->assertSessionHasErrors(['learning_course_id', 'title']);

        $this->actingAs($manager)->put(route('admin.learning.topics.update', $topic), ['title' => 'HTTP routing', 'position' => 2])
            ->assertSessionHas('success');
        $this->assertSame('HTTP routing', $topic->fresh()->title);

        $this->actingAs($manager)->get(route('admin.learning.topics.index', ['course' => $course->id]))->assertOk()->assertSee('HTTP routing');

        $this->actingAs($manager)->delete(route('admin.learning.topics.destroy', $topic), ['reason' => 'merged'])
            ->assertSessionHas('success');
        $this->assertNull(LearningTopic::find($topic->id));
        $this->assertNull($video->fresh()->learning_topic_id);
    }

    // --- Enrolments ----------------------------------------------------------------
    public function test_enrolment_add_and_remove(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage']);
        $category = $this->category();
        $course = $this->course($category, ['access' => 'enrolled']);
        [$a, $b] = User::factory()->count(2)->create();
        $suspended = User::factory()->create(['status' => 'suspended']);

        $this->actingAs($manager)->post(route('admin.learning.enrollments.store', $course), ['user_ids' => [$a->id, $b->id, $suspended->id]])
            ->assertRedirect(route('admin.learning.courses.show', [$course, 'tab' => 'enrolments']));

        $this->assertDatabaseHas('learning_enrollments', ['learning_course_id' => $course->id, 'user_id' => $a->id, 'source' => 'admin', 'enrolled_by' => $manager->id]);
        $this->assertDatabaseHas('learning_enrollments', ['learning_course_id' => $course->id, 'user_id' => $b->id]);
        $this->assertDatabaseMissing('learning_enrollments', ['user_id' => $suspended->id]);

        // Idempotent.
        $this->actingAs($manager)->post(route('admin.learning.enrollments.store', $course), ['user_ids' => [$a->id]]);
        $this->assertSame(2, $course->enrollments()->count());

        $this->actingAs($manager)->get(route('admin.learning.courses.show', [$course, 'tab' => 'enrolments']))->assertOk()->assertSee($a->name);

        $enrollment = $course->enrollments()->where('user_id', $a->id)->firstOrFail();
        $this->actingAs($manager)->delete(route('admin.learning.enrollments.destroy', [$course, $enrollment]), ['reason' => 'left the class'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('learning_enrollments', ['id' => $enrollment->id]);

        $other = $this->course($category);
        $otherEnrollment = LearningEnrollment::create(['user_id' => $b->id, 'learning_course_id' => $other->id, 'enrolled_at' => now()]);
        $this->actingAs($manager)->delete(route('admin.learning.enrollments.destroy', [$course, $otherEnrollment]), ['reason' => 'wrong course'])
            ->assertNotFound();
    }

    // --- Instructors -----------------------------------------------------------------
    public function test_instructor_grant_and_revoke(): void
    {
        $manager = $this->admin(['learning.view', 'learning.manage', 'users.view']);
        $member = User::factory()->create();

        $this->actingAs($manager)->post(route('admin.learning.instructors.store'), ['user_id' => $member->id])
            ->assertSessionHas('success');
        $this->assertTrue($member->fresh()->isInstructor());
        Notification::assertSentTo($member, InstructorAccessGranted::class);

        // Granting twice is harmless and does not notify again.
        $this->actingAs($manager)->post(route('admin.learning.instructors.store'), ['user_id' => $member->id])->assertSessionHas('success');
        Notification::assertSentToTimes($member, InstructorAccessGranted::class, 1);

        $this->actingAs($manager)->get(route('admin.learning.instructors.index'))->assertOk()->assertSee($member->name);

        $this->actingAs($manager)->delete(route('admin.learning.instructors.destroy', $member))->assertSessionHasErrors('reason');
        $this->actingAs($manager)->delete(route('admin.learning.instructors.destroy', $member), ['reason' => 'term ended'])
            ->assertSessionHas('success');
        $this->assertFalse($member->fresh()->isInstructor());

        $suspended = User::factory()->create(['status' => 'suspended']);
        $this->actingAs($manager)->post(route('admin.learning.instructors.store'), ['user_id' => $suspended->id])->assertSessionHas('error');
        $this->assertFalse($suspended->fresh()->isInstructor());
    }

    public function test_user_page_instructor_card_is_only_for_learning_managers(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->superAdmin())->get(route('admin.users.show', $member))
            ->assertOk()->assertSee('Instructor access')->assertSee('Grant instructor access');

        $member->forceFill(['can_teach' => true])->save();
        $this->actingAs($this->superAdmin())->get(route('admin.users.show', $member))->assertSee('Revoke access');

        $this->actingAs($this->admin(['users.view']))->get(route('admin.users.show', $member))
            ->assertOk()->assertDontSee('Instructor access');
    }

    // --- Trash ----------------------------------------------------------------------------
    public function test_trash_restore_and_purge_with_per_type_permissions(): void
    {
        $host = User::factory()->create(['can_teach' => true]);
        $category = $this->category();
        $course = $this->course($category);
        $course->delete();
        $room = $this->room($host);
        $room->delete();

        $learningManager = $this->admin(['learning.manage']);
        $roomsManager = $this->admin(['rooms.manage']);

        $this->actingAs($learningManager)->get(route('admin.learning.trash.index', ['type' => 'course']))->assertOk()->assertSee($course->title);
        $this->actingAs($learningManager)->get(route('admin.learning.trash.index', ['type' => 'room']))->assertOk()->assertDontSee($room->title);
        $this->actingAs($learningManager)->post(route('admin.learning.trash.restore', ['type' => 'room', 'id' => $room->id]))->assertForbidden();
        $this->actingAs($roomsManager)->post(route('admin.learning.trash.restore', ['type' => 'course', 'id' => $course->id]))->assertForbidden();

        $this->actingAs($learningManager)->post(route('admin.learning.trash.restore', ['type' => 'course', 'id' => $course->id]))
            ->assertSessionHas('success');
        $this->assertNotSoftDeleted($course);

        $this->actingAs($roomsManager)->get(route('admin.learning.trash.index'))->assertOk()->assertSee($room->title);
        $this->actingAs($roomsManager)->delete(route('admin.learning.trash.destroy', ['type' => 'room', 'id' => $room->id]))->assertSessionHasErrors('reason');
        $this->actingAs($roomsManager)->delete(route('admin.learning.trash.destroy', ['type' => 'room', 'id' => $room->id]), ['reason' => 'old test room'])
            ->assertSessionHas('success');
        $this->assertNull(LearningRoom::withTrashed()->find($room->id));

        // Already gone.
        $this->actingAs($roomsManager)->post(route('admin.learning.trash.restore', ['type' => 'room', 'id' => $room->id]))->assertSessionHas('error');
    }

    // --- Analytics -------------------------------------------------------------------------
    public function test_analytics_overview_numbers(): void
    {
        $w = $this->world();
        $this->category('Empty');

        $overview = app(LearningAnalytics::class)->overview(true);

        $this->assertSame(1, $overview['videos']);
        $this->assertSame(1, $overview['videos_published']);
        $this->assertSame(1, $overview['courses']);
        $this->assertSame(2, $overview['categories']);
        $this->assertSame(1, $overview['learners']);
        $this->assertSame(7, $overview['video_views']);
        $this->assertSame(1, $overview['completed_lessons']);
        $this->assertSame(100, $overview['average_completion']);
        $this->assertSame(1, $overview['attendance_30d']);
        $this->assertSame(1, $overview['sessions_30d']);
        $this->assertSame($w['video']->title, $overview['top_lessons'][0]['title']);

        $this->actingAs($this->superAdmin())->get(route('admin.learning.dashboard'))->assertOk()->assertSee($w['video']->title);
    }
}
