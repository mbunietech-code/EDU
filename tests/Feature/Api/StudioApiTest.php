<?php

namespace Tests\Feature\Api;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudioApiTest extends TestCase
{
    use RefreshDatabase;

    private const DISKS = ['private', 'public', 'local'];

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Own folder per process, like StudioVideosTest (parallel-safe chunked uploads).
        $this->diskRoot = storage_path('framework/testing/disks/studio-api-'.getmypid());
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

    private function instructor(): User
    {
        return User::factory()->create(['can_teach' => true]);
    }

    private function mp4Bytes(int $size = 3000): string
    {
        $head = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

        return str_pad($head.pack('N', $size - strlen($head)).'mdat', $size, "\x00");
    }

    public function test_plain_members_cannot_use_the_studio(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/studio')->assertForbidden();
        $this->postJson('/api/studio/rooms', ['title' => 'x', 'duration_minutes' => 60, 'access' => 'public'])->assertForbidden();
    }

    public function test_instructor_schedules_edits_and_cancels_a_class(): void
    {
        Sanctum::actingAs($teacher = $this->instructor());
        $category = LearningCategory::create(['name' => 'Science']);

        $this->getJson('/api/studio')
            ->assertOk()
            ->assertJsonPath('data.can_host_rooms', true)
            ->assertJsonPath('data.options.duration.min', 5);

        $when = now()->addDay();
        $room = $this->postJson('/api/studio/rooms', [
            'title' => 'Physics revision',
            'learning_category_id' => $category->id,
            'scheduled_date' => $when->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'duration_minutes' => 90,
            'access' => 'public',
            'action' => 'schedule',
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled')->json('data');

        $this->assertSame($teacher->id, LearningRoom::find($room['id'])->host_id);

        // A start time in the past is refused (same rule as the web Studio).
        $this->putJson("/api/studio/rooms/{$room['id']}", [
            'title' => 'Physics revision', 'scheduled_date' => now()->subDay()->format('Y-m-d'), 'scheduled_time' => '10:00',
            'duration_minutes' => 90, 'access' => 'public',
        ])->assertStatus(422)->assertJsonValidationErrors('scheduled_date');

        $this->putJson("/api/studio/rooms/{$room['id']}", [
            'title' => 'Physics revision (updated)', 'scheduled_date' => $when->format('Y-m-d'), 'scheduled_time' => '11:30',
            'duration_minutes' => 60, 'access' => 'public', 'learning_category_id' => $category->id,
        ])->assertOk()->assertJsonPath('data.title', 'Physics revision (updated)');

        $this->postJson("/api/studio/rooms/{$room['id']}/cancel", ['reason' => 'Teacher is ill'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_instructor_cannot_edit_someone_elses_class(): void
    {
        $other = $this->instructor();
        $room = LearningRoom::create(['title' => 'Theirs', 'host_id' => $other->id, 'status' => 'draft', 'access' => 'public', 'duration_minutes' => 60]);

        Sanctum::actingAs($this->instructor());
        $this->getJson("/api/studio/rooms/{$room->id}")->assertForbidden();
    }

    public function test_instructor_uploads_and_publishes_a_lesson(): void
    {
        Sanctum::actingAs($teacher = $this->instructor());
        $category = LearningCategory::create(['name' => 'Maths']);
        $bytes = $this->mp4Bytes();

        $this->getJson('/api/studio/uploads/config')->assertOk()->assertJsonStructure(['chunk_bytes']);

        $token = $this->postJson('/api/studio/uploads', ['purpose' => 'video', 'filename' => 'lesson.mp4', 'size' => strlen($bytes)])
            ->assertCreated()->json('token');
        foreach (str_split($bytes, 1024) as $i => $part) {
            $this->post("/api/studio/uploads/{$token}/chunk", [
                'index' => $i, 'chunk' => UploadedFile::fake()->createWithContent('blob', $part),
            ], ['Accept' => 'application/json'])->assertOk();
        }
        $this->postJson("/api/studio/uploads/{$token}/complete")->assertOk();

        $video = $this->postJson('/api/studio/videos', [
            'title' => 'Algebra basics', 'learning_category_id' => $category->id, 'visibility' => 'members',
            'upload_token' => $token, 'action' => 'draft',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.has_file', true)->json('data');

        $this->postJson("/api/studio/videos/{$video['id']}/publish", ['notify' => false])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->postJson("/api/studio/videos/{$video['id']}/unpublish")->assertJsonPath('data.status', 'draft');

        $this->assertSame($teacher->id, LearningVideo::find($video['id'])->instructor_id);
    }

    public function test_instructor_cannot_put_a_lesson_in_a_course_they_do_not_teach(): void
    {
        Sanctum::actingAs($this->instructor());
        $category = LearningCategory::create(['name' => 'History']);
        $course = LearningCourse::create([
            'learning_category_id' => $category->id, 'title' => 'Not mine', 'status' => 'published',
            'access' => 'open', 'instructor_id' => $this->instructor()->id,
        ]);

        $this->postJson('/api/studio/videos', [
            'title' => 'Sneaky', 'learning_course_id' => $course->id, 'learning_category_id' => $category->id, 'visibility' => 'course',
        ])->assertStatus(422)->assertJsonValidationErrors('learning_course_id');
    }
}
