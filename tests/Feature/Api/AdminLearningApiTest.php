<?php

namespace Tests\Feature\Api;

use App\Models\LearningCategory;
use App\Models\LearningCourse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLearningApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_admin_manages_categories_courses_and_enrolments(): void
    {
        Sanctum::actingAs($admin = User::factory()->admin()->create());
        $learner = User::factory()->create();

        $this->getJson('/api/admin/learning')->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.guest_links', false);

        $categoryId = $this->postJson('/api/admin/learning/categories', ['name' => 'Statistics'])->assertCreated()->json('data.id');
        $this->putJson("/api/admin/learning/categories/{$categoryId}", ['name' => 'Data & statistics'])->assertOk();
        $this->getJson('/api/admin/learning/categories')->assertJsonPath('data.0.name', 'Data & statistics');

        $courseId = $this->postJson('/api/admin/learning/courses', [
            'learning_category_id' => $categoryId,
            'title' => 'SPSS basics',
            'access' => 'enrolled',
            'status' => 'draft',
            'instructor_id' => $admin->id,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/admin/learning/courses/{$courseId}", [
            'learning_category_id' => $categoryId,
            'title' => 'SPSS for beginners',
            'access' => 'enrolled',
            'status' => 'published',
            'level' => 'beginner',
        ])->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull(LearningCourse::find($courseId)->published_at);

        $this->getJson('/api/admin/learning/courses?q=SPSS')->assertOk()->assertJsonPath('data.0.title', 'SPSS for beginners');

        $this->postJson("/api/admin/learning/courses/{$courseId}/enrolments", ['user_ids' => [$learner->id]])
            ->assertOk()->assertJsonPath('added', 1);
        $enrolment = $this->getJson("/api/admin/learning/courses/{$courseId}")->assertOk()->json('data.enrolments.0');
        $this->assertSame($learner->id, $enrolment['user_id']);
        $this->deleteJson("/api/admin/learning/courses/{$courseId}/enrolments/{$enrolment['id']}", ['reason' => 'Asked to leave'])
            ->assertOk();

        // Trash: delete, restore, delete again, purge.
        $this->deleteJson("/api/admin/learning/courses/{$courseId}", ['reason' => 'Old course'])->assertOk();
        $this->getJson('/api/admin/learning/trash?type=course')->assertOk()->assertJsonPath('data.0.id', $courseId);
        $this->postJson("/api/admin/learning/trash/course/{$courseId}/restore")->assertOk();
        $this->assertNotNull(LearningCourse::find($courseId));
        $this->deleteJson("/api/admin/learning/courses/{$courseId}", ['reason' => 'Old course'])->assertOk();
        $this->deleteJson("/api/admin/learning/trash/course/{$courseId}", ['reason' => 'For good'])->assertOk();
        $this->assertNull(LearningCourse::withTrashed()->find($courseId));

        $this->postJson('/api/admin/learning/guest-links', ['enabled' => true])->assertOk()->assertJsonPath('guest_links', true);
        $this->deleteJson("/api/admin/learning/categories/{$categoryId}", ['reason' => 'Merged'])->assertOk();
        $this->assertSoftDeleted(LearningCategory::withTrashed()->find($categoryId));
    }

    public function test_view_only_admin_cannot_change_learning(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(['role' => 'admin', 'permissions' => ['learning.view']]));

        $this->getJson('/api/admin/learning/courses')->assertOk();
        $this->getJson('/api/admin/learning')->assertOk()->assertJsonPath('data.can_manage', false);
        $this->postJson('/api/admin/learning/categories', ['name' => 'X'])->assertForbidden();
        $this->getJson('/api/admin/learning/trash')->assertForbidden();
        $this->postJson('/api/admin/learning/guest-links', ['enabled' => true])->assertForbidden();
    }
}
