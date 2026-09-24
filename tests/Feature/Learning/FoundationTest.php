<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomMember;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Foundation of ROOM (live classroom & video learning): route surface,
 * visibility rules (scope == policy), gates, User helpers, slugs, provider
 * room names, the live-room badge and the sidebar links.
 */
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    /** Every route name of the implementation contract (§8). */
    private const ROUTES = [
        'learn.dashboard', 'learn.progress', 'learn.calendar', 'learn.search',
        'learn.categories.index', 'learn.categories.show',
        'learn.courses.index', 'learn.courses.show', 'learn.courses.enroll', 'learn.courses.unenroll',
        'learn.videos.index', 'learn.videos.show', 'learn.videos.stream', 'learn.videos.progress',
        'learn.videos.complete', 'learn.videos.resource', 'learn.videos.comments.store', 'learn.videos.comments.destroy',
        'learn.instructors.show',
        'learn.rooms.index', 'learn.rooms.show', 'learn.rooms.ics', 'learn.rooms.live', 'learn.rooms.join',
        'learn.rooms.presence', 'learn.rooms.leave', 'learn.rooms.feed', 'learn.rooms.messages.store',
        'learn.rooms.messages.answer', 'learn.rooms.messages.destroy', 'learn.rooms.recordings.stream',
        'signed.learning.videos.stream',

        'studio.home', 'studio.uploads.config', 'studio.uploads.init', 'studio.uploads.chunk',
        'studio.uploads.complete', 'studio.uploads.abort', 'studio.users.search',
        'studio.rooms.index', 'studio.rooms.create', 'studio.rooms.store', 'studio.rooms.show', 'studio.rooms.edit',
        'studio.rooms.update', 'studio.rooms.destroy', 'studio.rooms.publish', 'studio.rooms.start', 'studio.rooms.end',
        'studio.rooms.cancel', 'studio.rooms.announce', 'studio.rooms.sessions.destroy', 'studio.rooms.participants',
        'studio.rooms.members.store', 'studio.rooms.members.destroy', 'studio.rooms.participants.remove',
        'studio.rooms.attendance', 'studio.rooms.attendance.export', 'studio.rooms.recordings.store',
        'studio.rooms.recordings.update', 'studio.rooms.recordings.publish', 'studio.rooms.recordings.destroy',
        'studio.videos.index', 'studio.videos.create', 'studio.videos.store', 'studio.videos.edit', 'studio.videos.update',
        'studio.videos.destroy', 'studio.videos.publish', 'studio.videos.unpublish', 'studio.videos.replace',
        'studio.videos.thumbnail.destroy', 'studio.videos.renditions.store', 'studio.videos.renditions.destroy',
        'studio.videos.resources.store', 'studio.videos.resources.destroy',

        'admin.learning.dashboard',
        'admin.learning.categories.index', 'admin.learning.categories.store', 'admin.learning.categories.update',
        'admin.learning.categories.destroy',
        'admin.learning.courses.index', 'admin.learning.courses.create', 'admin.learning.courses.store',
        'admin.learning.courses.show', 'admin.learning.courses.edit', 'admin.learning.courses.update',
        'admin.learning.courses.destroy', 'admin.learning.enrollments.store', 'admin.learning.enrollments.destroy',
        'admin.learning.topics.index', 'admin.learning.topics.store', 'admin.learning.topics.update',
        'admin.learning.topics.destroy',
        'admin.learning.instructors.index', 'admin.learning.instructors.store', 'admin.learning.instructors.destroy',
        'admin.learning.progress.index', 'admin.learning.progress.show', 'admin.learning.attendance.index',
        'admin.learning.trash.index', 'admin.learning.trash.restore', 'admin.learning.trash.destroy',
    ];

    // --- People --------------------------------------------------------
    private function learner(): User
    {
        return User::factory()->create();
    }

    private function instructor(): User
    {
        return User::factory()->create(['can_teach' => true]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    private function restrictedAdmin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    // --- Content fixtures ----------------------------------------------
    private function category(string $name): LearningCategory
    {
        return LearningCategory::create(['name' => $name]);
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

    private function video(LearningCategory $category, ?LearningCourse $course, array $attrs = []): LearningVideo
    {
        return LearningVideo::create(array_merge([
            'learning_category_id' => $category->id,
            'learning_course_id' => $course?->id,
            'title' => 'Lesson '.uniqid(),
            'status' => 'published',
            'visibility' => 'course',
        ], $attrs));
    }

    private function room(array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => 'Room '.uniqid(),
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDay(),
        ], $attrs));
    }

    private function enroll(User $user, LearningCourse $course): void
    {
        LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $course->id, 'enrolled_at' => now()]);
    }

    /**
     * The shared world every visibility test runs against. The learner is
     * enrolled in `enrolled` (category cat1) and invited to `privateMember`;
     * the instructor teaches `instructorDraft` and owns `ownDraft`.
     *
     * @return array{learner:User,instructor:User,courses:array<string,LearningCourse>,videos:array<string,LearningVideo>,rooms:array<string,LearningRoom>}
     */
    private function world(): array
    {
        $learner = $this->learner();
        $instructor = $this->instructor();
        $cat1 = $this->category('Research Methods');
        $cat2 = $this->category('Data Analysis');

        $courses = [
            'open' => $this->course($cat1),
            'enrolled' => $this->course($cat1, ['access' => 'enrolled']),
            'locked' => $this->course($cat2, ['access' => 'enrolled']),
            'draft' => $this->course($cat1, ['status' => 'draft']),
            'instructorDraft' => $this->course($cat2, ['status' => 'draft', 'instructor_id' => $instructor->id]),
        ];
        $this->enroll($learner, $courses['enrolled']);

        $videos = [
            'members' => $this->video($cat2, $courses['locked'], ['visibility' => 'members']),
            'courseOpen' => $this->video($cat1, $courses['open']),
            'courseEnrolled' => $this->video($cat1, $courses['enrolled']),
            'courseLocked' => $this->video($cat2, $courses['locked']),
            'private' => $this->video($cat1, $courses['open'], ['visibility' => 'private']),
            'draft' => $this->video($cat1, null, ['visibility' => 'members', 'status' => 'draft']),
            'inDraftCourse' => $this->video($cat1, $courses['draft']),
            'standalone' => $this->video($cat1, null),
            'ownDraft' => $this->video($cat1, null, ['visibility' => 'private', 'status' => 'draft', 'instructor_id' => $instructor->id]),
            'inInstructorsCourse' => $this->video($cat2, $courses['instructorDraft'], ['visibility' => 'private']),
        ];

        $rooms = [
            'public' => $this->room(),
            'privateMember' => $this->room(['access' => 'private']),
            'privateOther' => $this->room(['access' => 'private']),
            'courseEnrolled' => $this->room(['access' => 'course', 'learning_course_id' => $courses['enrolled']->id]),
            'courseLocked' => $this->room(['access' => 'course', 'learning_course_id' => $courses['locked']->id]),
            'categoryEnrolled' => $this->room(['access' => 'category', 'learning_category_id' => $cat1->id, 'status' => 'live']),
            'categoryOther' => $this->room(['access' => 'category', 'learning_category_id' => $cat2->id]),
            'draft' => $this->room(['status' => 'draft']),
            'hostedDraft' => $this->room(['status' => 'draft', 'access' => 'private', 'host_id' => $instructor->id]),
        ];
        LearningRoomMember::create(['learning_room_id' => $rooms['privateMember']->id, 'user_id' => $learner->id]);

        return compact('learner', 'instructor', 'courses', 'videos', 'rooms');
    }

    /**
     * Asserts the query scope and the policy agree for $user, and that the
     * visible set is exactly $expected (keys of $models).
     *
     * @param  array<string,Model>  $models
     * @param  list<string>  $expected
     */
    private function assertVisibility(User $user, string $modelClass, array $models, array $expected, string $who): void
    {
        $scopeIds = $modelClass::query()->visibleTo($user)->pluck('id')->sort()->values()->all();

        $policyIds = collect($models)
            ->filter(fn (Model $m) => Gate::forUser($user)->allows('view', $modelClass::find($m->id)))
            ->map->id->sort()->values()->all();

        $this->assertSame($policyIds, $scopeIds, class_basename($modelClass)." scope and policy disagree for {$who}");

        $expectedIds = collect($expected)->map(fn ($k) => $models[$k]->id)->sort()->values()->all();
        $this->assertSame($expectedIds, $scopeIds, class_basename($modelClass)." visible set is wrong for {$who}");
    }

    // --- Tests ---------------------------------------------------------
    public function test_every_contract_route_is_registered(): void
    {
        $missing = array_values(array_filter(self::ROUTES, fn ($name) => ! Route::has($name)));

        $this->assertSame([], $missing, 'Missing learning routes: '.implode(', ', $missing));
    }

    public function test_upload_token_routes_only_accept_forty_character_tokens(): void
    {
        $this->assertStringEndsWith('/studio/uploads/'.str_repeat('a', 40).'/chunk', route('studio.uploads.chunk', str_repeat('a', 40)));

        $route = Route::getRoutes()->getByName('studio.uploads.chunk');
        $this->assertSame('[A-Za-z0-9]{40}', $route->wheres['token'] ?? null);
    }

    public function test_learner_routes_bind_by_slug(): void
    {
        $category = $this->category('Thesis Writing');
        $course = $this->course($category, ['title' => 'Writing Chapter One']);

        $this->assertStringEndsWith('/learn/courses/writing-chapter-one', route('learn.courses.show', $course));
        $this->assertStringEndsWith('/learn/categories/thesis-writing', route('learn.categories.show', $category));
        $this->assertStringEndsWith('/admin/learning/courses/'.$course->id, route('admin.learning.courses.show', $course));
    }

    public function test_course_visibility_scope_matches_policy(): void
    {
        $w = $this->world();
        $c = $w['courses'];

        $this->assertVisibility($w['learner'], LearningCourse::class, $c, ['open', 'enrolled'], 'learner');
        $this->assertVisibility($w['instructor'], LearningCourse::class, $c, ['open', 'instructorDraft'], 'instructor');
        $this->assertVisibility($this->restrictedAdmin(['rooms.view']), LearningCourse::class, $c, ['open'], 'rooms.view admin');
        $this->assertVisibility($this->restrictedAdmin(['learning.view']), LearningCourse::class, $c, array_keys($c), 'learning.view admin');
        $this->assertVisibility($this->superAdmin(), LearningCourse::class, $c, array_keys($c), 'super admin');
    }

    public function test_video_visibility_scope_matches_policy(): void
    {
        $w = $this->world();
        $v = $w['videos'];

        $this->assertVisibility($w['learner'], LearningVideo::class, $v,
            ['members', 'courseOpen', 'courseEnrolled', 'standalone'], 'learner');
        $this->assertVisibility($w['instructor'], LearningVideo::class, $v,
            ['members', 'courseOpen', 'standalone', 'ownDraft', 'inInstructorsCourse'], 'instructor');
        $this->assertVisibility($this->restrictedAdmin(['rooms.view']), LearningVideo::class, $v,
            ['members', 'courseOpen', 'standalone'], 'rooms.view admin');
        $this->assertVisibility($this->restrictedAdmin(['learning.view']), LearningVideo::class, $v, array_keys($v), 'learning.view admin');
        $this->assertVisibility($this->superAdmin(), LearningVideo::class, $v, array_keys($v), 'super admin');
    }

    public function test_video_in_a_trashed_course_is_hidden_from_learners(): void
    {
        $w = $this->world();
        $w['courses']['open']->delete();

        $visible = LearningVideo::query()->visibleTo($w['learner'])->pluck('id');

        $this->assertNotContains($w['videos']['courseOpen']->id, $visible);
        $this->assertFalse($w['learner']->can('view', $w['videos']['courseOpen']->fresh()));
        $this->assertContains($w['videos']['members']->id, $visible);
    }

    public function test_room_visibility_scope_matches_policy(): void
    {
        $w = $this->world();
        $r = $w['rooms'];

        $this->assertVisibility($w['learner'], LearningRoom::class, $r,
            ['public', 'privateMember', 'courseEnrolled', 'categoryEnrolled'], 'learner');
        $this->assertVisibility($w['instructor'], LearningRoom::class, $r, ['public', 'hostedDraft'], 'instructor');
        $this->assertVisibility($this->restrictedAdmin(['rooms.view']), LearningRoom::class, $r, array_keys($r), 'rooms.view admin');
        $this->assertVisibility($this->restrictedAdmin(['learning.view']), LearningRoom::class, $r, ['public'], 'learning.view admin');
        $this->assertVisibility($this->superAdmin(), LearningRoom::class, $r, array_keys($r), 'super admin');

        // join follows view
        $this->assertTrue($w['learner']->can('join', $r['privateMember']));
        $this->assertFalse($w['learner']->can('join', $r['privateOther']));
    }

    public function test_management_abilities(): void
    {
        $w = $this->world();
        $own = $w['videos']['ownDraft'];
        $other = $w['videos']['courseOpen'];
        $hosted = $w['rooms']['hostedDraft'];

        $this->assertTrue($w['instructor']->can('update', $own));
        $this->assertTrue($w['instructor']->can('publish', $own));
        $this->assertFalse($w['instructor']->can('delete', $other));
        $this->assertTrue($this->restrictedAdmin(['learning.manage'])->can('update', $other));
        $this->assertFalse($this->restrictedAdmin(['learning.view'])->can('update', $other));

        // A former instructor keeps their rows but loses the right to manage them.
        $former = User::factory()->create(['can_teach' => false]);
        $own->update(['instructor_id' => $former->id]);
        $this->assertFalse($former->can('update', $own->fresh()));

        $this->assertTrue($w['instructor']->can('manage', $hosted));
        $this->assertTrue($w['instructor']->can('start', $hosted));
        $this->assertFalse($w['instructor']->can('manage', $w['rooms']['public']));
        $this->assertTrue($this->restrictedAdmin(['rooms.manage'])->can('end', $w['rooms']['public']));
        $this->assertTrue($this->restrictedAdmin(['rooms.view'])->can('viewAttendance', $w['rooms']['public']));
        $this->assertFalse($this->restrictedAdmin(['rooms.view'])->can('manage', $w['rooms']['public']));
        $this->assertTrue($hosted->isManageableBy($w['instructor']));
        $this->assertFalse($hosted->isManageableBy($w['learner']));

        $this->assertTrue($w['instructor']->can('create', LearningRoom::class));
        $this->assertFalse($w['learner']->can('create', LearningRoom::class));
        $this->assertTrue($w['instructor']->can('create', LearningVideo::class));
        $this->assertFalse($w['learner']->can('create', LearningVideo::class));

        $this->assertTrue($w['learner']->can('enroll', $w['courses']['open']));
        $this->assertFalse($w['learner']->can('enroll', $w['courses']['locked']));
        $this->assertFalse($w['learner']->can('enroll', $w['courses']['draft']));
        $this->assertFalse($w['learner']->can('manage', LearningCourse::class));
        $this->assertTrue($this->restrictedAdmin(['learning.manage'])->can('manage', LearningCourse::class));
    }

    public function test_studio_and_trash_gates(): void
    {
        $learner = $this->learner();
        $instructor = $this->instructor();

        $this->assertFalse(Gate::forUser($learner)->allows('learning.studio'));
        $this->assertTrue(Gate::forUser($instructor)->allows('learning.studio'));
        $this->assertTrue(Gate::forUser($this->restrictedAdmin(['rooms.view']))->allows('learning.studio'));
        $this->assertTrue(Gate::forUser($this->restrictedAdmin(['learning.view']))->allows('learning.studio'));
        $this->assertFalse(Gate::forUser($this->restrictedAdmin(['orders.view']))->allows('learning.studio'));
        $this->assertTrue(Gate::forUser($this->superAdmin())->allows('learning.studio'));

        $this->assertFalse(Gate::forUser($learner)->allows('learning.trash'));
        $this->assertFalse(Gate::forUser($instructor)->allows('learning.trash'));
        $this->assertFalse(Gate::forUser($this->restrictedAdmin(['learning.view', 'rooms.view']))->allows('learning.trash'));
        $this->assertTrue(Gate::forUser($this->restrictedAdmin(['learning.manage']))->allows('learning.trash'));
        $this->assertTrue(Gate::forUser($this->restrictedAdmin(['rooms.manage']))->allows('learning.trash'));
        $this->assertTrue(Gate::forUser($this->superAdmin())->allows('learning.trash'));
    }

    public function test_learning_permissions_are_declared(): void
    {
        foreach (['learning.view', 'learning.manage', 'rooms.view', 'rooms.manage'] as $key) {
            $this->assertContains($key, Permissions::keys());
        }
    }

    public function test_user_helpers_and_relations(): void
    {
        $learner = $this->learner();
        $this->assertFalse($learner->fresh()->can_teach);
        $this->assertFalse($learner->isInstructor());
        $this->assertFalse($learner->canHostRooms());
        $this->assertFalse($learner->canUploadLessons());
        $this->assertFalse($learner->canAccessStudio());

        $instructor = $this->instructor();
        $this->assertTrue($instructor->can_teach);
        $this->assertTrue($instructor->isInstructor());
        $this->assertTrue($instructor->canHostRooms());
        $this->assertTrue($instructor->canUploadLessons());
        $this->assertTrue($instructor->canAccessStudio());

        $roomsManager = $this->restrictedAdmin(['rooms.manage']);
        $this->assertTrue($roomsManager->canHostRooms());
        $this->assertFalse($roomsManager->canUploadLessons());

        $contentManager = $this->restrictedAdmin(['learning.manage']);
        $this->assertFalse($contentManager->canHostRooms());
        $this->assertTrue($contentManager->canUploadLessons());

        $viewer = $this->restrictedAdmin(['rooms.view']);
        $this->assertFalse($viewer->canHostRooms());
        $this->assertTrue($viewer->canAccessStudio());

        $super = $this->superAdmin();
        $this->assertFalse($super->isInstructor());
        $this->assertTrue($super->canHostRooms());
        $this->assertTrue($super->canUploadLessons());

        $category = $this->category('Statistics');
        $course = $this->course($category, ['instructor_id' => $instructor->id]);
        $video = $this->video($category, $course, ['instructor_id' => $instructor->id]);
        $this->room(['host_id' => $instructor->id]);
        $this->enroll($learner, $course);
        LearningVideoProgress::create(['user_id' => $learner->id, 'learning_video_id' => $video->id]);

        $this->assertSame(1, $learner->learningEnrollments()->count());
        $this->assertSame(1, $learner->learningProgress()->count());
        $this->assertSame(1, $instructor->hostedRooms()->count());
        $this->assertSame(1, $instructor->teachingVideos()->count());
        $this->assertSame(1, $instructor->taughtCourses()->count());
        $this->assertTrue($course->isEnrolled($learner));
        $this->assertSame([$learner->id], $course->enrolledUsers()->pluck('users.id')->all());
    }

    public function test_slugs_stay_unique_including_trashed_rows(): void
    {
        $first = $this->category('Data Science');
        $this->assertSame('data-science', $first->slug);
        $first->delete();

        $second = $this->category('Data Science');
        $this->assertSame('data-science-2', $second->slug);

        $course = $this->course($second, ['title' => 'Intro to R']);
        $course->delete();
        $this->assertSame('intro-to-r-2', $this->course($second, ['title' => 'Intro to R'])->slug);

        $video = $this->video($second, null, ['title' => 'Cleaning data']);
        $video->delete();
        $this->assertSame('cleaning-data-2', $this->video($second, null, ['title' => 'Cleaning data'])->slug);

        $room = $this->room(['title' => 'Office hours']);
        $room->delete();
        $this->assertSame('office-hours-2', $this->room(['title' => 'Office hours'])->slug);

        // Saving again keeps the slug it already has.
        $second->update(['name' => 'Data Science & AI']);
        $this->assertSame('data-science-2', $second->fresh()->slug);
    }

    public function test_provider_room_is_generated_and_unique(): void
    {
        $prefix = config('learning.live.room_prefix');
        $a = $this->room();
        $b = $this->room();

        $this->assertMatchesRegularExpression('/^'.preg_quote($prefix, '/').'-[a-z0-9]{20}$/', $a->provider_room);
        $this->assertNotSame($a->provider_room, $b->provider_room);

        $explicit = $this->room(['provider_room' => $prefix.'-fixedname']);
        $this->assertSame($prefix.'-fixedname', $explicit->provider_room);
    }

    public function test_new_models_read_their_column_defaults(): void
    {
        $room = LearningRoom::create(['title' => 'Defaults']);

        $this->assertSame('draft', $room->status);
        $this->assertTrue($room->isDraft());
        $this->assertSame(60, $room->duration_minutes);
        $this->assertTrue($room->chat_enabled);
        $this->assertNull($room->endsAt());
    }

    public function test_live_count_counts_visible_live_rooms_and_is_cached(): void
    {
        $w = $this->world();

        // Only categoryEnrolled is live, and only the learner (enrolled in cat1) may see it.
        $this->assertSame(1, LearningRoom::liveCountFor($w['learner']));
        $this->assertSame(0, LearningRoom::liveCountFor($w['instructor']));
        $this->assertSame(1, LearningRoom::liveCountFor($this->restrictedAdmin(['rooms.view'])));

        // Cached for 30 s per user.
        $w['rooms']['public']->update(['status' => 'live']);
        $this->assertSame(1, LearningRoom::liveCountFor($w['learner']));
        cache()->forget('learning.live_count.'.$w['learner']->id);
        $this->assertSame(2, LearningRoom::liveCountFor($w['learner']));
    }

    public function test_live_count_is_zero_when_the_table_is_missing(): void
    {
        $user = $this->learner();

        Schema::disableForeignKeyConstraints();
        foreach (['learning_room_recordings', 'learning_room_messages', 'learning_room_attendances',
            'learning_room_sessions', 'learning_room_members', 'learning_rooms'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        $this->assertSame(0, LearningRoom::liveCountFor($user));
    }

    public function test_user_sidebar_shows_the_learning_group(): void
    {
        Http::fake();
        $learner = $this->learner();

        $this->actingAs($learner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Learning')
            ->assertSee(route('learn.courses.index'), false)
            ->assertSee(route('learn.videos.index'), false)
            ->assertSee(route('learn.rooms.index'), false)
            ->assertSee(route('learn.calendar'), false)
            ->assertSee(route('learn.progress'), false)
            ->assertDontSee('Teaching Studio');
    }

    public function test_instructor_sidebar_links_the_teaching_studio(): void
    {
        Http::fake();

        $this->actingAs($this->instructor())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Teaching Studio')
            ->assertSee(route('studio.rooms.index'), false);
    }

    public function test_admin_sidebar_shows_the_learning_section(): void
    {
        Http::fake();

        $this->actingAs($this->superAdmin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Learning Overview')
            ->assertSee('Live Rooms')
            ->assertSee('Video Lessons')
            ->assertSee('Learning Library')
            ->assertSee(route('admin.learning.courses.index'), false);

        // An admin with no learning rights sees none of the staff links.
        $this->actingAs($this->restrictedAdmin(['users.view']))->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Learning Overview')
            ->assertDontSee('Live Rooms')
            ->assertSee('Learning Library');
    }

    public function test_shared_learning_components_render(): void
    {
        $w = $this->world();
        $this->actingAs($w['learner']);

        $video = $w['videos']['courseOpen']->load(['category', 'instructor']);
        $video->update(['duration_seconds' => 3725]);
        $progress = LearningVideoProgress::create(['user_id' => $w['learner']->id, 'learning_video_id' => $video->id, 'percent' => 40]);

        $html = $this->blade('<x-learning.video-card :video="$video" :progress="$progress" />', compact('video', 'progress'));
        $html->assertSee(route('learn.videos.show', $video), false)->assertSee('1:02:05')->assertSee('40% watched');

        $course = $w['courses']['open']->loadCount('videos')->load('category');
        $this->blade('<x-learning.course-card :course="$course" :progress="[\'total\' => 4, \'completed\' => 1, \'percent\' => 25]" />', compact('course'))
            ->assertSee($course->title)->assertSee('1 of 4 done')->assertSee('aria-valuenow="25"', false);

        $live = $w['rooms']['categoryEnrolled']->load(['host', 'category']);
        $this->blade('<x-learning.room-card :room="$live" />', compact('live'))
            ->assertSee('LIVE')->assertSee('Join now')->assertSee(route('learn.rooms.live', $live), false);

        $scheduled = $w['rooms']['public'];
        $this->blade('<x-learning.room-card :room="$scheduled" />', compact('scheduled'))
            ->assertSee('Scheduled')->assertSee('View details')->assertSee($scheduled->scheduled_at->format('D, d M Y · H:i'));

        foreach (['completed' => 'Completed', 'cancelled' => 'Cancelled', 'draft' => 'Draft'] as $status => $label) {
            $this->blade('<x-learning.room-status :status="$status" />', compact('status'))->assertSee($label);
        }

        $this->blade('<x-learning.progress-bar :percent="140" label="Course" size="md" />')
            ->assertSee('100%')->assertSee('h-2.5', false);

        $this->blade('<x-learning.confirm-delete action="/x" title="Delete room?" :impact="[\'3 session records\']"><input type="hidden" name="extra" value="1"></x-learning.confirm-delete>')
            ->assertSee('Delete room?')->assertSee('3 session records')->assertSee('name="reason"', false)
            ->assertSee('name="_method" value="DELETE"', false)->assertSee('name="_token"', false)
            ->assertSee('name="extra"', false)->assertSee('aria-modal="true"', false);

        $this->blade('<x-learning.empty title="Nothing yet" message="Check back soon" action-href="/learn" action-label="Browse" />')
            ->assertSee('Nothing yet')->assertSee('Browse');

        $this->blade('<x-learning.thumb src="/t.webp" alt="Cover" />')->assertSee('loading="lazy"', false);
        $this->blade('<x-learning.thumb />')->assertDontSee('<img', false);

        $this->blade('<x-mbui.status-badge status="live" />')->assertSee('bg-red-50', false);

        $this->blade('<x-layouts.classroom title="Live class"><main id="stage">Stage</main></x-layouts.classroom>')
            ->assertSee('Live class')->assertSee('id="stage"', false)->assertSee('h-screen', false)->assertSee('csrf-token', false);
    }

    public function test_studio_is_closed_to_plain_members(): void
    {
        $this->actingAs($this->learner())->get(route('studio.rooms.index'))->assertForbidden();
        $this->actingAs($this->restrictedAdmin(['orders.view']))->get(route('admin.learning.dashboard'))->assertForbidden();
    }
}
