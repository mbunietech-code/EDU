<?php

namespace Tests\Feature\Api;

use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminResearchReviewApiTest extends TestCase
{
    use RefreshDatabase;

    private function submitted(): Research
    {
        $author = User::factory()->create();
        $research = Research::create(['title' => 'Malaria in Mbeya', 'user_id' => $author->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $chapter = $research->chapters()->create(['title' => 'Introduction', 'position' => 1]);
        $chapter->sections()->create(['heading' => 'Background', 'body' => 'Some **text**', 'position' => 1]);

        return $research;
    }

    public function test_admin_reviews_and_publishes_a_submission(): void
    {
        Notification::fake();
        Sanctum::actingAs($admin = User::factory()->admin()->create());
        $research = $this->submitted();

        $this->getJson('/api/admin/research')->assertOk()
            ->assertJsonPath('data.0.id', $research->id)
            ->assertJsonPath('meta.review_count', 1);

        // Opening it starts the review and returns the full text.
        $this->getJson("/api/admin/research/{$research->id}")->assertOk()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath('data.chapters.0.sections.0.body', 'Some **text**');

        $this->postJson("/api/admin/research/{$research->id}/request-changes", [])->assertStatus(422);
        $this->postJson("/api/admin/research/{$research->id}/request-changes", ['comment' => 'Add sources'])
            ->assertOk()->assertJsonPath('data.status', 'changes_requested');

        $this->postJson("/api/admin/research/{$research->id}/approve", ['comment' => 'Good'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($research->fresh()->published_at);

        $this->postJson("/api/admin/research/{$research->id}/unpublish")->assertOk()->assertJsonPath('data.status', 'approved');

        // Newest decision first.
        $this->assertSame(['unpublished', 'published', 'changes_requested'],
            $this->getJson("/api/admin/research/{$research->id}")->json('data.reviews.*.action'));

        $this->deleteJson("/api/admin/research/{$research->id}", [])->assertStatus(422);
        $this->deleteJson("/api/admin/research/{$research->id}", ['reason' => 'Duplicate'])->assertOk();
        $this->assertNull(Research::find($research->id));
    }

    public function test_reject_and_categories(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->admin()->create());
        $research = $this->submitted();

        $this->postJson("/api/admin/research/{$research->id}/reject", ['comment' => 'Off topic'])
            ->assertOk()->assertJsonPath('data.status', 'archived');

        $id = $this->postJson('/api/admin/research-categories', ['name' => 'Health'])
            ->assertCreated()->assertJsonPath('data.slug', 'health')->json('data.id');
        $this->putJson("/api/admin/research-categories/{$id}", ['name' => 'Public health'])
            ->assertOk()->assertJsonPath('data.slug', 'health');
        $this->getJson('/api/admin/research-categories')->assertOk()->assertJsonPath('data.0.name', 'Public health');
        $this->deleteJson("/api/admin/research-categories/{$id}")->assertOk();
        $this->assertNull(ResearchCategory::find($id));
    }

    public function test_permissions_are_enforced(): void
    {
        $research = $this->submitted();

        // Reader-only admin: sees the queue, cannot decide, does not start the review.
        Sanctum::actingAs(User::factory()->admin()->create(['role' => 'admin', 'permissions' => ['research.view']]));
        $this->getJson('/api/admin/research')->assertOk()->assertJsonPath('meta.can_manage', false);
        $this->getJson("/api/admin/research/{$research->id}")->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->postJson("/api/admin/research/{$research->id}/approve")->assertForbidden();

        // Admin without research access, and a normal member.
        Sanctum::actingAs(User::factory()->admin()->create(['role' => 'admin', 'permissions' => ['orders.view']]));
        $this->getJson('/api/admin/research')->assertForbidden();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/research')->assertForbidden();
    }
}
