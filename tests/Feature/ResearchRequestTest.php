<?php

namespace Tests\Feature;

use App\Models\ResearchRequest;
use App\Models\ResearchRequestFile;
use App\Models\ResearchRequestStage;
use App\Models\User;
use App\Notifications\Admin\NewResearchRequest;
use App\Notifications\Admin\ResearchRequestActivity;
use App\Notifications\User\ResearchRequestUpdated;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Research work requests: a customer asks for research work, admins run it
 * through the stages (quote, drafts, revisions, delivery) and the customer
 * accepts and rates it. Covers the access rules: own requests only (web and
 * API), admin permission keys, internal notes never reach the customer,
 * files only through authorised routes.
 */
class ResearchRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake(ResearchRequestFile::DISK);
    }

    private function member(): User
    {
        return User::factory()->create();
    }

    private function admin(array $permissions): User
    {
        return User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions]);
    }

    private function brief(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Mobile money and small businesses in Arusha',
            'research_type' => 'proposal',
            'field_of_study' => 'Economics',
            'description' => 'I need a full research proposal with background, objectives, literature review and methodology. My supervisor wants a mixed-methods design.',
            'academic_level' => 'bachelor',
            'purpose' => 'Final year project',
            'deadline' => now()->addDays(20)->toDateString(),
            'budget' => 150000,
            'currency' => 'TZS',
            'pages' => 25,
            'citation_style' => 'apa',
            'priority' => 'high',
        ];
    }

    private function requestFor(User $customer, array $overrides = []): ResearchRequest
    {
        $this->actingAs($customer)
            ->post(route('user.research-requests.store'), $this->brief($overrides))
            ->assertRedirect();

        return ResearchRequest::query()->where('user_id', $customer->id)->latest('id')->firstOrFail();
    }

    public function test_default_stages_exist_in_process_order(): void
    {
        $this->assertSame(
            ['received', 'reviewing', 'quoted', 'in_progress', 'draft_ready', 'revisions', 'delivered', 'closed'],
            ResearchRequestStage::flow()->pluck('key')->all(),
        );
    }

    public function test_customer_sends_a_request_with_files_and_staff_with_permission_are_told(): void
    {
        $viewer = $this->admin(['research_requests.view']);
        $other = $this->admin(['orders.view']);
        $customer = $this->member();

        $this->actingAs($customer)->get(route('user.research-requests.create'))->assertOk()->assertSee('Ask for research work');

        $this->actingAs($customer)->post(route('user.research-requests.store'), $this->brief([
            'files' => [UploadedFile::fake()->create('guidelines.pdf', 120, 'application/pdf')],
        ]))->assertRedirect();

        $rr = ResearchRequest::query()->firstOrFail();
        $this->assertSame($customer->id, $rr->user_id);
        $this->assertSame('RR-'.str_pad((string) $rr->id, 5, '0', STR_PAD_LEFT), $rr->reference);
        $this->assertSame(ResearchRequestStage::RECEIVED, $rr->stage->key);
        $this->assertSame(5, $rr->progress);
        $this->assertSame('high', $rr->priority);

        $file = $rr->files()->firstOrFail();
        $this->assertSame('brief', $file->kind);
        $this->assertSame('guidelines.pdf', $file->original_name);
        Storage::disk(ResearchRequestFile::DISK)->assertExists($file->path);

        Notification::assertSentTo($viewer, NewResearchRequest::class);
        Notification::assertNotSentTo([$other, $customer], NewResearchRequest::class);

        $this->actingAs($customer)->get(route('user.research-requests.index'))->assertOk()->assertSee($rr->reference);
        $this->actingAs($customer)->get(route('user.research-requests.show', $rr))->assertOk()->assertSee('Where your work is');
    }

    public function test_form_rejects_bad_input_and_php_uploads(): void
    {
        $customer = $this->member();

        $this->actingAs($customer)->post(route('user.research-requests.store'), $this->brief([
            'description' => 'too short',
            'research_type' => 'hacking',
            'deadline' => now()->subDay()->toDateString(),
            'files' => [UploadedFile::fake()->create('shell.php', 1, 'text/x-php')],
        ]))->assertSessionHasErrors(['description', 'research_type', 'deadline', 'files.0']);

        $this->assertSame(0, ResearchRequest::count());
    }

    public function test_customer_cannot_see_or_act_on_another_customers_request(): void
    {
        $owner = $this->member();
        $stranger = $this->member();
        $rr = $this->requestFor($owner);
        $file = $rr->files()->create([
            'kind' => 'final', 'path' => 'research-requests/'.$rr->id.'/x.pdf', 'original_name' => 'final.pdf', 'size' => 10,
        ]);
        Storage::disk(ResearchRequestFile::DISK)->put($file->path, 'pdf');

        $this->actingAs($stranger)->get(route('user.research-requests.show', $rr))->assertNotFound();
        $this->actingAs($stranger)->post(route('user.research-requests.message', $rr), ['body' => 'hi'])->assertNotFound();
        $this->actingAs($stranger)->post(route('user.research-requests.cancel', $rr))->assertNotFound();
        $this->actingAs($stranger)->post(route('user.research-requests.complete', $rr), ['rating' => 5])->assertNotFound();
        $this->actingAs($stranger)->get(route('user.research-requests.file', [$rr, $file]))->assertNotFound();
        $this->actingAs($stranger)->get(route('user.research-requests.index'))->assertOk()->assertDontSee($rr->title);

        // API: same rules.
        Sanctum::actingAs($stranger);
        $this->getJson('/api/research-requests/'.$rr->id)->assertNotFound();
        $this->postJson('/api/research-requests/'.$rr->id.'/messages', ['body' => 'hi'])->assertNotFound();
        $this->getJson('/api/research-requests/'.$rr->id.'/files/'.$file->id.'/url')->assertNotFound();
        $this->getJson('/api/research-requests')->assertOk()->assertJsonCount(0, 'data');

        // A file of another request cannot be reached through the owner's own request either.
        $mine = $this->requestFor($stranger);
        $this->actingAs($stranger)->get(route('user.research-requests.file', [$mine, $file]))->assertNotFound();

        $this->assertSame(0, $rr->messages()->count());
        $this->assertTrue($rr->fresh()->isActive());
    }

    public function test_admin_routes_need_the_permission_keys(): void
    {
        $rr = $this->requestFor($this->member());
        $stage = ResearchRequestStage::byKey(ResearchRequestStage::IN_PROGRESS);

        $noPerm = $this->admin(['orders.view']);
        $this->actingAs($noPerm)->get(route('admin.research-requests.index'))->assertForbidden();
        $this->actingAs($noPerm)->get(route('admin.research-requests.show', $rr))->assertForbidden();
        $this->actingAs($noPerm)->get(route('admin.research-requests.stages.index'))->assertForbidden();

        $viewer = $this->admin(['research_requests.view']);
        $this->actingAs($viewer)->get(route('admin.research-requests.index'))->assertOk()->assertSee($rr->title);
        $this->actingAs($viewer)->get(route('admin.research-requests.index', ['view' => 'board']))->assertOk()->assertSee($rr->reference);
        $this->actingAs($viewer)->get(route('admin.research-requests.show', $rr))->assertOk();
        $this->actingAs($viewer)->post(route('admin.research-requests.stage', $rr), ['stage_id' => $stage->id])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.research-requests.message', $rr), ['body' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.research-requests.stages.store'), ['name' => 'X', 'color' => 'indigo', 'progress' => 10])->assertForbidden();

        // Customers never reach the admin side.
        $this->actingAs($rr->user)->get(route('admin.research-requests.index'))->assertForbidden();

        $this->assertSame(ResearchRequestStage::RECEIVED, $rr->fresh()->stage->key);
    }

    public function test_full_flow_quote_drafts_revision_delivery_and_rating(): void
    {
        $manager = $this->admin(['research_requests.view', 'research_requests.manage']);
        $customer = $this->member();
        $rr = $this->requestFor($customer);

        // Assign, then quote: moves to "Quoted", customer told.
        $this->actingAs($manager)->post(route('admin.research-requests.assign', $rr), ['assigned_to' => $manager->id])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.research-requests.quote', $rr), [
            'quote_amount' => 180000, 'quote_currency' => 'TZS', 'quote_note' => 'Includes two rounds of changes.',
        ])->assertRedirect();
        $rr->refresh();
        $this->assertSame('pending', $rr->quote_status);
        $this->assertSame(ResearchRequestStage::QUOTED, $rr->stage->key);
        $this->assertTrue($rr->canBeCancelledByCustomer());
        Notification::assertSentTo($customer, ResearchRequestUpdated::class, fn ($n) => $n->event === 'quote');

        // Customer accepts: in progress, assignee told.
        $this->actingAs($customer)->post(route('user.research-requests.quote.accept', $rr))->assertRedirect();
        $rr->refresh();
        $this->assertSame('accepted', $rr->quote_status);
        $this->assertSame(ResearchRequestStage::IN_PROGRESS, $rr->stage->key);
        $this->assertFalse($rr->canBeCancelledByCustomer());
        Notification::assertSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'quote_accepted');

        // Too late to cancel now.
        $this->actingAs($customer)->post(route('user.research-requests.cancel', $rr))->assertSessionHasErrors('reason');
        $this->assertTrue($rr->fresh()->isActive());

        // Stage move with a custom % and a note.
        $this->actingAs($manager)->post(route('admin.research-requests.stage', $rr), [
            'stage_id' => $rr->stage_id, 'progress' => 55, 'note' => 'Chapter two is done.', 'notify' => 1,
        ])->assertRedirect();
        $this->assertSame(55, $rr->fresh()->progress);

        // Draft: moves to "Draft ready"; customer can ask for changes.
        $this->actingAs($manager)->post(route('admin.research-requests.deliver', $rr), [
            'kind' => 'draft', 'note' => 'Chapters 1-3', 'move_stage' => 1,
            'files' => [UploadedFile::fake()->create('draft.docx', 50)],
        ])->assertRedirect();
        $rr->refresh();
        $this->assertSame(ResearchRequestStage::DRAFT_READY, $rr->stage->key);
        $this->assertTrue($rr->canRequestRevision());
        $this->assertFalse($rr->canBeCompletedByCustomer());

        $this->actingAs($customer)->post(route('user.research-requests.revision', $rr), ['reason' => 'Please add more recent sources to chapter two.'])->assertRedirect();
        $rr->refresh();
        $this->assertSame(ResearchRequestStage::REVISIONS, $rr->stage->key);
        $this->assertSame(1, $rr->revision_count);
        Notification::assertSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'revision');

        // Final work: "Delivered", customer downloads it, accepts and rates.
        $this->actingAs($manager)->post(route('admin.research-requests.deliver', $rr), [
            'kind' => 'final', 'move_stage' => 1, 'files' => [UploadedFile::fake()->create('final.pdf', 80, 'application/pdf')],
        ])->assertRedirect();
        $rr->refresh();
        $this->assertSame(ResearchRequestStage::DELIVERED, $rr->stage->key);
        $this->assertNotNull($rr->delivered_at);

        $final = $rr->files()->where('kind', 'final')->firstOrFail();
        $this->actingAs($customer)->get(route('user.research-requests.file', [$rr, $final]))->assertOk()->assertDownload('final.pdf');

        $this->actingAs($customer)->post(route('user.research-requests.complete', $rr), ['rating' => 5, 'review' => 'Excellent work'])->assertRedirect();
        $rr->refresh();
        $this->assertSame(ResearchRequest::COMPLETED, $rr->status);
        $this->assertSame(ResearchRequestStage::CLOSED, $rr->stage->key);
        $this->assertSame(100, $rr->progress);
        $this->assertSame(5, $rr->rating);

        // The history the customer sees tells the story.
        $types = $rr->events()->forCustomer()->pluck('type')->all();
        foreach (['created', 'quote_sent', 'quote_accepted', 'files', 'revision', 'completed'] as $type) {
            $this->assertContains($type, $types);
        }
    }

    public function test_internal_notes_never_reach_the_customer(): void
    {
        $manager = $this->admin(['research_requests.view', 'research_requests.manage']);
        $customer = $this->member();
        $rr = $this->requestFor($customer);

        $this->actingAs($manager)->post(route('admin.research-requests.message', $rr), [
            'body' => 'SECRET-NOTE customer seems to be a reseller', 'internal' => 1,
            'files' => [UploadedFile::fake()->create('internal-costing.xlsx', 10)],
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.research-requests.stage', $rr), [
            'stage_id' => ResearchRequestStage::byKey('reviewing')->id,
            'note' => 'We are reading your brief.', 'internal_note' => 'SECRET-STAGE budget too low', 'notify' => 1,
        ])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.research-requests.message', $rr), ['body' => 'Hello, which university?'])->assertRedirect();

        // Only the public message and stage note told the customer.
        Notification::assertSentTo($customer, ResearchRequestUpdated::class, fn ($n) => ! str_contains($n->message, 'SECRET'));
        Notification::assertNotSentTo($customer, ResearchRequestUpdated::class, fn ($n) => str_contains($n->message, 'SECRET'));

        // Staff see everything.
        $this->actingAs($manager)->get(route('admin.research-requests.show', $rr))
            ->assertOk()->assertSee('SECRET-NOTE')->assertSee('SECRET-STAGE')->assertSee('internal-costing.xlsx');

        // Web page for the customer.
        $this->actingAs($customer)->get(route('user.research-requests.show', $rr))
            ->assertOk()
            ->assertSee('Hello, which university?')
            ->assertSee('We are reading your brief.')
            ->assertDontSee('SECRET-NOTE')
            ->assertDontSee('SECRET-STAGE')
            ->assertDontSee('internal-costing.xlsx');

        // The internal file cannot be downloaded by the customer, even by id.
        $internalFile = $rr->files()->where('is_internal', true)->firstOrFail();
        $this->actingAs($customer)->get(route('user.research-requests.file', [$rr, $internalFile]))->assertNotFound();

        // API for the app.
        Sanctum::actingAs($customer);
        $json = $this->getJson('/api/research-requests/'.$rr->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('SECRET', $json);
        $this->assertStringNotContainsString('internal-costing', $json);
        $this->assertStringNotContainsString('internal_note', $json);
        $this->assertStringContainsString('Hello, which university?', $json);
        $this->getJson('/api/research-requests/'.$rr->id.'/files/'.$internalFile->id.'/url')->assertNotFound();
    }

    public function test_api_flow_for_the_app(): void
    {
        $manager = $this->admin(['research_requests.view', 'research_requests.manage']);
        $customer = $this->member();
        Sanctum::actingAs($customer);

        $this->getJson('/api/research-requests/options')->assertOk()
            ->assertJsonPath('types.0.value', 'thesis')
            ->assertJsonCount(8, 'stages');

        $id = $this->post('/api/research-requests', $this->brief([
            'files' => [UploadedFile::fake()->create('data.csv', 5, 'text/csv')],
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.stage.key', 'received')
            ->assertJsonPath('data.can.cancel', true)
            ->assertJsonCount(8, 'data.tracker')
            ->assertJsonPath('data.tracker.0.state', 'current')
            ->assertJsonCount(1, 'data.attachments')
            ->json('data.id');

        $this->postJson("/api/research-requests/{$id}/messages", ['body' => 'Any update?'])->assertCreated()->assertJsonPath('data.author', 'You');
        Notification::assertSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'message');

        $this->getJson('/api/research-requests')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('counts.active', 1);

        // A file link is a short-lived signed URL that works without a session.
        $fileId = ResearchRequest::query()->findOrFail($id)->files()->value('id');
        $url = $this->getJson("/api/research-requests/{$id}/files/{$fileId}/url")->assertOk()->json('url');
        $this->assertStringContainsString('signature=', $url);
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertDownload('data.csv');
        $this->get(route('signed.research-requests.file', ['file' => $fileId]))->assertForbidden();

        Sanctum::actingAs($customer);
        $this->postJson("/api/research-requests/{$id}/cancel", ['reason' => 'Found another way'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        Notification::assertSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'cancelled');
    }

    public function test_admin_can_manage_stages_but_not_delete_built_in_or_busy_ones(): void
    {
        $manager = $this->admin(['research_requests.view', 'research_requests.manage']);
        $received = ResearchRequestStage::byKey(ResearchRequestStage::RECEIVED);

        $this->actingAs($manager)->get(route('admin.research-requests.stages.index'))->assertOk()->assertSee('Process stages');

        $this->actingAs($manager)->post(route('admin.research-requests.stages.store'), [
            'name' => 'Data collection', 'color' => 'teal', 'progress' => 50, 'after' => ResearchRequestStage::byKey('in_progress')->id,
        ])->assertRedirect();
        $new = ResearchRequestStage::query()->where('name', 'Data collection')->firstOrFail();
        $this->assertSame(ResearchRequestStage::byKey('in_progress')->sort_order + 1, $new->sort_order);
        $this->assertSame(['received', 'reviewing', 'quoted', 'in_progress', null, 'draft_ready'], ResearchRequestStage::flow()->take(6)->pluck('key')->all());

        $this->actingAs($manager)->put(route('admin.research-requests.stages.update', $received), [
            'name' => 'Imepokelewa', 'color' => 'gray', 'progress' => 5, 'allows_cancel' => 1, 'is_active' => 0,
        ])->assertRedirect();
        $received->refresh();
        $this->assertSame('Imepokelewa', $received->name);
        $this->assertTrue($received->is_active, 'built-in stages stay on');

        $this->actingAs($manager)->delete(route('admin.research-requests.stages.destroy', $received))->assertRedirect();
        $this->assertModelExists($received);

        $this->requestFor($this->member());
        ResearchRequest::query()->update(['stage_id' => $new->id]);
        $this->actingAs($manager)->delete(route('admin.research-requests.stages.destroy', $new))->assertRedirect();
        $this->assertModelExists($new);

        ResearchRequest::query()->update(['stage_id' => $received->id]);
        $this->actingAs($manager)->delete(route('admin.research-requests.stages.destroy', $new))->assertRedirect();
        $this->assertModelMissing($new);
    }

    public function test_board_filters_and_deadline_warnings(): void
    {
        $manager = $this->admin(['research_requests.view', 'research_requests.manage']);
        $late = $this->requestFor($this->member(), ['title' => 'Late thesis work']);
        $late->forceFill(['deadline' => now()->subDays(2)->toDateString()])->save();
        $fine = $this->requestFor($this->member(), ['title' => 'Relaxed proposal work', 'deadline' => now()->addDays(30)->toDateString()]);

        $this->assertTrue($late->fresh()->isOverdue());
        $this->actingAs($manager)->get(route('admin.research-requests.index', ['deadline' => 'overdue']))
            ->assertOk()->assertSee('Late thesis work')->assertDontSee('Relaxed proposal work');

        $this->artisan('research-requests:deadlines')->assertSuccessful();
        Notification::assertSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'deadline' && $n->request->is($late));
        $this->assertNotNull($late->fresh()->deadline_alerted_on);
        Notification::assertNotSentTo($manager, ResearchRequestActivity::class, fn ($n) => $n->event === 'deadline' && $n->request->is($fine));
    }
}
