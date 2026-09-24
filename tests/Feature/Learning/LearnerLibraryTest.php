<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningVideo;
use App\Models\LearningVideoComment;
use App\Models\LearningVideoProgress;
use App\Models\LearningVideoRendition;
use App\Models\LearningVideoResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * V1 — learner video library & player: catalogue pages, visibility, enrolment,
 * player page states, private media streaming, progress beacons, comments and
 * instructor profiles.
 */
class LearnerLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
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

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => 'super_admin', 'permissions' => null]);
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
            'access' => 'open',
            'published_at' => now(),
        ], $attrs));
    }

    private function video(LearningCategory $category, ?LearningCourse $course = null, array $attrs = [], bool $withFile = true): LearningVideo
    {
        $data = [
            'learning_category_id' => $category->id,
            'learning_course_id' => $course?->id,
            'title' => 'Lesson '.uniqid(),
            'status' => 'published',
            'visibility' => 'course',
            'published_at' => now(),
            'duration_seconds' => 100,
        ];

        if ($withFile) {
            $path = 'learning/videos/'.Str::random(24).'.mp4';
            Storage::disk('private')->put($path, str_repeat('0123456789', 100)); // 1000 bytes
            $data += ['disk' => 'private', 'path' => $path, 'mime' => 'video/mp4', 'size_bytes' => 1000, 'height' => 720];
        }

        return LearningVideo::create(array_merge($data, $attrs));
    }

    private function room(User $host, array $attrs = []): LearningRoom
    {
        return LearningRoom::create(array_merge([
            'title' => 'Room '.uniqid(),
            'host_id' => $host->id,
            'status' => 'completed',
            'access' => 'public',
            'scheduled_at' => now()->subDay(),
        ], $attrs));
    }

    // --- Library pages -----------------------------------------------------
    public function test_library_pages_render_with_data(): void
    {
        $teacher = $this->instructor();
        $category = $this->category('Mathematics');
        $course = $this->course($category, ['title' => 'Algebra Basics', 'instructor_id' => $teacher->id]);
        $topic = $course->topics()->create(['title' => 'Linear equations', 'position' => 1]);
        $lesson = $this->video($category, $course, ['title' => 'Solving for x', 'learning_topic_id' => $topic->id, 'instructor_id' => $teacher->id]);
        $loose = $this->video($category, $course, ['title' => 'Course wrap-up']);
        $user = $this->member();

        $this->actingAs($user)->get(route('learn.categories.index'))
            ->assertOk()->assertSee('Mathematics')->assertSee('1 course')->assertSee('2 lessons');

        $this->actingAs($user)->get(route('learn.categories.show', $category))
            ->assertOk()->assertSee('Algebra Basics')->assertSee('Solving for x');

        $this->actingAs($user)->get(route('learn.categories.show', ['category' => $category, 'sort' => 'popular']))
            ->assertOk()->assertSee('Solving for x');

        $this->actingAs($user)->get(route('learn.courses.index', ['q' => 'Algebra', 'level' => '', 'sort' => 'title']))
            ->assertOk()->assertSee('Algebra Basics');

        $this->actingAs($user)->get(route('learn.courses.show', $course))
            ->assertOk()
            ->assertSee('Linear equations')
            ->assertSee('Solving for x')
            ->assertSee('Other lessons')
            ->assertSee('Course wrap-up')
            ->assertSee('Start course')
            ->assertSee('Enrol in this course')
            ->assertSee(route('learn.instructors.show', $teacher), false);

        $this->actingAs($user)->get(route('learn.videos.index', ['category' => $category->slug, 'duration' => 'short', 'sort' => 'duration']))
            ->assertOk()->assertSee('Solving for x')->assertSee('Course wrap-up');

        $this->actingAs($user)->get(route('learn.videos.index', ['duration' => 'long']))
            ->assertOk()->assertSee('No lessons match your filters')->assertDontSee('Solving for x');

        // "My courses" only lists courses the viewer enrolled in or started.
        $this->actingAs($user)->get(route('learn.courses.index', ['mine' => 1]))
            ->assertOk()->assertSee('You have no courses yet');
        LearningVideoProgress::create(['user_id' => $user->id, 'learning_video_id' => $loose->id, 'position_seconds' => 10, 'percent' => 10]);
        $this->actingAs($user)->get(route('learn.courses.index', ['mine' => 1]))
            ->assertOk()->assertSee('Algebra Basics');

        $this->actingAs($user)->get(route('learn.videos.index', ['status' => 'in_progress']))
            ->assertOk()->assertSee('Course wrap-up')->assertDontSee('Solving for x');
        $this->actingAs($user)->get(route('learn.videos.index', ['status' => 'not_started']))
            ->assertOk()->assertSee('Solving for x')->assertDontSee('Course wrap-up');
    }

    public function test_library_pages_show_empty_states(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('learn.categories.index'))->assertOk()->assertSee('No categories yet');
        $this->actingAs($user)->get(route('learn.courses.index'))->assertOk()->assertSee('No courses available yet');
        $this->actingAs($user)->get(route('learn.videos.index'))->assertOk()->assertSee('No video lessons yet');
        $this->actingAs($user)->get(route('learn.videos.index', ['status' => 'completed']))->assertOk()->assertSee('No completed lessons yet');

        $category = $this->category('Empty corner');
        $this->actingAs($user)->get(route('learn.categories.show', $category))
            ->assertOk()->assertSee('No videos available in this category.');

        $course = $this->course($category, ['title' => 'Hollow course']);
        $this->actingAs($user)->get(route('learn.courses.show', $course))
            ->assertOk()->assertSee('No lessons yet')->assertSee('No upcoming live sessions for this course.');
    }

    public function test_visibility_never_leaks_hidden_lessons(): void
    {
        $category = $this->category();
        $open = $this->course($category, ['title' => 'Open course']);
        $closed = $this->course($category, ['title' => 'Closed cohort', 'access' => 'enrolled']);
        $this->video($category, $open, ['title' => 'Visible lesson']);
        $secret = $this->video($category, $closed, ['title' => 'Cohort-only lesson']);
        $draft = $this->video($category, $open, ['title' => 'Unfinished draft', 'status' => 'draft']);
        $private = $this->video($category, $open, ['title' => 'Staff-only lesson', 'visibility' => 'private']);
        $user = $this->member();

        foreach ([
            route('learn.videos.index'),
            route('learn.categories.show', $category),
            route('learn.courses.index'),
            route('learn.courses.show', $open),
            route('learn.videos.index', ['q' => 'lesson']),
        ] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertDontSee('Cohort-only lesson')
                ->assertDontSee('Unfinished draft')
                ->assertDontSee('Staff-only lesson')
                ->assertDontSee('Closed cohort');
        }

        $this->actingAs($user)->get(route('learn.courses.show', $closed))->assertForbidden();

        // Locked page: friendly, still 403, and reveals nothing about the course.
        $this->actingAs($user)->get(route('learn.videos.show', $secret))
            ->assertForbidden()
            ->assertSee('This lesson is for enrolled learners')
            ->assertDontSee('Cohort-only lesson')
            ->assertDontSee('Closed cohort');
        $this->actingAs($user)->get(route('learn.videos.show', $draft))->assertForbidden()->assertDontSee('Unfinished draft');
        $this->actingAs($user)->get(route('learn.videos.show', $private))->assertForbidden()->assertDontSee('Staff-only lesson');

        // Once an administrator enrols the learner the lesson appears.
        LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $closed->id, 'source' => 'admin', 'enrolled_at' => now()]);
        $this->actingAs($user)->get(route('learn.videos.index'))->assertOk()->assertSee('Cohort-only lesson');
        $this->actingAs($user)->get(route('learn.courses.show', $closed))
            ->assertOk()->assertSee('An administrator enrolled you in this course.')->assertDontSee('Leave course');
    }

    public function test_enroll_and_unenroll_rules(): void
    {
        $category = $this->category();
        $open = $this->course($category, ['title' => 'Open course']);
        $closed = $this->course($category, ['access' => 'enrolled']);
        $draft = $this->course($category, ['status' => 'draft']);
        $user = $this->member();

        $this->actingAs($user)->post(route('learn.courses.enroll', $open))
            ->assertRedirect(route('learn.courses.show', $open))->assertSessionHas('success');
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $user->id, 'learning_course_id' => $open->id, 'source' => 'self']);

        // Enrolling twice is harmless.
        $this->actingAs($user)->post(route('learn.courses.enroll', $open))->assertSessionHas('success');
        $this->assertSame(1, LearningEnrollment::where('user_id', $user->id)->count());

        $this->actingAs($user)->get(route('learn.courses.show', $open))->assertOk()->assertSee('Leave course');

        $this->actingAs($user)->delete(route('learn.courses.unenroll', $open))
            ->assertRedirect(route('learn.courses.show', $open))->assertSessionHas('success');
        $this->assertDatabaseMissing('learning_enrollments', ['user_id' => $user->id, 'learning_course_id' => $open->id]);

        $this->actingAs($user)->post(route('learn.courses.enroll', $closed))->assertForbidden();
        $this->actingAs($user)->post(route('learn.courses.enroll', $draft))->assertForbidden();

        // Super admins pass the policy, but the state rule still applies.
        $this->actingAs($this->admin())->post(route('learn.courses.enroll', $draft))->assertSessionHas('error');
        $this->assertSame(0, LearningEnrollment::where('learning_course_id', $draft->id)->count());

        // Administrator enrolments cannot be removed by the learner.
        LearningEnrollment::create(['user_id' => $user->id, 'learning_course_id' => $closed->id, 'source' => 'admin', 'enrolled_at' => now()]);
        $this->actingAs($user)->delete(route('learn.courses.unenroll', $closed))->assertSessionHas('error');
        $this->assertDatabaseHas('learning_enrollments', ['user_id' => $user->id, 'learning_course_id' => $closed->id]);
    }

    // --- Player page -------------------------------------------------------
    public function test_player_page_states(): void
    {
        $teacher = $this->instructor();
        $category = $this->category();
        $course = $this->course($category, ['title' => 'Physics']);
        $first = $this->video($category, $course, ['title' => 'Motion', 'position' => 1, 'instructor_id' => $teacher->id]);
        $second = $this->video($category, $course, ['title' => 'Forces', 'position' => 2]);
        $user = $this->member();

        LearningVideoProgress::create(['user_id' => $user->id, 'learning_video_id' => $first->id, 'position_seconds' => 42, 'max_position_seconds' => 42, 'percent' => 42]);

        $this->actingAs($user)->get(route('learn.videos.show', $first))
            ->assertOk()
            ->assertSee('learnVideoPlayer', false)
            ->assertSee('controlsList="nodownload"', false)
            ->assertSee('playsinline', false)
            ->assertSee(route('learn.videos.stream', $first), false)
            ->assertSee('720p (original)')
            ->assertSee('Course content')
            ->assertSee('Next lesson')
            ->assertSee('Forces')
            ->assertSee('42% watched')
            ->assertSee(route('learn.instructors.show', $teacher), false)
            ->assertViewHas('config', fn ($cfg) => $cfg['resumeAt'] === 42
                && $cfg['nextUrl'] === route('learn.videos.show', $second)
                && count($cfg['sources']) === 1);

        // Published but no file yet.
        $pending = $this->video($category, $course, ['title' => 'Coming soon'], withFile: false);
        $this->actingAs($user)->get(route('learn.videos.show', $pending))
            ->assertOk()->assertSee('This lesson is being prepared')->assertDontSee('learnVideoPlayer', false);

        // Draft preview for the owning instructor.
        $draft = $this->video($category, null, ['title' => 'Owner draft', 'status' => 'draft', 'instructor_id' => $teacher->id]);
        $this->actingAs($teacher)->get(route('learn.videos.show', $draft))
            ->assertOk()->assertSee('Preview')->assertSee('This lesson is a draft.')->assertSee('learnVideoPlayer', false);

        // Private lesson previewed by its instructor.
        $private = $this->video($category, null, ['title' => 'Hidden one', 'visibility' => 'private', 'instructor_id' => $teacher->id]);
        $this->actingAs($teacher)->get(route('learn.videos.show', $private))
            ->assertOk()->assertSee('This lesson is private');
    }

    // --- Media -------------------------------------------------------------
    public function test_stream_serves_allowed_viewers_with_range_support(): void
    {
        $category = $this->category();
        $open = $this->course($category);
        $closed = $this->course($category, ['access' => 'enrolled']);
        $video = $this->video($category, $open);
        $secret = $this->video($category, $closed);
        $user = $this->member();

        $this->actingAs($user)->get(route('learn.videos.stream', $video))
            ->assertOk()->assertHeader('Content-Type', 'video/mp4');

        $partial = $this->actingAs($user)->get(route('learn.videos.stream', $video), ['Range' => 'bytes=0-9']);
        $partial->assertStatus(206);
        $this->assertSame('bytes 0-9/1000', $partial->headers->get('Content-Range'));

        $this->actingAs($user)->get(route('learn.videos.stream', $secret))->assertForbidden();

        // Renditions: own one streams, another video's one is a 404.
        Storage::disk('private')->put('learning/renditions/a.mp4', 'rendition-bytes');
        $own = LearningVideoRendition::create(['learning_video_id' => $video->id, 'quality' => '360p', 'height' => 360, 'disk' => 'private', 'path' => 'learning/renditions/a.mp4', 'mime' => 'video/mp4', 'size_bytes' => 15]);
        $foreign = LearningVideoRendition::create(['learning_video_id' => $secret->id, 'quality' => '360p', 'height' => 360, 'disk' => 'private', 'path' => 'learning/renditions/a.mp4', 'mime' => 'video/mp4', 'size_bytes' => 15]);
        $this->actingAs($user)->get(route('learn.videos.stream', [$video, $own]))->assertOk();
        $this->actingAs($user)->get(route('learn.videos.stream', [$video, $foreign]))->assertNotFound();

        // Drafts only stream for people who may edit them.
        $draft = $this->video($category, null, ['status' => 'draft']);
        $viewer = $this->member(['is_admin' => true, 'role' => 'admin', 'permissions' => ['learning.view']]);
        $this->actingAs($viewer)->get(route('learn.videos.stream', $draft))->assertForbidden();
        $this->actingAs($this->admin())->get(route('learn.videos.stream', $draft))->assertOk();

        // No file → 404.
        $empty = $this->video($category, $open, [], withFile: false);
        $this->actingAs($user)->get(route('learn.videos.stream', $empty))->assertNotFound();
    }

    public function test_resource_download_uses_a_slugged_attachment_name(): void
    {
        $category = $this->category();
        $video = $this->video($category, $this->course($category));
        Storage::disk('private')->put('learning/resources/x.pdf', '%PDF-1.4 test');
        $file = LearningVideoResource::create([
            'learning_video_id' => $video->id, 'title' => 'Lecture Notes', 'type' => 'file',
            'disk' => 'private', 'path' => 'learning/resources/x.pdf', 'original_name' => 'notes-final.pdf',
            'mime' => 'application/pdf', 'size_bytes' => 13,
        ]);
        $link = LearningVideoResource::create(['learning_video_id' => $video->id, 'title' => 'Docs', 'type' => 'link', 'url' => 'https://example.com/docs']);
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('learn.videos.resource', [$video, $file]));
        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('lecture-notes.pdf', $response->headers->get('Content-Disposition'));

        $this->actingAs($user)->get(route('learn.videos.resource', [$video, $link]))->assertNotFound();

        // The player page lists both, the link opening safely in a new tab.
        $this->actingAs($user)->get(route('learn.videos.show', $video))
            ->assertOk()
            ->assertSee(route('learn.videos.resource', [$video, $file]), false)
            ->assertSee('rel="noopener noreferrer nofollow"', false);

        // A resource of another lesson never resolves through this lesson.
        $other = $this->video($category);
        $this->actingAs($user)->get(route('learn.videos.resource', [$other, $file]))->assertNotFound();
    }

    public function test_recording_stream_rules(): void
    {
        $host = $this->instructor();
        $room = $this->room($host);
        Storage::disk('private')->put('learning/recordings/r.mp4', 'recording-bytes');
        $recording = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'source' => 'upload', 'status' => 'ready',
            'disk' => 'private', 'path' => 'learning/recordings/r.mp4', 'mime' => 'video/mp4', 'is_shared' => false,
        ]);
        $member = $this->member();

        $this->actingAs($member)->get(route('learn.rooms.recordings.stream', [$room, $recording]))->assertForbidden();
        $this->actingAs($host)->get(route('learn.rooms.recordings.stream', [$room, $recording]))->assertOk();

        $recording->update(['is_shared' => true]);
        $this->actingAs($member)->get(route('learn.rooms.recordings.stream', [$room, $recording]))->assertOk();

        $recording->update(['status' => 'processing']);
        $this->actingAs($member)->get(route('learn.rooms.recordings.stream', [$room, $recording]))->assertNotFound();

        // Invite-only room: outsiders cannot even see it.
        $recording->update(['status' => 'ready']);
        $room->update(['access' => 'private']);
        $this->actingAs($member)->get(route('learn.rooms.recordings.stream', [$room, $recording]))->assertForbidden();

        // A recording of another room never resolves through this room.
        $otherRoom = $this->room($host);
        $this->actingAs($host)->get(route('learn.rooms.recordings.stream', [$otherRoom, $recording]))->assertNotFound();
    }

    public function test_signed_stream_requires_a_valid_signature_and_an_allowed_active_user(): void
    {
        $category = $this->category();
        $video = $this->video($category, $this->course($category));
        $secret = $this->video($category, $this->course($category, ['access' => 'enrolled']));
        $user = $this->member();
        $other = $this->member();

        $url = URL::temporarySignedRoute('signed.learning.videos.stream', now()->addHour(), ['video' => $video->id, 'u' => $user->id]);
        $this->get($url)->assertOk();

        // Tampering with ?u= breaks the signature.
        $this->get(str_replace('u='.$user->id, 'u='.$other->id, $url))->assertForbidden();

        $expired = URL::temporarySignedRoute('signed.learning.videos.stream', now()->subMinute(), ['video' => $video->id, 'u' => $user->id]);
        $this->get($expired)->assertForbidden();

        $denied = URL::temporarySignedRoute('signed.learning.videos.stream', now()->addHour(), ['video' => $secret->id, 'u' => $user->id]);
        $this->get($denied)->assertForbidden();

        $user->update(['status' => 'suspended']);
        $this->get($url)->assertForbidden();
    }

    // --- Progress ----------------------------------------------------------
    public function test_progress_endpoint_validates_and_completes_at_the_threshold(): void
    {
        $category = $this->category();
        $video = $this->video($category, $this->course($category));
        $secret = $this->video($category, $this->course($category, ['access' => 'enrolled']));
        $user = $this->member();
        $url = route('learn.videos.progress', $video);

        $this->actingAs($user)->postJson($url, ['position' => -1, 'event' => 'tick'])->assertStatus(422)->assertJsonValidationErrors('position');
        $this->actingAs($user)->postJson($url, ['position' => 10, 'event' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors('event');
        $this->actingAs($user)->postJson($url, ['position' => 10, 'event' => 'tick', 'watched' => 601])->assertStatus(422)->assertJsonValidationErrors('watched');
        $this->actingAs($user)->postJson($url, ['position' => 90000, 'event' => 'tick'])->assertStatus(422);
        $this->actingAs($user)->postJson($url, ['position' => 1, 'duration' => 0, 'event' => 'tick'])->assertStatus(422)->assertJsonValidationErrors('duration');

        $this->actingAs($user)->postJson($url, ['position' => 0, 'duration' => 100, 'event' => 'start'])
            ->assertOk()->assertJson(['percent' => 0, 'completed' => false, 'position' => 0]);
        $this->assertSame(1, (int) $video->fresh()->views);

        $this->actingAs($user)->postJson($url, ['position' => 50, 'duration' => 100, 'event' => 'tick', 'watched' => 15])
            ->assertOk()->assertJson(['percent' => 50, 'completed' => false, 'position' => 50]);

        $this->actingAs($user)->postJson($url, ['position' => 90, 'duration' => 100, 'event' => 'tick', 'watched' => 15])
            ->assertOk()->assertJson(['percent' => 90, 'completed' => true]);

        // sendBeacon-style form post (no JSON Accept header) is accepted too.
        $this->actingAs($user)->post($url, ['position' => 91, 'duration' => 100, 'event' => 'pause', 'watched' => 1])->assertOk();

        $this->actingAs($user)->postJson(route('learn.videos.progress', $secret), ['position' => 1, 'event' => 'tick'])->assertForbidden();
    }

    public function test_complete_toggle(): void
    {
        $category = $this->category();
        $video = $this->video($category, $this->course($category));
        $user = $this->member();
        $url = route('learn.videos.complete', $video);

        $this->actingAs($user)->postJson($url, [])->assertStatus(422);

        $this->actingAs($user)->postJson($url, ['completed' => true])
            ->assertOk()->assertJson(['completed' => true, 'percent' => 100]);

        LearningVideoProgress::where('user_id', $user->id)->update(['position_seconds' => 70, 'max_position_seconds' => 95]);

        $this->actingAs($user)->postJson($url, ['completed' => false])
            ->assertOk()->assertJson(['completed' => false, 'percent' => 0, 'position' => 0]);

        $this->actingAs($user)->from(route('learn.videos.show', $video))->post($url, ['completed' => '1'])
            ->assertRedirect(route('learn.videos.show', $video))->assertSessionHas('success', 'Lesson marked as completed.');
    }

    // --- Comments ----------------------------------------------------------
    public function test_comments_create_reply_and_delete_permissions(): void
    {
        $teacher = $this->instructor();
        $category = $this->category();
        $video = $this->video($category, $this->course($category), ['instructor_id' => $teacher->id]);
        $secret = $this->video($category, $this->course($category, ['access' => 'enrolled']));
        $author = $this->member();
        $stranger = $this->member();

        $this->actingAs($author)->post(route('learn.videos.comments.store', $video), ['body' => 'What is inertia?'])
            ->assertRedirect()->assertSessionHas('success', 'Comment posted.');
        $comment = LearningVideoComment::firstOrFail();

        $this->actingAs($stranger)->post(route('learn.videos.comments.store', $video), ['body' => 'Resistance to change.', 'parent_id' => $comment->id])
            ->assertSessionHas('success', 'Reply posted.');
        $reply = LearningVideoComment::where('parent_id', $comment->id)->firstOrFail();

        // Only one reply level, and only on this lesson.
        $this->actingAs($stranger)->post(route('learn.videos.comments.store', $video), ['body' => 'Nested', 'parent_id' => $reply->id])
            ->assertSessionHasErrors('parent_id');
        $other = $this->video($category);
        $this->actingAs($stranger)->post(route('learn.videos.comments.store', $other), ['body' => 'Wrong lesson', 'parent_id' => $comment->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($author)->post(route('learn.videos.comments.store', $video), ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
        $this->actingAs($author)->post(route('learn.videos.comments.store', $video), ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($author)->post(route('learn.videos.comments.store', $secret), ['body' => 'Sneaky'])->assertForbidden();

        // The page shows the thread, escaped.
        LearningVideoComment::create(['learning_video_id' => $video->id, 'user_id' => $author->id, 'body' => '<script>alert(1)</script>']);
        $this->actingAs($author)->get(route('learn.videos.show', $video))
            ->assertOk()
            ->assertSee('What is inertia?')
            ->assertSee('Resistance to change.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);

        // Strangers cannot delete someone else's comment.
        $this->actingAs($stranger)->delete(route('learn.videos.comments.destroy', [$video, $comment]), ['reason' => 'nope'])->assertForbidden();
        $this->assertNotSoftDeleted($comment);

        // Author deletes their own reply; the lesson instructor deletes the top-level comment (+ replies).
        $this->actingAs($stranger)->delete(route('learn.videos.comments.destroy', [$video, $reply]), ['reason' => 'mine'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted($reply);

        $this->actingAs($teacher)->delete(route('learn.videos.comments.destroy', [$video, $comment]), ['reason' => 'off topic'])
            ->assertSessionHas('success');
        $this->assertSoftDeleted($comment);

        // Content managers may moderate any lesson.
        $third = LearningVideoComment::create(['learning_video_id' => $video->id, 'user_id' => $author->id, 'body' => 'Another']);
        $manager = $this->member(['is_admin' => true, 'role' => 'admin', 'permissions' => ['learning.manage']]);
        $this->actingAs($manager)->delete(route('learn.videos.comments.destroy', [$video, $third]), ['reason' => 'cleanup'])
            ->assertSessionHas('success');
        $this->assertSoftDeleted($third);

        // A comment of another lesson never resolves through this lesson.
        $foreign = LearningVideoComment::create(['learning_video_id' => $other->id, 'user_id' => $author->id, 'body' => 'Elsewhere']);
        $this->actingAs($author)->delete(route('learn.videos.comments.destroy', [$video, $foreign]))->assertNotFound();
    }

    // --- Instructors -------------------------------------------------------
    public function test_instructor_page_hides_email_and_404s_for_non_instructors(): void
    {
        $viewer = $this->member();
        $plain = $this->member();

        $this->actingAs($viewer)->get(route('learn.instructors.show', $plain))->assertNotFound();

        $teacher = User::factory()->create(['can_teach' => true, 'name' => 'Dr Amina Juma', 'email' => 'amina.private@example.com']);
        $this->actingAs($viewer)->get(route('learn.instructors.show', $teacher))
            ->assertOk()
            ->assertSee('Dr Amina Juma')
            ->assertSee('Member since')
            ->assertSee('No courses yet')
            ->assertDontSee('amina.private@example.com');

        // Former instructors with published content still have a profile.
        $category = $this->category();
        $former = User::factory()->create(['can_teach' => false, 'email' => 'former@example.com']);
        $course = $this->course($category, ['title' => 'Legacy course', 'instructor_id' => $former->id]);
        $this->video($category, $course, ['title' => 'Legacy lesson', 'instructor_id' => $former->id]);
        $this->video($category, null, ['title' => 'Hidden legacy', 'instructor_id' => $former->id, 'status' => 'draft']);

        $this->actingAs($viewer)->get(route('learn.instructors.show', $former))
            ->assertOk()
            ->assertSee('Legacy course')
            ->assertSee('Legacy lesson')
            ->assertDontSee('Hidden legacy')
            ->assertDontSee('former@example.com');
    }
}
