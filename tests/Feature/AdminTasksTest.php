<?php

namespace Tests\Feature;

use App\Models\AdminTask;
use App\Models\User;
use App\Notifications\Admin\TaskAssigned;
use App\Notifications\Admin\TaskDueReminder;
use App\Notifications\Admin\TaskUpdated;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Team tasks: give work to the CEO, MD, a developer…, follow it, talk about it, get reminded. */
class AdminTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function admin(string $title, array $permissions = ['orders.view']): User
    {
        return User::factory()->create([
            'is_admin' => true, 'role' => Permissions::ROLE_ADMIN, 'permissions' => $permissions, 'job_title' => $title,
        ]);
    }

    private function give(User $from, User $to, array $extra = []): AdminTask
    {
        $this->actingAs($from)->post(route('admin.tasks.store'), $extra + [
            'title' => 'Fix the payment page',
            'priority' => 'high',
            'due_at' => now()->addDays(3)->toDateString(),
            'assigned_to' => $to->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return AdminTask::query()->latest('id')->firstOrFail();
    }

    public function test_an_admin_gives_a_task_to_a_person_by_job_title_and_they_are_told(): void
    {
        $md = $this->admin('MD');
        $dev = $this->admin('Developer');

        $form = $this->actingAs($md)->get(route('admin.tasks.create'))->assertOk();
        $form->assertSee('Developer — '.$dev->name);

        $task = $this->give($md, $dev);
        $this->assertSame([$md->id, $dev->id, 'todo'], [$task->created_by, $task->assigned_to, $task->status]);
        Notification::assertSentTo($dev, TaskAssigned::class, fn ($n) => $n->task->is($task));

        $this->actingAs($dev)->get(route('admin.tasks.index'))->assertOk()->assertSee('Fix the payment page');
        $this->actingAs($md)->get(route('admin.tasks.index', ['view' => 'given']))->assertOk()->assertSee('Fix the payment page');
    }

    public function test_people_see_only_their_tasks_unless_they_manage_tasks(): void
    {
        $ceo = $this->admin('CEO');
        $dev = $this->admin('Developer');
        $outsider = $this->admin('Marketing');
        $manager = $this->admin('Operations', ['tasks.manage']);
        $task = $this->give($ceo, $dev);

        $this->actingAs($outsider)->get(route('admin.tasks.show', $task))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.tasks.status', $task), ['status' => 'done'])->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.tasks.comment', $task), ['body' => 'hi'])->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.tasks.index', ['view' => 'all']))->assertOk()->assertDontSee('Fix the payment page');

        $this->actingAs($manager)->get(route('admin.tasks.index', ['view' => 'all']))->assertOk()->assertSee('Fix the payment page');
        $this->actingAs($manager)->get(route('admin.tasks.show', $task))->assertOk();
    }

    public function test_the_person_moves_it_along_and_the_giver_is_told(): void
    {
        $ceo = $this->admin('CEO');
        $dev = $this->admin('Developer');
        $task = $this->give($ceo, $dev);

        $this->actingAs($dev)->post(route('admin.tasks.status', $task), ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($dev)->post(route('admin.tasks.status', $task), ['status' => 'done'])->assertRedirect();

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        Notification::assertSentToTimes($ceo, TaskUpdated::class, 2);
        Notification::assertNotSentTo($dev, TaskUpdated::class);

        $this->actingAs($dev)->post(route('admin.tasks.status', $task), ['status' => 'paused'])->assertSessionHasErrors('status');
    }

    public function test_comments_reach_the_other_side(): void
    {
        $ceo = $this->admin('CEO');
        $dev = $this->admin('Developer');
        $task = $this->give($ceo, $dev);

        $this->actingAs($dev)->post(route('admin.tasks.comment', $task), ['body' => 'Nimeanza, nitamaliza kesho.'])->assertRedirect();

        $this->actingAs($ceo)->get(route('admin.tasks.show', $task))->assertOk()->assertSee('Nimeanza, nitamaliza kesho.');
        Notification::assertSentTo($ceo, TaskUpdated::class);
    }

    public function test_only_the_giver_or_a_tasks_manager_edits_and_reassigning_tells_the_new_person(): void
    {
        $ceo = $this->admin('CEO');
        $dev = $this->admin('Developer');
        $designer = $this->admin('Designer');
        $task = $this->give($ceo, $dev);

        $this->actingAs($dev)->get(route('admin.tasks.edit', $task))->assertForbidden();
        $this->actingAs($dev)->delete(route('admin.tasks.destroy', $task))->assertForbidden();

        $this->actingAs($ceo)->put(route('admin.tasks.update', $task), [
            'title' => 'Redesign the payment page', 'priority' => 'urgent', 'assigned_to' => $designer->id,
        ])->assertRedirect();
        $this->assertSame([$designer->id, 'urgent'], [$task->fresh()->assigned_to, $task->fresh()->priority]);
        Notification::assertSentTo($designer, TaskAssigned::class);

        $this->actingAs($ceo)->delete(route('admin.tasks.destroy', $task))->assertRedirect();
        $this->assertSame(0, AdminTask::count());
    }

    public function test_tasks_go_only_to_admins(): void
    {
        $ceo = $this->admin('CEO');

        $this->actingAs($ceo)->post(route('admin.tasks.store'), [
            'title' => 'x', 'priority' => 'normal', 'assigned_to' => User::factory()->create()->id,
        ])->assertSessionHasErrors('assigned_to');
    }

    public function test_due_reminders_the_day_before_on_the_day_and_once_when_overdue(): void
    {
        $ceo = $this->admin('CEO');
        $dev = $this->admin('Developer');
        $task = $this->give($ceo, $dev, ['due_at' => now()->addDay()->toDateString()]);
        $this->give($ceo, $dev, ['title' => 'Later', 'due_at' => now()->addDays(10)->toDateString()]);

        $this->artisan('tasks:remind')->assertSuccessful();
        $this->artisan('tasks:remind')->assertSuccessful(); // same day: no repeat
        Notification::assertSentToTimes($dev, TaskDueReminder::class, 1);

        $this->travel(1)->days();
        $this->artisan('tasks:remind')->assertSuccessful();
        Notification::assertSentToTimes($dev, TaskDueReminder::class, 2);

        $this->travel(1)->days();
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('tasks:remind')->assertSuccessful(); // overdue only once
        Notification::assertSentToTimes($dev, TaskDueReminder::class, 3);

        // Done tasks are never reminded.
        $task->update(['status' => 'done']);
        $this->travel(1)->days();
        $this->artisan('tasks:remind')->assertSuccessful();
        Notification::assertSentToTimes($dev, TaskDueReminder::class, 3);
    }

    public function test_job_titles_are_set_in_team(): void
    {
        $super = User::factory()->create(['is_admin' => true, 'role' => Permissions::ROLE_SUPER_ADMIN, 'permissions' => null]);
        $member = $this->admin('');

        $this->actingAs($super)->put(route('admin.team.update', $member), ['role' => 'admin', 'permissions' => [], 'job_title' => '  Lead   Developer '])->assertRedirect();
        $this->assertSame('Lead Developer', $member->fresh()->job_title);
        $this->actingAs($super)->get(route('admin.team.index'))->assertOk()->assertSee('Lead Developer');
    }
}
