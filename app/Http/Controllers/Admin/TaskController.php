<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Models\User;
use App\Notifications\Admin\TaskAssigned;
use App\Notifications\Admin\TaskUpdated;
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
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->latest('id')
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

        return view('admin.tasks.form', ['task' => $task, 'people' => $this->people()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $task = AdminTask::create($data + ['created_by' => $user->id, 'status' => 'todo']);

        $this->notifyAssignee($task, $user);

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Task created'.($task->assignee ? ' and given to '.$task->assignee->name : '').'.');
    }

    public function show(Request $request, AdminTask $task): View
    {
        $this->authorizeSee($request->user(), $task);

        $task->load(['assignee:id,name,job_title,email', 'creator:id,name,job_title', 'comments.user:id,name,job_title']);

        return view('admin.tasks.show', [
            'task' => $task,
            'canEdit' => $this->canEdit($request->user(), $task),
            'canMove' => $this->canMove($request->user(), $task),
        ]);
    }

    public function edit(Request $request, AdminTask $task): View
    {
        abort_unless($this->canEdit($request->user(), $task), 403);

        return view('admin.tasks.form', ['task' => $task, 'people' => $this->people()]);
    }

    public function update(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $task), 403);

        $before = $task->assigned_to;
        $task->fill($this->validated($request));
        if ($task->isDirty('due_at')) {
            $task->reminded_on = null;
        }
        $task->save();

        if ((int) $before !== (int) $task->assigned_to) {
            $this->notifyAssignee($task, $user);
        }

        return redirect()->route('admin.tasks.show', $task)->with('success', 'Task updated.');
    }

    /** To do → in progress → done (or cancelled), by the person who got it, who gave it, or a tasks manager. */
    public function status(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canMove($user, $task), 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(AdminTask::STATUSES))]]);

        if ($data['status'] === $task->status) {
            return back();
        }

        $task->forceFill([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ])->save();

        $label = AdminTask::STATUSES[$task->status];
        $this->notifyOthers($task, $user, $user->name.' marked "'.$task->title.'" as '.strtolower($label).'.');

        return back()->with('success', 'Task marked as '.strtolower($label).'.');
    }

    public function comment(Request $request, AdminTask $task): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeSee($user, $task);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $task->comments()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);
        $task->touch();

        $this->notifyOthers($task, $user, $user->name.' commented on "'.$task->title.'".');

        return redirect()->to(route('admin.tasks.show', $task).'#comments')->with('success', 'Comment added.');
    }

    public function destroy(Request $request, AdminTask $task): RedirectResponse
    {
        abort_unless($this->canEdit($request->user(), $task), 403);

        $task->delete();

        return redirect()->route('admin.tasks.index', ['view' => 'given'])->with('success', 'Task deleted.');
    }

    // --- Rules ------------------------------------------------------------
    private function authorizeSee(User $user, AdminTask $task): void
    {
        abort_unless($task->involves($user) || $user->can('tasks.manage'), 403);
    }

    /** Edit / delete / re-assign: who gave it, or a tasks manager. */
    private function canEdit(User $user, AdminTask $task): bool
    {
        return (int) $task->created_by === (int) $user->id || $user->can('tasks.manage');
    }

    /** Change the status: the person who got it, who gave it, or a tasks manager. */
    private function canMove(User $user, AdminTask $task): bool
    {
        return $task->involves($user) || $user->can('tasks.manage');
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

    private function people()
    {
        return User::query()->where('is_admin', true)->where('is_guest', false)->where('status', 'active')
            ->orderByRaw('job_title IS NULL')->orderBy('job_title')->orderBy('name')
            ->get(['id', 'name', 'job_title']);
    }

    private function notifyAssignee(AdminTask $task, User $by): void
    {
        $task->load('assignee');

        if ($task->assignee && (int) $task->assignee->id !== (int) $by->id) {
            $task->assignee->notify(new TaskAssigned($task, $by->name));
        }
    }

    /** Tell whoever gave / got the task, except the person who just acted. */
    private function notifyOthers(AdminTask $task, User $by, string $message): void
    {
        collect([$task->assigned_to, $task->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $by->id)
            ->each(fn ($id) => User::query()->find($id)?->notify(new TaskUpdated($task, $message)));
    }
}
