<?php

namespace Tests\Feature\Api;

use App\Models\Research;
use App\Models\ResearchCategory;
use App\Models\ResearchChapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResearchAuthorApiTest extends TestCase
{
    use RefreshDatabase;

    private function writer(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['can_write_research' => true])->save();

        return $user;
    }

    public function test_author_can_write_and_submit_research_from_the_app(): void
    {
        Sanctum::actingAs($this->writer());
        $category = ResearchCategory::create(['name' => 'Education', 'slug' => 'education']);

        $this->getJson('/api/my-research/categories')->assertOk()->assertJsonPath('data.0.name', 'Education');

        $id = $this->postJson('/api/my-research', ['title' => 'Mobile learning in Tanzania', 'research_category_id' => $category->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.can_submit', false)
            ->json('data.id');

        $chapterId = $this->postJson("/api/my-research/{$id}/chapters", ['title' => 'Introduction'])
            ->assertCreated()
            ->json('data.chapters.0.id');

        $sectionId = $this->postJson("/api/my-research/chapters/{$chapterId}/sections", ['heading' => 'Background', 'body' => 'Mobile phones are everywhere.'])
            ->assertCreated()
            ->assertJsonPath('data.words', 4)
            ->json('data.id');

        $this->putJson("/api/my-research/sections/{$sectionId}", ['heading' => 'Background', 'body' => 'Updated text here.'])
            ->assertOk();
        $this->getJson("/api/my-research/sections/{$sectionId}")->assertJsonPath('data.body', 'Updated text here.');

        $this->postJson("/api/my-research/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        // Locked while under review.
        $this->postJson("/api/my-research/{$id}/chapters", ['title' => 'More'])->assertForbidden();
    }

    public function test_import_from_pasted_text(): void
    {
        Sanctum::actingAs($user = $this->writer());
        $research = Research::create(['title' => 'Import me', 'user_id' => $user->id, 'status' => 'draft']);

        $this->postJson("/api/my-research/{$research->id}/import", ['text' => "# Chapter one\n\n## Part A\n\nSome text."])
            ->assertOk()
            ->assertJsonPath('data.chapters.0.title', 'Chapter one')
            ->assertJsonPath('data.chapters.0.sections.0.heading', 'Part A');
    }

    public function test_other_users_cannot_touch_my_research(): void
    {
        $owner = $this->writer();
        $research = Research::create(['title' => 'Mine', 'user_id' => $owner->id, 'status' => 'draft']);

        Sanctum::actingAs($this->writer());
        $this->getJson("/api/my-research/{$research->id}")->assertNotFound();
        $this->putJson("/api/my-research/{$research->id}", ['title' => 'Hacked'])->assertNotFound();
    }

    public function test_users_without_writing_access_are_refused(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/my-research', ['title' => 'Nope'])->assertForbidden();
    }

    public function test_reorder_ignores_chapters_from_someone_elses_research(): void
    {
        $me = $this->writer();
        $mine = Research::create(['title' => 'Mine', 'user_id' => $me->id, 'status' => 'draft']);
        $a = $mine->chapters()->create(['title' => 'A', 'position' => 1]);
        $b = $mine->chapters()->create(['title' => 'B', 'position' => 2]);

        $other = Research::create(['title' => 'Theirs', 'user_id' => $this->writer()->id, 'status' => 'draft']);
        $theirs = $other->chapters()->create(['title' => 'X', 'position' => 1]);

        Sanctum::actingAs($me);
        $this->postJson("/api/my-research/{$mine->id}/reorder", ['type' => 'chapter', 'ids' => [$b->id, $theirs->id, $a->id]])
            ->assertOk();

        $this->assertSame(1, $b->fresh()->position);
        $this->assertSame(3, $a->fresh()->position);
        $this->assertSame(1, $theirs->fresh()->position); // untouched

        // Same protection on the web route.
        $this->actingAs($me)->postJson(route('research.contributor.reorder', $mine), ['type' => 'chapter', 'ids' => [$theirs->id]]);
        $this->assertSame(1, ResearchChapter::find($theirs->id)->position);
    }
}
