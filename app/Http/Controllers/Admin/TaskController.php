<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Team tasks: any admin gives a task to a team member (picked by name and
 * job title — CEO, MD, Developer…). The person who got it moves it along
 * (to do → in progress → done) and both sides talk in its comments. Each
 * admin sees the tasks they gave or got; tasks.manage sees them all.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canAll = $user->can('tasks.manage');
        $view = (string) $request->query('view', 'mine');
        if (! in_array($view, $canAll ? ['mine', 'given', 'all'] : ['mine', 'given'], true)) {
            $view = 'mine';
        }
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));

        $tasks = AdminTask::query()
            ->with(['assignee:id,name,job_title', 'creator:id,name'])
            ->withCount('comments')
            ->when($view === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($view === 'given', fn ($q) => $q->where('created_by', $user->id))
            ->when($view === 'all', fn ($q) => $q->visibleTo($user))
            ->when($status === 'open', fn ($q) => $q->open())
            ->when($status === 'done', fn ($q) => $q->where('status', 'done'))
            ->when($status === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            // Urgent and overdue work first.
            ->workOrder()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'mine' => AdminTask::query()->open()->where('assigned_to', $user->id)->count(),
            'given' => AdminTask::query()->open()->where('created_by', $user->id)->count(),
            'all' => $canAll ? AdminTask::query()->open()->count() : null,
        ];

        return view('admin.tasks.index', compact('tasks', 'counts', 'view', 'status', 'search', 'canAll'));
    }

    public function create(Request $request): View
    {
        $task = new AdminTask(['priority' => 'normal', 'status' => 'todo']);
        // "Give a task to…" links from elsewhere preselect the person.
        $task->assigned_to = $request->integer('to') ?: null;

        return view('admin.tasks.form', ['task' => $task, 'people' => AdminTask::people()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $task = AdminTask::create($data + ['created_by' => $user->id, 'status' => 'todo']);

        $task->notifyAssignee($user);

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Task created'.($task->assignee ? ' and given to '.$task->assignee->name : '').'.');
    }

    public function show(Request $request, AdminTask $task): View
    {
        abort_unless($task->canBeSeenBy($request->user()), 403);

        $task->load(['assignee:id,name,job_title,email', 'creator:id,name,job_title', 'comments.user:id,name,job_title']);

        return view('admin.tasks.show', [
            'task' => $task,
            'canEdit' => $task->canBeEditedBy($request->user()),
            'canMove' => $task->canBeMovedBy($request->user()),
        ]);
    }

    public function edit(Request $request, AdminTask $task): View
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);

        return view('admin.tasks.form', ['task' => $task, 'people' => AdminTask::people()]);
    }

    public function update(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($task->canBeEditedBy($user), 403);

        $before = $task->assigned_to;
        $task->fill($this->validated($request));
        if ($task->isDirty('due_at')) {
            $task->reminded_on = null;
        }
        $task->save();

        if ((int) $before !== (int) $task->assigned_to) {
            $task->notifyAssignee($user);
        }

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Task updated.');
    }

    /** To do → in progress → done (or cancelled), by the person who got it, who gave it, or a tasks manager. */
    public function status(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($task->canBeMovedBy($user), 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(AdminTask::STATUSES))]]);

        if ($data['status'] === $task->status) {
            return back();
        }

        $task->forceFill([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ])->save();

        $label = AdminTask::STATUSES[$task->status];
        $task->notifyOthers($user, $user->name.' marked "'.$task->title.'" as '.strtolower($label).'.');

        return back()->with('success', 'Task marked as '.strtolower($label).'.');
    }

    public function comment(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($task->canBeSeenBy($user), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $task->comments()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);
        $task->touch();

        $task->notifyOthers($user, $user->name.' commented on "'.$task->title.'".');

        return redirect()->to(route('admin.tasks.show', $task).'#comments')->with('success', 'Comment added.');
    }

    public function destroy(Request $request, AdminTask $task): RedirectResponse
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);

        $task->delete();

        return redirect()->route('admin.tasks.index', ['view' => 'given'])->with('success', 'Task deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(array_keys(AdminTask::PRIORITIES))],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', 1)->where('is_guest', 0)],
        ]);
    }
}
