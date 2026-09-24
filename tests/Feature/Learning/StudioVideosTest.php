<?php

namespace Tests\Feature\Learning;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoProgress;
use App\Models\LearningVideoRendition;
use App\Models\LearningVideoResource;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Teaching Studio — video lessons: permission matrix, the chunked upload
 * endpoints, the create/edit/publish/replace flows, renditions, resources,
 * deletion and the member search used by the user picker.
 */
class StudioVideosTest extends TestCase
{
    use RefreshDatabase;

    private const DISKS = ['private', 'public', 'local'];

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Like Storage::fake(), but in a folder of our own: Storage::fake() always uses
        // storage/framework/testing/disks/{disk}, which another test process running at the
        // same time would wipe in the middle of a chunked upload.
        $this->diskRoot = storage_path('framework/testing/disks/studio-videos-'.getmypid());
        foreach (self::DISKS as $disk) {
            $root = $this->diskRoot.'/'.$disk;
            (new Filesystem)->cleanDirectory($root);
            Storage::set($disk, Storage::createLocalDriver(array_merge(
                (array) config("filesystems.disks.{$disk}", []),
                ['driver' => 'local', 'root' => $root, 'throw' => false],
            )));
        }

        Notification::fake();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    // --- Fixtures --------------------------------------------------------
    /** A tiny but genuine MP4 (ftyp box first) padded to $size bytes — finfo reports video/mp4. */
    private function mp4Bytes(int $size = 3000): string
    {
        $head = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";
        $mdat = pack('N', $size - strlen($head)).'mdat';

        return str_pad($head.$mdat, $size, "\x00");
    }

    private function chunk(string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('blob', $bytes);
    }

    /** Full upload through the HTTP endpoints; returns the completed token. */
    private function uploadViaHttp(User $user, string $bytes, string $purpose = 'video', string $name = 'lecture.mp4'): string
    {
        $token = $this->actingAs($user)
            ->postJson(route('studio.uploads.init'), ['purpose' => $purpose, 'filename' => $name, 'size' => strlen($bytes)])
            ->assertCreated()
            ->json('token');

        foreach (str_split($bytes, 1024) as $i => $part) {
            $this->actingAs($user)
                ->post(route('studio.uploads.chunk', $token), ['index' => $i, 'chunk' => $this->chunk($part)], ['Accept' => 'application/json'])
                ->assertOk();
        }

        $this->actingAs($user)->postJson(route('studio.uploads.complete', $token))->assertOk();

        return $token;
    }

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

    private function video(LearningCategory $category, ?User $instructor = null, array $attrs = []): LearningVideo
    {
        return LearningVideo::create(array_merge([
            'learning_category_id' => $category->id,
            'title' => 'Lesson '.uniqid(),
            'status' => 'draft',
            'visibility' => 'course',
            'instructor_id' => $instructor?->id,
        ], $attrs));
    }

    /** A lesson with a real file on the private disk. */
    private function videoWithFile(LearningCategory $category, User $instructor, array $attrs = []): LearningVideo
    {
        Storage::disk('private')->put('learning/videos/existing.mp4', $this->mp4Bytes(2000));

        return $this->video($category, $instructor, array_merge([
            'disk' => 'private',
            'path' => 'learning/videos/existing.mp4',
            'original_name' => 'existing.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => 2000,
        ], $attrs));
    }

    // --- Permission matrix -------------------------------------------------
    public function test_plain_member_is_forbidden_on_every_studio_video_route(): void
    {
        $user = User::factory()->create();
        $video = $this->video($this->category(), $this->instructor());

        $this->actingAs($user);
        $this->get(route('studio.videos.index'))->assertForbidden();
        $this->get(route('studio.videos.create'))->assertForbidden();
        $this->post(route('studio.videos.store'), ['title' => 'x'])->assertForbidden();
        $this->get(route('studio.videos.edit', $video))->assertForbidden();
        $this->put(route('studio.videos.update', $video), ['title' => 'x'])->assertForbidden();
        $this->delete(route('studio.videos.destroy', $video), ['reason' => 'nope'])->assertForbidden();
        $this->post(route('studio.videos.publish', $video))->assertForbidden();
        $this->post(route('studio.videos.unpublish', $video))->assertForbidden();
        $this->post(route('studio.videos.replace', $video))->assertForbidden();
        $this->delete(route('studio.videos.thumbnail.destroy', $video))->assertForbidden();
        $this->post(route('studio.videos.renditions.store', $video))->assertForbidden();
        $this->post(route('studio.videos.resources.store', $video))->assertForbidden();
        $this->getJson(route('studio.uploads.config'))->assertForbidden();
        $this->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'a.mp4', 'size' => 10])->assertForbidden();
        $this->getJson(route('studio.users.search', ['q' => 'ab']))->assertForbidden();

        $this->assertDatabaseCount('learning_videos', 1);
    }

    public function test_instructor_sees_and_manages_only_their_own_lessons(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $other = $this->instructor();
        $mine = $this->video($cat, $me, ['title' => 'My own lesson']);
        $theirs = $this->video($cat, $other, ['title' => 'Somebody else lesson']);

        $this->actingAs($me)->get(route('studio.videos.index'))
            ->assertOk()
            ->assertSee('My own lesson')
            ->assertDontSee('Somebody else lesson');

        $this->actingAs($me)->get(route('studio.videos.edit', $mine))->assertOk()->assertSee('Danger zone');
        $this->actingAs($me)->get(route('studio.videos.edit', $theirs))->assertForbidden();
        $this->actingAs($me)->put(route('studio.videos.update', $theirs), [
            'title' => 'Hijacked', 'learning_category_id' => $cat->id, 'visibility' => 'course',
        ])->assertForbidden();
        $this->actingAs($me)->post(route('studio.videos.publish', $theirs))->assertForbidden();
        $this->actingAs($me)->delete(route('studio.videos.destroy', $theirs), ['reason' => 'mine now'])->assertForbidden();

        $this->assertSame('Somebody else lesson', $theirs->fresh()->title);
        $this->assertFalse($theirs->fresh()->trashed());
    }

    public function test_instructor_cannot_place_a_lesson_in_a_course_they_do_not_teach(): void
    {
        $cat = $this->category();
        $otherCat = $this->category('Maths');
        $me = $this->instructor();
        $foreign = $this->course($cat, $this->instructor());
        $own = $this->course($otherCat, $me);
        $topic = LearningTopic::create(['learning_course_id' => $own->id, 'title' => 'Week 1']);

        // A course taught by somebody else is refused server-side.
        $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => 'Sneaky', 'learning_course_id' => $foreign->id, 'visibility' => 'course', 'action' => 'draft',
        ])->assertSessionHasErrors('learning_course_id');
        $this->assertDatabaseMissing('learning_videos', ['title' => 'Sneaky']);

        // The create form only offers the courses they teach.
        $this->actingAs($me)->get(route('studio.videos.create'))
            ->assertOk()
            ->assertSee($own->title)
            ->assertDontSee($foreign->title);

        // Their own course works; the category follows the course.
        $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => 'In my course', 'learning_category_id' => $cat->id, 'learning_course_id' => $own->id,
            'learning_topic_id' => $topic->id, 'visibility' => 'course', 'action' => 'draft',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $video = LearningVideo::where('title', 'In my course')->firstOrFail();
        $this->assertSame($otherCat->id, (int) $video->learning_category_id);
        $this->assertSame($topic->id, (int) $video->learning_topic_id);
        $this->assertSame($me->id, (int) $video->instructor_id);

        // Standalone in any category is fine; an instructor_id field is ignored for non-managers.
        $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => 'Standalone', 'learning_category_id' => $cat->id, 'visibility' => 'members',
            'instructor_id' => $this->instructor()->id, 'action' => 'draft',
        ])->assertSessionHasNoErrors();
        $standalone = LearningVideo::where('title', 'Standalone')->firstOrFail();
        $this->assertNull($standalone->learning_course_id);
        $this->assertSame($me->id, (int) $standalone->instructor_id);

        // Moving an existing lesson into a foreign course is refused as well.
        $this->actingAs($me)->put(route('studio.videos.update', $standalone), [
            'title' => 'Standalone', 'learning_course_id' => $foreign->id, 'visibility' => 'course',
        ])->assertSessionHasErrors('learning_course_id');
        $this->assertNull($standalone->fresh()->learning_course_id);
    }

    public function test_learning_view_admin_can_list_everything_but_not_edit(): void
    {
        $cat = $this->category();
        $a = $this->video($cat, $this->instructor(), ['title' => 'Lesson Alpha']);
        $b = $this->video($cat, null, ['title' => 'Lesson Bravo']);
        $viewer = $this->restrictedAdmin(['learning.view']);

        $this->actingAs($viewer)->get(route('studio.videos.index'))
            ->assertOk()->assertSee('Lesson Alpha')->assertSee('Lesson Bravo')
            ->assertDontSee(route('studio.videos.create'));
        $this->actingAs($viewer)->get(route('studio.videos.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('studio.videos.edit', $a))->assertForbidden();
        $this->actingAs($viewer)->post(route('studio.videos.publish', $b))->assertForbidden();
        $this->actingAs($viewer)->delete(route('studio.videos.destroy', $a), ['reason' => 'test'])->assertForbidden();
    }

    public function test_rooms_only_staff_cannot_open_the_lesson_list(): void
    {
        $this->actingAs($this->restrictedAdmin(['rooms.view']))
            ->get(route('studio.videos.index'))->assertForbidden();
    }

    public function test_learning_manager_has_full_control_and_picks_eligible_instructors(): void
    {
        $cat = $this->category();
        $teacher = $this->instructor(['name' => 'Teacher Tina']);
        $plain = User::factory()->create();
        $course = $this->course($cat, $this->instructor());
        $video = $this->video($cat, $this->instructor());
        $manager = $this->restrictedAdmin(['learning.manage']);

        $this->actingAs($manager)->get(route('studio.videos.index'))->assertOk()->assertSee($video->title);
        $this->actingAs($manager)->get(route('studio.videos.edit', $video))->assertOk()->assertSee('Teacher Tina');

        // Any course; an instructor must have instructor access (or be an admin).
        $this->actingAs($manager)->post(route('studio.videos.store'), [
            'title' => 'Managed', 'learning_course_id' => $course->id, 'visibility' => 'course',
            'instructor_id' => $plain->id, 'action' => 'draft',
        ])->assertSessionHasErrors('instructor_id');

        $this->actingAs($manager)->post(route('studio.videos.store'), [
            'title' => 'Managed', 'learning_course_id' => $course->id, 'visibility' => 'course',
            'instructor_id' => $teacher->id, 'action' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->assertSame($teacher->id, (int) LearningVideo::where('title', 'Managed')->value('instructor_id'));

        $this->actingAs($manager)->put(route('studio.videos.update', $video), [
            'title' => 'Renamed by manager', 'learning_category_id' => $cat->id, 'visibility' => 'private',
            'tags' => 'biology, Cells, biology',
        ])->assertRedirect(route('studio.videos.edit', $video))->assertSessionHas('success');
        $fresh = $video->fresh();
        $this->assertSame('Renamed by manager', $fresh->title);
        $this->assertSame('private', $fresh->visibility);
        $this->assertSame(['biology', 'Cells'], $fresh->tags);
    }

    public function test_super_admin_can_do_everything(): void
    {
        $cat = $this->category();
        $admin = $this->superAdmin();
        $video = $this->videoWithFile($cat, $this->instructor());

        $this->actingAs($admin)->get(route('studio.videos.index'))->assertOk()->assertSee($video->title);
        $this->actingAs($admin)->get(route('studio.videos.create'))->assertOk()->assertSee('Publish now');
        $this->actingAs($admin)->get(route('studio.videos.edit', $video))->assertOk();
        $this->actingAs($admin)->post(route('studio.videos.publish', $video))->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($video->fresh()->isPublished());
    }

    // --- Create flow through the upload endpoints ---------------------------
    public function test_full_create_flow_uploads_in_chunks_and_publishes(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $bytes = $this->mp4Bytes(3000);

        $config = $this->actingAs($me)->getJson(route('studio.uploads.config'))
            ->assertOk()
            ->assertJsonStructure(['chunk_bytes', 'purposes' => ['video' => ['max_bytes', 'extensions', 'mimes'], 'rendition', 'recording']])
            ->json();
        $this->assertGreaterThan(0, $config['chunk_bytes']);

        $token = $this->actingAs($me)->postJson(route('studio.uploads.init'), [
            'purpose' => 'video', 'filename' => 'Week 1.mp4', 'size' => strlen($bytes),
        ])->assertCreated()->assertJsonStructure(['token', 'chunk_bytes'])->json('token');
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{40}\z/', $token);

        $parts = str_split($bytes, 1024);
        foreach ($parts as $i => $part) {
            $this->actingAs($me)
                ->post(route('studio.uploads.chunk', $token), ['index' => $i, 'chunk' => $this->chunk($part)], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJson(['next_index' => $i + 1]);
        }

        // A retried chunk is acknowledged without writing twice.
        $this->actingAs($me)
            ->post(route('studio.uploads.chunk', $token), ['index' => 0, 'chunk' => $this->chunk($parts[0])], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['received_bytes' => 3000, 'next_index' => 3]);

        $this->actingAs($me)->postJson(route('studio.uploads.complete', $token))
            ->assertOk()
            ->assertExactJson(['token' => $token, 'size' => 3000, 'mime' => 'video/mp4', 'original_name' => 'Week 1.mp4']);

        $response = $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => 'Photosynthesis explained',
            'description' => "**Light** reactions\n\n- chlorophyll",
            'learning_category_id' => $cat->id,
            'visibility' => 'course',
            'tags' => 'plants, energy',
            'upload_token' => $token,
            'duration_seconds' => 125,
            'width' => 1280,
            'height' => 720,
            'auto_thumbnail' => UploadedFile::fake()->image('thumbnail.jpg', 640, 360),
            'notify' => '1',
            'action' => 'publish',
        ]);

        $video = LearningVideo::where('title', 'Photosynthesis explained')->firstOrFail();
        $response->assertRedirect(route('studio.videos.edit', $video))->assertSessionHas('success');

        $this->assertTrue($video->isPublished());
        $this->assertNotNull($video->published_at);
        $this->assertSame($me->id, (int) $video->instructor_id);
        $this->assertSame(['plants', 'energy'], $video->tags);
        $this->assertSame(125, $video->duration_seconds);
        $this->assertSame(720, (int) $video->height);
        $this->assertSame('private', $video->disk);
        $this->assertSame('video/mp4', $video->mime);
        Storage::disk('private')->assertExists($video->path);
        $this->assertNotNull($video->thumbnail_path, 'the captured frame becomes the thumbnail');
        Storage::disk('public')->assertExists($video->thumbnail_path);
        $this->assertFalse(Storage::disk('local')->exists('learning-uploads/'.$token), 'the temp upload is adopted');

        // The edit page renders every panel.
        $this->actingAs($me)->get(route('studio.videos.edit', $video))
            ->assertOk()
            ->assertSee('Replace video')
            ->assertSee('Add a quality')
            ->assertSee('Add a resource')
            ->assertSee('learnChunkUploader', false)
            ->assertSee('learnVideoForm', false);
    }

    public function test_publishing_requires_a_file_and_publish_unpublish_round_trip(): void
    {
        $cat = $this->category();
        $me = $this->instructor();

        $this->actingAs($me)->from(route('studio.videos.create'))->post(route('studio.videos.store'), [
            'title' => 'No file yet', 'learning_category_id' => $cat->id, 'visibility' => 'course', 'action' => 'publish',
        ])->assertRedirect(route('studio.videos.create'))->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('learning_videos', ['title' => 'No file yet']);

        $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => 'No file yet', 'learning_category_id' => $cat->id, 'visibility' => 'course', 'action' => 'draft',
        ])->assertSessionHasNoErrors();
        $draft = LearningVideo::where('title', 'No file yet')->firstOrFail();
        $this->assertFalse($draft->isPublished());

        $this->actingAs($me)->post(route('studio.videos.publish', $draft))->assertSessionHasErrors('status');
        $this->assertFalse($draft->fresh()->isPublished());

        $ready = $this->videoWithFile($cat, $me);
        $this->actingAs($me)->from(route('studio.videos.index'))->post(route('studio.videos.publish', $ready))
            ->assertRedirect(route('studio.videos.index'))->assertSessionHas('success');
        $this->assertTrue($ready->fresh()->isPublished());

        $this->actingAs($me)->post(route('studio.videos.unpublish', $ready))->assertSessionHas('success');
        $this->assertFalse($ready->fresh()->isPublished());
        $this->assertNotNull($ready->fresh()->published_at, 'published_at is kept so re-publishing does not notify again');
    }

    public function test_validation_errors_on_store(): void
    {
        $me = $this->instructor();

        $this->actingAs($me)->post(route('studio.videos.store'), [
            'title' => '', 'visibility' => 'everyone', 'upload_token' => 'bad token', 'action' => 'draft',
        ])->assertSessionHasErrors(['title', 'visibility', 'learning_category_id', 'upload_token']);

        $this->assertDatabaseCount('learning_videos', 0);
    }

    public function test_replacing_the_file_deletes_the_old_one(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $video = $this->videoWithFile($cat, $me, ['duration_seconds' => 50]);
        $oldPath = $video->path;

        $token = $this->uploadViaHttp($me, $this->mp4Bytes(4000), 'video', 'take2.mp4');

        $this->actingAs($me)->post(route('studio.videos.replace', $video), [
            'upload_token' => $token, 'duration_seconds' => 300, 'width' => 1920, 'height' => 1080,
        ])->assertRedirect(route('studio.videos.edit', $video).'#replace')->assertSessionHas('success');

        $fresh = $video->fresh();
        $this->assertNotSame($oldPath, $fresh->path);
        Storage::disk('private')->assertExists($fresh->path);
        Storage::disk('private')->assertMissing($oldPath);
        $this->assertSame('take2.mp4', $fresh->original_name);
        $this->assertSame(4000, (int) $fresh->size_bytes);
        $this->assertSame(300, $fresh->duration_seconds);
        $this->assertSame('1080p', $fresh->sourceQualityLabel());

        // Without a token nothing changes.
        $this->actingAs($me)->post(route('studio.videos.replace', $video), [])->assertSessionHasErrors('upload_token');
    }

    public function test_thumbnail_can_be_replaced_and_removed(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $video = $this->video($cat, $me);

        $this->actingAs($me)->put(route('studio.videos.update', $video), [
            'title' => $video->title, 'learning_category_id' => $cat->id, 'visibility' => 'course',
            'thumbnail' => UploadedFile::fake()->image('cover.png', 800, 450),
        ])->assertSessionHasNoErrors();
        $path = $video->fresh()->thumbnail_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($me)->delete(route('studio.videos.thumbnail.destroy', $video), ['reason' => 'blurry'])
            ->assertRedirect(route('studio.videos.edit', $video))->assertSessionHas('success');
        $this->assertNull($video->fresh()->thumbnail_path);
        Storage::disk('public')->assertMissing($path);
    }

    // --- Renditions & resources --------------------------------------------
    public function test_renditions_can_be_added_replaced_and_removed(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $video = $this->videoWithFile($cat, $me);

        $this->actingAs($me)->post(route('studio.videos.renditions.store', $video), ['quality' => '360p'])
            ->assertSessionHasErrors('upload_token');
        $this->actingAs($me)->post(route('studio.videos.renditions.store', $video), ['quality' => '4k', 'upload_token' => str_repeat('a', 40)])
            ->assertSessionHasErrors('quality');

        $token = $this->uploadViaHttp($me, $this->mp4Bytes(1500), 'rendition', 'low.mp4');
        $this->actingAs($me)->post(route('studio.videos.renditions.store', $video), ['quality' => '360p', 'upload_token' => $token])
            ->assertRedirect(route('studio.videos.edit', $video).'#renditions')->assertSessionHas('success');

        $rendition = LearningVideoRendition::where('learning_video_id', $video->id)->firstOrFail();
        $this->assertSame(360, (int) $rendition->height);
        Storage::disk('private')->assertExists($rendition->path);
        $firstPath = $rendition->path;

        // Same quality again replaces the file.
        $token2 = $this->uploadViaHttp($me, $this->mp4Bytes(1800), 'rendition', 'low2.mp4');
        $this->actingAs($me)->post(route('studio.videos.renditions.store', $video), ['quality' => '360p', 'upload_token' => $token2])
            ->assertSessionHas('success');
        $this->assertSame(1, LearningVideoRendition::where('learning_video_id', $video->id)->count());
        Storage::disk('private')->assertMissing($firstPath);

        $rendition = $rendition->fresh();
        $this->actingAs($me)->delete(route('studio.videos.renditions.destroy', [$video, $rendition]), ['reason' => 'not needed'])
            ->assertRedirect(route('studio.videos.edit', $video).'#renditions');
        $this->assertDatabaseMissing('learning_video_renditions', ['id' => $rendition->id]);
        Storage::disk('private')->assertMissing($rendition->path);

        // A rendition of another lesson cannot be reached through this lesson (scoped binding).
        $otherVideo = $this->video($cat, $me);
        $foreign = LearningVideoRendition::create([
            'learning_video_id' => $otherVideo->id, 'quality' => '720p', 'height' => 720,
            'disk' => 'private', 'path' => 'x.mp4', 'mime' => 'video/mp4', 'size_bytes' => 1,
        ]);
        $this->actingAs($me)->delete(route('studio.videos.renditions.destroy', [$video, $foreign]))->assertNotFound();
    }

    public function test_resources_accept_files_and_http_links_only(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $video = $this->video($cat, $me);

        $this->actingAs($me)->post(route('studio.videos.resources.store', $video), [
            'title' => 'Slides', 'type' => 'file', 'file' => UploadedFile::fake()->create('slides.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('studio.videos.edit', $video).'#resources')->assertSessionHas('success');

        $file = LearningVideoResource::where('title', 'Slides')->firstOrFail();
        $this->assertTrue($file->isFile());
        Storage::disk('private')->assertExists($file->path);

        $this->actingAs($me)->post(route('studio.videos.resources.store', $video), [
            'title' => 'Further reading', 'type' => 'link', 'url' => 'https://example.org/cells',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('learning_video_resources', ['title' => 'Further reading', 'type' => 'link', 'url' => 'https://example.org/cells']);

        $this->actingAs($me)->post(route('studio.videos.resources.store', $video), [
            'title' => 'Evil', 'type' => 'link', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');
        $this->actingAs($me)->post(route('studio.videos.resources.store', $video), [
            'title' => 'Program', 'type' => 'file', 'file' => UploadedFile::fake()->create('run.exe', 5),
        ])->assertSessionHasErrors('file');
        $this->actingAs($me)->post(route('studio.videos.resources.store', $video), ['title' => 'Nothing', 'type' => 'file'])
            ->assertSessionHasErrors('file');

        $this->actingAs($me)->get(route('studio.videos.edit', $video))->assertOk()->assertSee('Further reading')->assertSee('slides.pdf');

        $this->actingAs($me)->delete(route('studio.videos.resources.destroy', [$video, $file]), ['reason' => 'outdated'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('learning_video_resources', ['id' => $file->id]);
        Storage::disk('private')->assertMissing($file->path);
    }

    // --- Deletion ------------------------------------------------------------
    public function test_delete_requires_a_reason_and_soft_deletes(): void
    {
        $cat = $this->category();
        $me = $this->instructor();
        $video = $this->videoWithFile($cat, $me, ['title' => 'To be removed']);

        $this->actingAs($me)->delete(route('studio.videos.destroy', $video), [])->assertSessionHasErrors('reason');
        $this->actingAs($me)->delete(route('studio.videos.destroy', $video), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->assertFalse($video->fresh()->trashed());

        $this->actingAs($me)->delete(route('studio.videos.destroy', $video), ['reason' => 'Recorded again'])
            ->assertRedirect(route('studio.videos.index'))->assertSessionHas('success');

        $this->assertSoftDeleted('learning_videos', ['id' => $video->id]);
        Storage::disk('private')->assertExists($video->path); // files stay until the trash is purged
        $this->actingAs($me)->get(route('studio.videos.index'))->assertOk()->assertSee('moved to the trash')->assertSee('No video lessons yet');
    }

    // --- Index ---------------------------------------------------------------
    public function test_index_tabs_filters_counts_and_completion(): void
    {
        $cat = $this->category('Biology');
        $other = $this->category('History');
        $me = $this->instructor();
        $published = $this->videoWithFile($cat, $me, ['title' => 'Published cells', 'status' => 'published', 'published_at' => now(), 'views' => 7]);
        $this->video($other, $me, ['title' => 'Draft wars']);

        foreach ([true, false, false, true] as $i => $done) {
            LearningVideoProgress::create([
                'user_id' => User::factory()->create()->id, 'learning_video_id' => $published->id,
                'play_count' => 1, 'percent' => $done ? 100 : 20, 'completed_at' => $done ? now() : null,
            ]);
        }

        $this->actingAs($me)->get(route('studio.videos.index'))
            ->assertOk()->assertSee('Published cells')->assertSee('Draft wars')->assertSee('50%');

        $this->actingAs($me)->get(route('studio.videos.index', ['tab' => 'drafts']))
            ->assertOk()->assertSee('Draft wars')->assertDontSee('Published cells');

        $this->actingAs($me)->get(route('studio.videos.index', ['category' => $cat->id]))
            ->assertOk()->assertSee('Published cells')->assertDontSee('Draft wars');

        $this->actingAs($me)->get(route('studio.videos.index', ['q' => 'wars']))
            ->assertOk()->assertSee('Draft wars')->assertDontSee('Published cells');

        $this->actingAs($me)->get(route('studio.videos.index', ['q' => 'nothing-matches']))
            ->assertOk()->assertSee('No lessons match');

        $this->actingAs($this->instructor())->get(route('studio.videos.index'))
            ->assertOk()->assertSee('No video lessons yet')->assertSee(route('studio.videos.create'));
    }

    // --- Upload endpoints ------------------------------------------------------
    public function test_upload_endpoints_return_json_shapes_and_422s(): void
    {
        $me = $this->instructor();
        $stranger = $this->instructor();

        $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'notes.pdf', 'size' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('filename');
        $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'a.mp4', 'size' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('size');
        $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'avatar', 'filename' => 'a.mp4', 'size' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('purpose');
        $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'a.mp4', 'size' => PHP_INT_MAX])
            ->assertStatus(422);

        // Recordings need a room host; a lesson-only manager is refused.
        $this->actingAs($this->restrictedAdmin(['learning.manage']))
            ->postJson(route('studio.uploads.init'), ['purpose' => 'recording', 'filename' => 'a.mp4', 'size' => 10])->assertForbidden();
        $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'recording', 'filename' => 'a.mp4', 'size' => 10])
            ->assertCreated();

        $bytes = $this->mp4Bytes(2048);
        $token = $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'l.mp4', 'size' => 2048])
            ->json('token');

        // Chunk validation, ownership and ordering.
        $this->actingAs($me)->postJson(route('studio.uploads.chunk', $token), ['index' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('chunk');
        $this->actingAs($me)->post(route('studio.uploads.chunk', $token), ['index' => -1, 'chunk' => $this->chunk('x')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('index');
        $this->actingAs($stranger)->post(route('studio.uploads.chunk', $token), ['index' => 0, 'chunk' => $this->chunk(substr($bytes, 0, 1024))], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('upload_token');
        $this->actingAs($me)->post(route('studio.uploads.chunk', $token), ['index' => 1, 'chunk' => $this->chunk(substr($bytes, 1024))], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('index');

        $this->actingAs($me)->post(route('studio.uploads.chunk', $token), ['index' => 0, 'chunk' => $this->chunk(substr($bytes, 0, 1024))], ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['received_bytes' => 1024, 'next_index' => 1]);

        // Completing early fails; aborting removes everything.
        $this->actingAs($me)->postJson(route('studio.uploads.complete', $token))
            ->assertStatus(422)->assertJsonValidationErrors('upload_token');
        $this->actingAs($stranger)->deleteJson(route('studio.uploads.abort', $token))->assertStatus(422);
        $this->actingAs($me)->deleteJson(route('studio.uploads.abort', $token))->assertNoContent();
        $this->assertFalse(Storage::disk('local')->exists('learning-uploads/'.$token));
        $this->actingAs($me)->deleteJson(route('studio.uploads.abort', $token))->assertNoContent(); // idempotent

        // Content that is not really a video is refused on complete.
        $fake = str_repeat('not a video ', 50);
        $bad = $this->actingAs($me)->postJson(route('studio.uploads.init'), ['purpose' => 'video', 'filename' => 'trick.mp4', 'size' => strlen($fake)])
            ->json('token');
        $this->actingAs($me)->post(route('studio.uploads.chunk', $bad), ['index' => 0, 'chunk' => $this->chunk($fake)], ['Accept' => 'application/json'])
            ->assertOk();
        $this->actingAs($me)->postJson(route('studio.uploads.complete', $bad))
            ->assertStatus(422)->assertJsonValidationErrors('file');

        // A token that does not match the route pattern never reaches the controller.
        $this->actingAs($me)->postJson('/studio/uploads/short/complete')->assertNotFound();
    }

    // --- Member search ------------------------------------------------------
    public function test_user_search_hides_emails_from_non_admins(): void
    {
        User::factory()->create(['name' => 'Amina Juma', 'email' => 'amina@example.test']);
        User::factory()->create(['name' => 'Amina Inactive', 'email' => 'inactive@example.test', 'status' => 'inactive']);
        $me = $this->instructor(['name' => 'Teacher Zed']);

        $this->actingAs($me)->getJson(route('studio.users.search', ['q' => 'a']))->assertOk()->assertExactJson([]);

        $rows = $this->actingAs($me)->getJson(route('studio.users.search', ['q' => 'Amina']))->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame('Amina Juma', $rows[0]['name']);
        $this->assertArrayNotHasKey('email', $rows[0]);

        // Non-admins cannot harvest addresses by partial e-mail.
        $this->actingAs($me)->getJson(route('studio.users.search', ['q' => 'example.test']))->assertOk()->assertExactJson([]);

        $admin = $this->superAdmin();
        $rows = $this->actingAs($admin)->getJson(route('studio.users.search', ['q' => 'amina@']))->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame('amina@example.test', $rows[0]['email']);
        $this->assertSame(['id', 'name', 'email'], array_keys($rows[0]));
    }
}
