<?php

namespace Tests\Feature\Learning;

use App\Exceptions\Learning\LearningDeletionBlocked;
use App\Models\DeletedRecord;
use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningRoom;
use App\Models\LearningRoomRecording;
use App\Models\LearningRoomSession;
use App\Models\LearningTopic;
use App\Models\LearningVideo;
use App\Models\LearningVideoRendition;
use App\Models\User;
use App\Services\Learning\ChunkedUploadService;
use App\Services\Learning\LearningDeletionService;
use App\Services\Learning\LearningStorage;
use App\Services\Learning\ProgressService;
use App\Services\Learning\VideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * ROOM content services: chunked uploads, private streaming, watch progress,
 * lesson writes (incl. recordings → lessons) and safe deletion / trash.
 */
class ContentServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        Storage::fake('local');
        Notification::fake(); // LearningNotifier is exercised by its own tests
    }

    // --- Fixtures --------------------------------------------------------
    /** A tiny but genuine MP4 (ftyp box first) padded to $size bytes — finfo reports video/mp4. */
    private function mp4Bytes(int $size = 3000): string
    {
        $head = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom"
            ."\x00\x00\x00\x08free";
        $mdat = pack('N', $size - strlen($head)).'mdat';

        return str_pad($head.$mdat, $size, "\x00");
    }

    private function chunk(string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('blob', $bytes);
    }

    /** Run a complete chunked upload and return its token. */
    private function upload(User $user, string $bytes, string $purpose = 'video', string $name = 'lecture.mp4'): string
    {
        $uploads = app(ChunkedUploadService::class);
        $token = $uploads->init($user, $purpose, $name, strlen($bytes));

        foreach (str_split($bytes, 1024) as $i => $part) {
            $uploads->appendChunk($user, $token, $i, $this->chunk($part));
        }
        $uploads->complete($user, $token);

        return $token;
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
            'status' => 'published',
            'visibility' => 'course',
        ], $attrs));
    }

    private function assertValidationKey(string $key, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected a ValidationException on [{$key}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    private function assertBlocked(string $messageFragment, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected LearningDeletionBlocked.');
        } catch (LearningDeletionBlocked $e) {
            $this->assertStringContainsString($messageFragment, $e->getMessage());
        }
    }

    // --- Chunked uploads ---------------------------------------------------
    public function test_chunked_upload_assembles_a_real_mp4_and_retries_are_harmless(): void
    {
        $user = $this->instructor();
        $uploads = app(ChunkedUploadService::class);
        $bytes = $this->mp4Bytes(3000);
        [$a, $b, $c] = str_split($bytes, 1024);

        $token = $uploads->init($user, 'video', 'C:\\fakepath\\Week 1 lecture.MP4', strlen($bytes));
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{40}\z/', $token);

        $this->assertSame(['received_bytes' => 1024, 'next_index' => 1], $uploads->appendChunk($user, $token, 0, $this->chunk($a)));
        // A retried chunk 0 is acknowledged without being written twice.
        $this->assertSame(['received_bytes' => 1024, 'next_index' => 1], $uploads->appendChunk($user, $token, 0, $this->chunk($a)));
        $uploads->appendChunk($user, $token, 1, $this->chunk($b));
        $this->assertSame(['received_bytes' => 3000, 'next_index' => 3], $uploads->appendChunk($user, $token, 2, $this->chunk($c)));

        $done = $uploads->complete($user, $token);
        $this->assertSame(['token' => $token, 'size' => 3000, 'mime' => 'video/mp4', 'original_name' => 'Week 1 lecture.MP4'], $done);
        $this->assertSame($done, $uploads->complete($user, $token), 'complete() is idempotent');

        $resolved = $uploads->resolveCompleted($user, $token, 'video');
        $this->assertSame($bytes, file_get_contents($resolved['absolute_path']));

        // Adopting moves the file to the private disk and clears the temp area.
        $stored = app(LearningStorage::class)->adoptUpload($user, $token, 'video', 'learning/videos');
        $this->assertSame('private', $stored['disk']);
        $this->assertStringEndsWith('.mp4', $stored['path']);
        $this->assertSame($bytes, Storage::disk('private')->get($stored['path']));
        $this->assertSame([], Storage::disk('local')->allFiles(ChunkedUploadService::ROOT));
        $this->assertValidationKey('upload_token', fn () => $uploads->resolveCompleted($user, $token, 'video'));
    }

    public function test_chunked_upload_rejects_fake_mime_bad_tokens_foreign_owners_and_out_of_order_chunks(): void
    {
        $user = $this->instructor();
        $other = $this->instructor();
        $uploads = app(ChunkedUploadService::class);

        // Right extension, wrong content → rejected by finfo and discarded.
        $fake = str_repeat('this is not a video ', 20);
        $token = $uploads->init($user, 'video', 'trick.mp4', strlen($fake));
        $uploads->appendChunk($user, $token, 0, $this->chunk($fake));
        $this->assertValidationKey('file', fn () => $uploads->complete($user, $token));
        $this->assertFalse(Storage::disk('local')->exists(ChunkedUploadService::ROOT.'/'.$token));

        // Path traversal / malformed tokens never reach the filesystem.
        foreach (['../../../.env', str_repeat('a', 39), str_repeat('a', 40).'/x', ''] as $bad) {
            $this->assertValidationKey('upload_token', fn () => $uploads->appendChunk($user, $bad, 0, $this->chunk('x')));
        }

        // Out-of-order chunk, and someone else's upload.
        $bytes = $this->mp4Bytes(2048);
        $token = $uploads->init($user, 'video', 'lesson.mp4', strlen($bytes));
        $this->assertValidationKey('index', fn () => $uploads->appendChunk($user, $token, 1, $this->chunk(substr($bytes, 1024))));
        $this->assertValidationKey('upload_token', fn () => $uploads->appendChunk($other, $token, 0, $this->chunk(substr($bytes, 0, 1024))));

        // Incomplete upload cannot be completed; more bytes than declared are refused.
        $uploads->appendChunk($user, $token, 0, $this->chunk(substr($bytes, 0, 1024)));
        $this->assertValidationKey('upload_token', fn () => $uploads->complete($user, $token));
        $this->assertValidationKey('chunk', fn () => $uploads->appendChunk($user, $token, 1, $this->chunk(str_repeat("\x00", 1500))));

        // Bad purpose / extension / size at init.
        $this->assertValidationKey('purpose', fn () => $uploads->init($user, 'avatar', 'a.mp4', 10));
        $this->assertValidationKey('filename', fn () => $uploads->init($user, 'video', 'setup.exe', 10));
        $this->assertValidationKey('size', fn () => $uploads->init($user, 'video', 'a.mp4', 0));

        // Wrong purpose on adopt.
        $done = $this->upload($user, $this->mp4Bytes(1500));
        $this->assertValidationKey('upload_token', fn () => app(LearningStorage::class)->adoptUpload($user, $done, 'rendition', 'learning/renditions'));
    }

    public function test_cleanup_removes_only_stale_uploads(): void
    {
        $user = $this->instructor();
        $uploads = app(ChunkedUploadService::class);

        $stale = $uploads->init($user, 'video', 'old.mp4', 100);
        $this->travel(25)->hours();
        $fresh = $uploads->init($user, 'video', 'new.mp4', 100);

        $this->assertSame(1, $uploads->cleanup(24));
        $this->assertFalse(Storage::disk('local')->exists(ChunkedUploadService::ROOT.'/'.$stale));
        $this->assertTrue(Storage::disk('local')->exists(ChunkedUploadService::ROOT.'/'.$fresh));
    }

    // --- Streaming -----------------------------------------------------------
    public function test_stream_response_serves_http_ranges_from_the_private_disk(): void
    {
        $bytes = $this->mp4Bytes(5000);
        Storage::disk('private')->put('learning/videos/clip.mp4', $bytes);

        Route::get('/_test/learning-stream/{name?}', fn (?string $name = null) => app(LearningStorage::class)
            ->streamResponse('private', 'learning/videos/clip.mp4', 'video/mp4', $name));

        $partial = $this->get('/_test/learning-stream', ['Range' => 'bytes=0-99']);
        $partial->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-99/5000')
            ->assertHeader('Content-Length', '100')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline');
        $this->assertStringContainsString('private', $partial->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $partial->headers->get('Cache-Control'));

        $this->get('/_test/learning-stream')->assertOk()->assertHeader('Content-Length', '5000');

        $download = $this->get('/_test/learning-stream/Notes.mp4');
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));

        try {
            app(LearningStorage::class)->streamResponse('private', 'learning/videos/missing.mp4', 'video/mp4');
            $this->fail('A missing file must be a 404.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    // --- Progress ----------------------------------------------------------
    public function test_progress_counts_starts_completes_at_the_threshold_and_is_never_uncompleted_by_record(): void
    {
        $learner = User::factory()->create();
        $video = $this->video($this->category(), null, ['duration_seconds' => 100]);
        $progress = app(ProgressService::class);

        $row = $progress->record($learner, $video, 0, 100, 'start');
        $this->assertSame(1, $row->play_count);
        $this->assertSame(1, $video->fresh()->views);

        $row = $progress->record($learner, $video, 50, 9999, 'tick', 500);
        $this->assertSame(60, $row->watched_seconds, 'watched delta is clamped to 60 s');
        $this->assertSame(50, $row->percent);
        $this->assertNull($row->completed_at);
        $this->assertSame(100, $video->fresh()->duration_seconds, 'the stored duration is trusted over the client');

        $row = $progress->record($learner, $video, 90, 100, 'tick', 40);
        $this->assertSame(90, $row->percent);
        $this->assertNotNull($row->completed_at, '90% counts as completed');

        $row = $progress->record($learner, $video, 10, 100, 'seek');
        $this->assertNotNull($row->completed_at, 'record() never clears completion');
        $this->assertSame(10, $row->position_seconds);
        $this->assertSame(90, $row->max_position_seconds);

        $row = $progress->record($learner, $video, 500, 100, 'tick');
        $this->assertSame(100, $row->position_seconds, 'position is clamped to the duration');
        $this->assertSame(100, $row->percent);

        // Unknown duration: a sane client value is persisted on the video.
        $untimed = $this->video($this->category());
        $progress->record($learner, $untimed, 5, 240, 'start');
        $this->assertSame(240, $untimed->fresh()->duration_seconds);

        $this->assertSame(1, LearningVideo::query()->whereKey($video->id)->value('views'));
    }

    public function test_course_progress_rolls_up_lessons_and_syncs_the_enrolment(): void
    {
        $learner = User::factory()->create();
        $category = $this->category();
        $course = $this->course($category);
        $first = $this->video($category, $course, ['duration_seconds' => 60, 'position' => 1]);
        $second = $this->video($category, $course, ['duration_seconds' => 60, 'position' => 2]);
        $this->video($category, $course, ['status' => 'draft', 'position' => 3]); // not counted
        $enrollment = LearningEnrollment::create(['user_id' => $learner->id, 'learning_course_id' => $course->id, 'enrolled_at' => now()]);
        $progress = app(ProgressService::class);

        $empty = $progress->courseProgress($learner, $course);
        $this->assertSame([2, 0, 0, false], [$empty['total'], $empty['completed'], $empty['percent'], $empty['started']]);
        $this->assertTrue($empty['next_video']->is($first));

        $progress->record($learner, $first, 0, 60, 'start');
        $progress->record($learner, $first, 60, 60, 'ended');
        $half = $progress->courseProgress($learner, $course);
        $this->assertSame([2, 1, 50, true], [$half['total'], $half['completed'], $half['percent'], $half['started']]);
        $this->assertTrue($half['next_video']->is($second));
        $this->assertNull($enrollment->fresh()->completed_at);

        $progress->setCompleted($learner, $second, true);
        $full = $progress->courseProgress($learner, $course);
        $this->assertSame(100, $full['percent']);
        $this->assertNull($full['next_video']);
        $this->assertNotNull($enrollment->fresh()->completed_at, 'finishing every lesson completes the enrolment');

        $progress->setCompleted($learner, $second, false);
        $this->assertNull($enrollment->fresh()->completed_at);
        $this->assertSame(1, $progress->courseProgress($learner, $course)['completed']);

        $this->assertSame([$first->id], $progress->completedLessons($learner)->pluck('learning_video_id')->all());
        $this->assertSame([$second->id], $progress->continueWatching($learner)->pluck('learning_video_id')->all());
        $this->assertCount(2, $progress->recentlyWatched($learner));

        $stats = $progress->stats($learner);
        $this->assertSame([2, 1, 1, 0], [$stats['started'], $stats['completed'], $stats['courses_enrolled'], $stats['courses_completed']]);
    }

    // --- Lessons -----------------------------------------------------------
    public function test_video_service_creates_publishes_replaces_and_manages_renditions(): void
    {
        $teacher = $this->instructor();
        $service = app(VideoService::class);
        $category = $this->category();
        $otherCategory = $this->category('Maths');
        $course = $this->course($category);
        $topic = LearningTopic::create(['learning_course_id' => $course->id, 'title' => 'Week 1']);
        $foreignTopic = LearningTopic::create(['learning_course_id' => $this->course($category)->id, 'title' => 'Elsewhere']);

        $this->assertValidationKey('learning_topic_id', fn () => $service->create([
            'title' => 'Bad topic', 'learning_category_id' => $category->id,
            'learning_course_id' => $course->id, 'learning_topic_id' => $foreignTopic->id,
        ], $teacher));
        $this->assertValidationKey('status', fn () => $service->create([
            'title' => 'No file', 'learning_category_id' => $category->id, 'status' => 'published',
        ], $teacher));

        $video = $service->create([
            'title' => 'Cells', 'description' => 'Intro', 'learning_category_id' => $otherCategory->id,
            'learning_course_id' => $course->id, 'learning_topic_id' => $topic->id,
            'tags' => 'biology, Cells, cells , ', 'visibility' => 'course', 'status' => 'published',
            'upload_token' => $this->upload($teacher, $this->mp4Bytes()),
            'duration_seconds' => 120, 'width' => 1280, 'height' => 720,
            'thumbnail' => UploadedFile::fake()->image('cover.jpg', 2000, 1000),
        ], $teacher);

        $this->assertSame($category->id, $video->learning_category_id, "the course's category wins");
        $this->assertSame(['biology', 'Cells'], $video->tags);
        $this->assertSame($teacher->id, $video->instructor_id);
        $this->assertTrue($video->isPublished());
        $this->assertNotNull($video->published_at);
        $this->assertSame('video/mp4', $video->mime);
        Storage::disk('private')->assertExists($video->path);
        $thumb = getimagesizefromstring(Storage::disk('public')->get($video->thumbnail_path));
        $this->assertLessThanOrEqual(1280, max($thumb[0], $thumb[1]));
        $this->assertContains($thumb['mime'], ['image/webp', 'image/jpeg']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_video_created', 'entity_id' => $video->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_video_published', 'entity_id' => $video->id]);

        // Replace: new file in, old file gone after commit.
        $oldPath = $video->path;
        $service->replaceFile($video, $this->upload($teacher, $this->mp4Bytes(4000)), $teacher, 300);
        $this->assertSame(300, $video->fresh()->duration_seconds);
        $this->assertSame(4000, $video->fresh()->size_bytes);
        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($video->path);

        // Renditions: same quality replaces the old file.
        $first = $service->addRendition($video, $this->upload($teacher, $this->mp4Bytes(1200), 'rendition'), '720p', $teacher);
        $second = $service->addRendition($video, $this->upload($teacher, $this->mp4Bytes(1300), 'rendition'), '720p', $teacher);
        $this->assertSame(1, LearningVideoRendition::query()->where('learning_video_id', $video->id)->count());
        $this->assertSame(720, $second->height);
        $this->assertNotSame($first->path, $second->path);
        Storage::disk('private')->assertMissing($first->path);
        Storage::disk('private')->assertExists($second->path);
        $this->assertValidationKey('quality', fn () => $service->addRendition($video, 'x', '4k', $teacher));

        $service->removeRendition($second, $teacher);
        Storage::disk('private')->assertMissing($second->path);
        $this->assertDatabaseMissing('learning_video_renditions', ['id' => $second->id]);

        // Resources and an unpublish that keeps published_at.
        $resource = $service->addResource($video, ['title' => 'Slides', 'type' => 'file', 'file' => UploadedFile::fake()->create('slides.pdf', 20)], $teacher);
        Storage::disk('private')->assertExists($resource->path);
        $this->assertValidationKey('url', fn () => $service->addResource($video, ['title' => 'Bad', 'type' => 'link', 'url' => 'javascript:alert(1)'], $teacher));
        $service->removeResource($resource, $teacher);
        Storage::disk('private')->assertMissing($resource->path);

        $service->unpublish($video, $teacher);
        $this->assertFalse($video->fresh()->isPublished());
        $this->assertNotNull($video->fresh()->published_at);
    }

    public function test_create_from_recording_moves_the_file_without_duplicating_it(): void
    {
        $host = $this->instructor();
        $category = $this->category();
        $course = $this->course($category);
        $room = LearningRoom::create(['title' => 'Revision night', 'host_id' => $host->id, 'learning_course_id' => $course->id, 'status' => 'completed']);
        $session = LearningRoomSession::create(['learning_room_id' => $room->id, 'started_at' => now()->subHour(), 'ended_at' => now()]);
        Storage::disk('private')->put('learning/recordings/rec.mp4', $this->mp4Bytes());
        $recording = LearningRoomRecording::create([
            'learning_room_id' => $room->id, 'learning_room_session_id' => $session->id, 'source' => 'upload',
            'status' => 'ready', 'disk' => 'private', 'path' => 'learning/recordings/rec.mp4',
            'original_name' => 'rec.mp4', 'mime' => 'video/mp4', 'size_bytes' => 3000, 'duration_seconds' => 3600,
        ]);

        $video = app(VideoService::class)->createFromRecording($recording, $host);

        $this->assertSame('learning/recordings/rec.mp4', $video->path);
        $this->assertSame([$category->id, $course->id, 'draft', $host->id], [$video->learning_category_id, $video->learning_course_id, $video->status, $video->instructor_id]);
        $recording->refresh();
        $this->assertNull($recording->path);
        $this->assertSame($video->id, $recording->learning_video_id);
        $this->assertSame(['learning/recordings/rec.mp4'], Storage::disk('private')->allFiles(), 'moved, not copied');
        $this->assertValidationKey('recording', fn () => app(VideoService::class)->createFromRecording($recording, $host));
    }

    // --- Deletion, restore, purge -------------------------------------------
    public function test_deletions_are_blocked_until_safe_and_restores_respect_parents(): void
    {
        $this->actingAs($admin = $this->admin());
        $deletions = app(LearningDeletionService::class);
        $category = $this->category();
        $course = $this->course($category);
        $video = $this->video($category, $course);
        $room = LearningRoom::create(['title' => 'Live now', 'host_id' => $admin->id, 'learning_category_id' => $category->id, 'status' => 'live']);

        $this->assertBlocked('still has 1 course and 1 lesson', fn () => $deletions->deleteCategory($category, 'cleanup'));
        $this->assertBlocked('still has 1 lesson', fn () => $deletions->deleteCourse($course, 'cleanup'));
        $this->assertBlocked('End the session', fn () => $deletions->deleteRoom($room, 'cleanup'));
        $this->assertContains('1 lesson in this course', $deletions->impact($course));

        $deletions->deleteCourse($course, 'retired', withVideos: true);
        $this->assertSoftDeleted($course);
        $this->assertSoftDeleted($video);
        $this->assertSame(2, DeletedRecord::query()->where('reason', 'retired')->count(), 'each item is snapshotted');
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_course_deleted', 'entity_id' => $course->id]);

        $this->assertBlocked('Restore the course', fn () => $deletions->restore('video', $video->id));
        $deletions->restore('course', $course->id);
        $restored = $deletions->restore('video', $video->id);
        $this->assertFalse($restored->trashed());

        // Topic delete keeps its lessons; category delete detaches rooms once empty.
        $topic = LearningTopic::create(['learning_course_id' => $course->id, 'title' => 'T']);
        $video->update(['learning_topic_id' => $topic->id]);
        $deletions->deleteTopic($topic, 'merge');
        $this->assertNull($video->fresh()->learning_topic_id);

        $deletions->deleteCourse($course->fresh(), 'retired again', withVideos: true);
        $room->update(['status' => 'completed']);
        $deletions->deleteCategory($category, 'empty now');
        $this->assertSoftDeleted($category);
        $this->assertNull($room->fresh()->learning_category_id);
        $this->assertBlocked('Restore the category', fn () => $deletions->restore('course', $course->id));

        // A running session cannot be deleted.
        $session = LearningRoomSession::create(['learning_room_id' => $room->id, 'started_at' => now()]);
        $this->assertBlocked('still running', fn () => $deletions->deleteSession($session, 'oops'));
    }

    public function test_purge_removes_rows_and_files_and_expired_trash_is_purged(): void
    {
        $this->actingAs($admin = $this->admin());
        $deletions = app(LearningDeletionService::class);
        $category = $this->category();
        $course = $this->course($category, ['thumbnail_path' => 'learning/thumbnails/course.webp']);
        Storage::disk('public')->put('learning/thumbnails/course.webp', 'img');

        $files = ['learning/videos/a.mp4', 'learning/renditions/a-720.mp4', 'learning/resources/a.pdf'];
        foreach ($files as $path) {
            Storage::disk('private')->put($path, 'x');
        }
        Storage::disk('public')->put('learning/thumbnails/a.webp', 'img');
        $video = $this->video($category, $course, ['disk' => 'private', 'path' => $files[0], 'thumbnail_path' => 'learning/thumbnails/a.webp']);
        $video->renditions()->create(['quality' => '720p', 'height' => 720, 'disk' => 'private', 'path' => $files[1], 'mime' => 'video/mp4', 'size_bytes' => 1]);
        $video->resources()->create(['title' => 'Notes', 'type' => 'file', 'disk' => 'private', 'path' => $files[2], 'mime' => 'application/pdf', 'size_bytes' => 1]);

        // Soft delete keeps the files; purge removes rows and files.
        $deletions->deleteVideo($video, 'duplicate');
        Storage::disk('private')->assertExists($files[0]);

        $deletions->purge('video', $video->id);
        $this->assertDatabaseMissing('learning_videos', ['id' => $video->id]);
        $this->assertDatabaseMissing('learning_video_renditions', ['learning_video_id' => $video->id]);
        foreach ($files as $path) {
            Storage::disk('private')->assertMissing($path);
        }
        Storage::disk('public')->assertMissing('learning/thumbnails/a.webp');
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_video_purged', 'entity_id' => $video->id]);

        // Room purge removes its recordings' files with the rows.
        $room = LearningRoom::create(['title' => 'Old room', 'host_id' => $admin->id, 'status' => 'completed']);
        Storage::disk('private')->put('learning/recordings/r.mp4', 'x');
        $room->recordings()->create(['source' => 'upload', 'status' => 'ready', 'disk' => 'private', 'path' => 'learning/recordings/r.mp4', 'size_bytes' => 1]);
        $deletions->deleteRoom($room, 'old');
        $deletions->purge('room', $room->id);
        $this->assertDatabaseMissing('learning_rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('learning_room_recordings', ['learning_room_id' => $room->id]);
        Storage::disk('private')->assertMissing('learning/recordings/r.mp4');
        $this->assertDatabaseHas('activity_logs', ['action' => 'learning_room_purged', 'entity_id' => $room->id]);

        // Retention: course (with a trashed lesson) and then its category go after the cutoff.
        $lesson = $this->video($category, $course, ['disk' => 'private', 'path' => 'learning/videos/b.mp4']);
        Storage::disk('private')->put('learning/videos/b.mp4', 'x');
        $deletions->deleteCourse($course, 'end of term', withVideos: true);
        $deletions->deleteCategory($category, 'end of term');

        $this->assertBlocked('including items in the trash', fn () => $deletions->purge('category', $category->id));
        $this->assertSame(0, $deletions->purgeExpired(30));
        $this->travel(31)->days();
        $this->assertSame(3, $deletions->purgeExpired(30));
        $this->assertDatabaseMissing('learning_courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('learning_videos', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('learning_categories', ['id' => $category->id]);
        Storage::disk('private')->assertMissing('learning/videos/b.mp4');
        Storage::disk('public')->assertMissing('learning/thumbnails/course.webp');
    }
}
