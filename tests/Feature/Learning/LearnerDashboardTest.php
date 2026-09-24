<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\User;
use App\Services\Learning\RoomService;
use Database\Seeders\LearningDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Learner-facing overview pages (My Learning dashboard, progress, calendar,
 * search), the mobile learning API, the /dashboard widget, the AuthController
 * payload and the demo seeder — with a focus on never leaking content the
 * viewer may not see.
 */
class LearnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        config(['learning.live.provider' => 'jitsi', 'learning.live.jitsi.domain' => 'meet.jit.si']);
        Notification::fake();
    }

    // --- Fixtures --------------------------------------------------------
    private function category(string $name): LearningCategory
    {
        return LearningCategory::create(['name' => $name]);
    }

    private function course(LearningCategory $category, string $title, array $attrs = []): LearningCourse
    {
        return LearningCourse::create(array_merge([
            'learning_category_id' => $category->id,
            'title' => $title,
            'status' => 'published',
            'access' => 'open',
            'published_at' => now(),
        ], $attrs));
    }

    private function video(LearningCategory $category, ?LearningCourse $course, string $title, array $attrs = [], bool $withFile = true): LearningVideo
    {
        $data = [
            'learning_category_id' => $category->id,
            'learning_course_id' => $course?->id,
            'title' => $title,
            'status' => 'published',
            'visibility' => 'course',
            'published_at' => now(),
            'duration_seconds' => 300,
        ];

        if ($withFile) {
            $path = 'learning/videos/'.Str::random(24).'.mp4';
            Storage::disk('private')->put($path, str_repeat('0123456789', 100));
            $data += ['disk' => 'private', 'path' => $path, 'mime' => 'video/mp4', 'size_bytes' => 1000, 'height' => 720];
        }

        return LearningVideo::create(array_merge($data, $attrs));
    }

    private function room(User $host, string $title, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => $title,
            'host_id' => $host->id,
            'created_by' => $host->id,
            'status' => 'scheduled',
            'access' => 'public',
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60,
        ], $attrs));
    }

    /**
     * Visible to the learner: "Open course", "Visible lesson", "Public class",
     * "Instructor Ada". Never visible: everything named "Secret …".
     */
    private function world(): array
    {
        $learner = User::factory()->create();
        $instructor = User::factory()->create(['can_teach' => true, 'name' => 'Instructor Ada', 'email' => 'ada-private@example.test']);
        $programming = $this->category('Programming');

        $open = $this->course($programming, 'Open course', ['instructor_id' => $instructor->id]);
        $topic = LearningTopic::create(['learning_course_id' => $open->id, 'title' => 'Visible topic']);
        $visible = $this->video($programming, $open, 'Visible lesson', ['learning_topic_id' => $topic->id, 'instructor_id' => $instructor->id]);
        $second = $this->video($programming, $open, 'Second lesson');

        $enrolledOnly = $this->course($programming, 'Secret enrolled course', ['access' => 'enrolled']);
        LearningTopic::create(['learning_course_id' => $enrolledOnly->id, 'title' => 'Secret topic']);
        $secretLesson = $this->video($programming, $enrolledOnly, 'Secret enrolled lesson');
        $this->course($programming, 'Secret draft course', ['status' => 'draft', 'published_at' => null]);
        $this->video($programming, $open, 'Secret draft lesson', ['status' => 'draft', 'published_at' => null]);
        $this->video($programming, null, 'Secret private lesson', ['visibility' => 'private']);

        $public = $this->room($instructor, 'Public class');
        $this->room($instructor, 'Secret private class', ['access' => 'private']);
        $this->room($instructor, 'Secret draft class', ['status' => 'draft']);
        $this->room($instructor, 'Secret course class', ['access' => 'course', 'learning_category_id' => $programming->id, 'learning_course_id' => $enrolledOnly->id]);

        return compact('learner', 'instructor', 'programming', 'open', 'visible', 'second', 'enrolledOnly', 'secretLesson', 'public');
    }

    // --- Dashboard & progress ------------------------------------------------
    public function test_dashboard_shows_every_section_with_data(): void
    {
        $w = $this->world();
        LearningEnrollment::create(['user_id' => $w['learner']->id, 'learning_course_id' => $w['open']->id, 'enrolled_at' => now()]);
        LearningVideoProgress::create(['user_id' => $w['learner']->id, 'learning_video_id' => $w['visible']->id, 'percent' => 100,
            'completed_at' => now(), 'last_watched_at' => now(), 'position_seconds' => 0, 'duration_seconds' => 300]);
        LearningVideoProgress::create(['user_id' => $w['learner']->id, 'learning_video_id' => $w['second']->id, 'percent' => 40,
            'last_watched_at' => now(), 'position_seconds' => 120, 'duration_seconds' => 300]);
        app(RoomService::class)->start($live = $this->room($w['instructor'], 'Live right now'), $w['instructor']);

        $this->actingAs($w['learner'])->get(route('learn.dashboard'))->assertOk()
            ->assertSee('Live right now')
            ->assertSee('Open course')
            ->assertSee('Second lesson')
            ->assertSee('Visible lesson')
            ->assertSee('Public class')
            ->assertSee('Programming')
            ->assertDontSee('Secret');

        $this->actingAs($w['learner'])->get(route('learn.progress'))->assertOk()
            ->assertSee('Open course')->assertSee('Visible lesson')->assertDontSee('Secret');
    }

    public function test_dashboard_and_progress_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('learn.dashboard'))->assertOk()
            ->assertSee("You haven't started any lessons yet.")
            ->assertSee('No upcoming classes.');
        $this->actingAs($user)->get(route('learn.progress'))->assertOk();
    }

    // --- Calendar --------------------------------------------------------------
    public function test_calendar_month_navigation_and_visibility(): void
    {
        $w = $this->world();
        $month = $w['public']->scheduled_at->format('Y-m');

        $this->actingAs($w['learner'])->get(route('learn.calendar', ['month' => $month]))->assertOk()
            ->assertSee('Public class')
            ->assertDontSee('Secret private class')
            ->assertDontSee('Secret draft class')
            ->assertDontSee('Secret course class');

        $this->actingAs($w['learner'])->get(route('learn.calendar', ['month' => '2031-01']))->assertOk()->assertDontSee('Public class');
        $this->actingAs($w['learner'])->get(route('learn.calendar'))->assertOk();

        foreach (['2026-13', 'not-a-month', '1999-01'] as $bad) {
            $this->actingAs($w['learner'])->get(route('learn.calendar', ['month' => $bad]))
                ->assertRedirect(route('learn.calendar'))->assertSessionHas('error');
        }

        // The host sees their own draft.
        $this->actingAs($w['instructor'])->get(route('learn.calendar', ['month' => $month]))->assertOk()->assertSee('Secret private class');
    }

    // --- Search -------------------------------------------------------------------
    public function test_search_every_type_without_leaks(): void
    {
        $w = $this->world();
        $learner = $w['learner'];

        $this->actingAs($learner)->get(route('learn.search'))->assertOk();

        $all = $this->actingAs($learner)->get(route('learn.search', ['q' => 's']))->assertOk();
        $all->assertSee('Open course')->assertSee('Visible lesson')->assertSee('Public class')->assertDontSee('Secret');

        $cases = [
            'videos' => 'Visible lesson',
            'courses' => 'Open course',
            'categories' => 'Programming',
            'topics' => 'Visible topic',
            'rooms' => 'Public class',
            'instructors' => 'Instructor Ada',
        ];
        foreach ($cases as $type => $expected) {
            $this->actingAs($learner)->get(route('learn.search', ['type' => $type, 'q' => '']))->assertOk()
                ->assertSee($expected)->assertDontSee('Secret')->assertDontSee('ada-private@example.test');
        }

        $this->actingAs($learner)->get(route('learn.search', ['q' => 'Secret']))->assertOk()->assertDontSee('Secret enrolled lesson');

        // Filters narrow the results; unknown values fall back safely.
        $this->actingAs($learner)->get(route('learn.search', ['type' => 'videos', 'duration' => 'long']))->assertOk()->assertDontSee('Visible lesson');
        $this->actingAs($learner)->get(route('learn.search', ['type' => 'videos', 'duration' => 'short', 'sort' => 'title']))->assertOk()->assertSee('Visible lesson');
        $this->actingAs($learner)->get(route('learn.search', ['type' => 'bogus', 'sort' => 'bogus']))->assertOk();

        // Once enrolled, the enrolled-only course and its lesson appear.
        LearningEnrollment::create(['user_id' => $learner->id, 'learning_course_id' => $w['enrolledOnly']->id, 'enrolled_at' => now()]);
        $this->actingAs($learner)->get(route('learn.search', ['type' => 'videos', 'q' => 'enrolled']))->assertOk()->assertSee('Secret enrolled lesson');
    }

    // --- API --------------------------------------------------------------------------
    public function test_api_endpoints_shapes_pagination_and_permissions(): void
    {
        $w = $this->world();
        Sanctum::actingAs($w['learner']);

        $this->getJson(route('api.learning.home'))->assertOk()
            ->assertJsonStructure(['stats', 'live', 'continue_watching', 'my_courses', 'upcoming', 'recently_watched', 'completed']);

        $this->getJson(route('api.learning.categories'))->assertOk()->assertJsonFragment(['name' => 'Programming']);

        $courses = $this->getJson(route('api.learning.courses'))->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
        $this->assertSame(['Open course'], collect($courses->json('data'))->pluck('title')->all()); // enrolled-only courses stay hidden until enrolled

        $videos = $this->getJson(route('api.learning.videos'))->assertOk();
        $titles = collect($videos->json('data'))->pluck('title');
        $this->assertTrue($titles->contains('Visible lesson'));
        $this->assertFalse($titles->contains(fn ($t) => str_starts_with($t, 'Secret')));

        $this->getJson(route('api.learning.courses.show', $w['open']->slug))->assertOk();
        $this->getJson(route('api.learning.videos.show', $w['secretLesson']->slug))->assertForbidden();
        $this->getJson(route('api.learning.videos.show', 'no-such-lesson'))->assertNotFound();

        $this->postJson(route('api.learning.courses.enroll', $w['open']->slug))->assertSuccessful();
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $w['learner']->id, 'learning_course_id' => $w['open']->id]);

        $this->postJson(route('api.learning.videos.progress', $w['visible']->slug), ['position' => 30, 'duration' => 300, 'event' => 'tick', 'watched' => 30])
            ->assertOk()->assertJsonStructure(['percent', 'completed', 'position_seconds', 'last_watched_at']);
        $this->postJson(route('api.learning.videos.progress', $w['visible']->slug), ['position' => -1, 'event' => 'nope'])->assertStatus(422);
        $this->postJson(route('api.learning.videos.complete', $w['visible']->slug), ['completed' => true])->assertOk();
        $this->assertNotNull($w['visible']->progressFor($w['learner'])?->completed_at);

        $rooms = $this->getJson(route('api.learning.rooms'))->assertOk();
        $this->assertSame(['Public class'], collect($rooms->json('data'))->pluck('title')->all());
        $this->getJson(route('api.learning.rooms.show', $w['public']->slug))->assertOk();
        $this->postJson(route('api.learning.rooms.join', $w['public']->slug))->assertStatus(409)->assertJson(['reason' => 'not_live']);

        app(RoomService::class)->start($w['public'], $w['instructor']);
        $this->postJson(route('api.learning.rooms.join', $w['public']->slug))->assertOk()->assertJsonStructure(['config' => ['domain', 'roomName']]);

        $this->getJson(route('api.learning.rooms.show', LearningRoom::where('title', 'Secret private class')->value('slug')))->assertForbidden();
    }

    public function test_api_signed_stream_url_plays_on_the_web_route_and_rejects_tampering(): void
    {
        $w = $this->world();
        Sanctum::actingAs($w['learner']);

        $url = $this->getJson(route('api.learning.videos.show', $w['visible']->slug))->assertOk()->json('data.stream_url');
        $this->assertNotEmpty($url);

        // The signed route authenticates by its signature, not by a session.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk();
        $this->get($url.'x')->assertForbidden();
        $this->get(preg_replace('/([?&]u=)\d+/', '${1}'.User::factory()->create(['status' => 'suspended'])->id, $url))->assertForbidden();
    }

    public function test_auth_payload_has_can_teach(): void
    {
        $instructor = User::factory()->create(['can_teach' => true, 'password' => bcrypt('secret-pass')]);
        Sanctum::actingAs($instructor);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->getActionName(), 'Api\AuthController@') && in_array('GET', $r->methods(), true))
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'));

        $found = false;
        foreach ($routes as $uri) {
            $json = $this->getJson($uri)->json();
            if (is_array($json) && data_get($json, 'user.can_teach', data_get($json, 'can_teach', data_get($json, 'data.can_teach'))) === true) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'No AuthController GET endpoint returned can_teach = true.');
    }

    // --- /dashboard widget ----------------------------------------------------------------
    public function test_user_dashboard_shows_my_learning_and_survives_without_learning_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('My Learning');

        $w = $this->world();
        app(RoomService::class)->start($w['public'], $w['instructor']);
        $this->actingAs($w['learner'])->get(route('dashboard'))->assertOk()->assertSee('Public class')->assertDontSee('Secret');
    }

    // --- Seeder ------------------------------------------------------------------------------
    public function test_demo_seeder_without_sample_videos_keeps_lessons_as_drafts(): void
    {
        config(['learning.demo_video_dir' => storage_path('framework/testing/no-such-dir')]);

        $this->seed(LearningDemoSeeder::class);

        $counts = fn () => [
            LearningCategory::count(), LearningCourse::count(), LearningTopic::count(), LearningVideo::count(),
            LearningRoom::count(), LearningEnrollment::count(), User::count(),
        ];
        $first = $counts();

        $this->assertSame(7, $first[0]);
        $this->assertGreaterThanOrEqual(4, $first[1]);
        $this->assertSame(0, LearningVideo::published()->count(), 'Without sample files, demo lessons must stay drafts.');
        $this->assertEqualsCanonicalizing(
            ['draft', 'scheduled', 'live', 'completed', 'cancelled'],
            LearningRoom::query()->distinct()->pluck('status')->all(),
        );
        $this->assertTrue(User::where('email', 'instructor@example.test')->value('can_teach'));
        $this->assertSame(1, LearningCourse::onlyTrashed()->count());
        $this->assertSame(1, LearningRoom::onlyTrashed()->count());
        $this->assertSame(1, LearningCategory::onlyTrashed()->count());

        $this->seed(LearningDemoSeeder::class);
        $this->assertSame($first, $counts());
        $this->assertSame(1, LearningRoom::where('status', 'live')->firstOrFail()->sessions()->whereNull('ended_at')->count());
    }

    public function test_demo_seeder_with_sample_videos_fills_every_section_once(): void
    {
        $dir = storage_path('framework/testing/learning-demo-'.getmypid());
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/sample.mp4', str_repeat('0123456789', 50));
        config(['learning.demo_video_dir' => $dir]);

        try {
            $this->seed(LearningDemoSeeder::class);

            $published = LearningVideo::published()->get();
            $this->assertGreaterThan(10, $published->count());
            $this->assertSame(1, LearningVideo::where('status', 'draft')->count(), 'One lesson stays a draft on purpose.');
            foreach ($published as $video) {
                $this->assertTrue(Storage::disk('private')->exists($video->path));
            }
            $this->assertCount($published->count(), $published->pluck('path')->unique(), 'Each lesson owns its own file.');

            $learner = User::where('email', 'learner@example.test')->firstOrFail();
            $this->assertGreaterThan(0, LearningVideoProgress::where('user_id', $learner->id)->whereNotNull('completed_at')->count());
            $this->assertDatabaseHas('learning_video_resources', ['type' => 'file', 'title' => 'Python cheat sheet']);
            $this->assertDatabaseHas('learning_video_comments', ['user_id' => $learner->id]);
            $this->assertDatabaseHas('learning_room_members', ['user_id' => $learner->id]);
            $this->assertDatabaseHas('learning_room_recordings', ['status' => 'ready', 'is_shared' => true]);
            $this->assertDatabaseHas('learning_room_messages', ['type' => 'question', 'is_answered' => true]);
            $this->assertGreaterThanOrEqual(4, \App\Models\LearningRoomAttendance::count());

            $tables = ['learning_videos', 'learning_video_progress', 'learning_video_resources', 'learning_video_comments',
                'learning_room_messages', 'learning_room_attendances', 'learning_room_recordings', 'learning_room_members'];
            $before = collect($tables)->mapWithKeys(fn ($t) => [$t => \DB::table($t)->count()])->all();

            $this->seed(LearningDemoSeeder::class);
            $this->assertSame($before, collect($tables)->mapWithKeys(fn ($t) => [$t => \DB::table($t)->count()])->all());

            // The learner pages show the seeded activity.
            $this->actingAs($learner)->get(route('learn.dashboard'))->assertOk()->assertSee('Python for Beginners')->assertSee('Demo Live Study Hall');
            $this->actingAs($learner)->get(route('learn.rooms.index', ['tab' => 'all']))->assertOk()->assertSee('Demo Mentorship Circle');
        } finally {
            (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($dir);
        }
    }
}
