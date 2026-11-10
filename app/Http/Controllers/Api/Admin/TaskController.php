<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Models\AdminTaskComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Team tasks for the MHub app: the same rules as admin/tasks on the web.
 * Each admin sees the tasks they gave or got; tasks.manage sees them all.
 */
class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canAll = $user->can('tasks.manage');
        $view = (string) $request->query('view', 'mine');
        if (! in_array($view, $canAll ? ['mine', 'given', 'all'] : ['mine', 'given'], true)) {
            $view = 'mine';
        }
        $status = in_array($request->query('status'), ['open', 'done', 'cancelled', 'any'], true)
            ? (string) $request->query('status') : 'open';
        $search = trim((string) $request->query('q', ''));

        $tasks = AdminTask::query()
            ->with(['assignee:id,name,job_title', 'creator:id,name,job_title'])
            ->withCount('comments')
            ->when($view === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($view === 'given', fn ($q) => $q->where('created_by', $user->id))
            ->when($view === 'all', fn ($q) => $q->visibleTo($user))
            ->when($status === 'open', fn ($q) => $q->open())
            ->when(in_array($status, ['done', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->workOrder()
            ->paginate(25);

        return response()->json([
            'data' => collect($tasks->items())->map(fn (AdminTask $t) => $this->row($t))->values(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
                'view' => $view,
                'status' => $status,
                'can_see_all' => $canAll,
                'counts' => [
                    'mine' => AdminTask::query()->open()->where('assigned_to', $user->id)->count(),
                    'given' => AdminTask::query()->open()->where('created_by', $user->id)->count(),
                    'all' => $canAll ? AdminTask::query()->open()->count() : null,
                ],
                'priorities' => AdminTask::PRIORITIES,
                'statuses' => AdminTask::STATUSES,
            ],
        ]);
    }

    /** Admins a task can be given to (name + job title). */
    public function people(): JsonResponse
    {
        return response()->json([
            'data' => AdminTask::people()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'job_title' => $u->job_title,
            ])->values(),
            'priorities' => AdminTask::PRIORITIES,
        ]);
    }

    public function show(Request $request, AdminTask $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($task->canBeSeenBy($user), 403);

        $task->load(['assignee:id,name,job_title,email', 'creator:id,name,job_title', 'comments.user:id,name,job_title']);

        return response()->json(['data' => $this->row($task) + [
            'description' => $task->description,
            'can_edit' => $task->canBeEditedBy($user),
            'can_move' => $task->canBeMovedBy($user),
            'comments' => $task->comments->map(fn (AdminTaskComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user_name' => $c->user?->name ?? 'Someone',
                'user_job_title' => $c->user?->job_title,
                'mine' => (int) $c->user_id === (int) $user->id,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'created_ago' => optional($c->created_at)->diffForHumans(),
            ])->values(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $task = AdminTask::create($this->validated($request) + ['created_by' => $user->id, 'status' => 'todo']);
        $task->notifyAssignee($user);

        return response()->json([
            'message' => 'Task created'.($task->assignee ? ' and given to '.$task->assignee->name : '').'.',
            'data' => ['id' => $task->id],
        ], 201);
    }

    public function update(Request $request, AdminTask $task): JsonResponse
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

        return response()->json(['message' => 'Task updated.']);
    }

    public function status(Request $request, AdminTask $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($task->canBeMovedBy($user), 403);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(AdminTask::STATUSES))]]);

        if ($data['status'] !== $task->status) {
            $task->forceFill([
                'status' => $data['status'],
                'completed_at' => $data['status'] === 'done' ? now() : null,
            ])->save();

            $label = AdminTask::STATUSES[$task->status];
            $task->notifyOthers($user, $user->name.' marked "'.$task->title.'" as '.strtolower($label).'.');
        }

        return response()->json(['message' => 'Task marked as '.strtolower(AdminTask::STATUSES[$task->status]).'.']);
    }

    public function comment(Request $request, AdminTask $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($task->canBeSeenBy($user), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $task->comments()->create(['user_id' => $user->id, 'body' => trim($data['body'])]);
        $task->touch();
        $task->notifyOthers($user, $user->name.' commented on "'.$task->title.'".');

        return response()->json(['message' => 'Comment added.'], 201);
    }

    public function destroy(Request $request, AdminTask $task): JsonResponse
    {
        abort_unless($task->canBeEditedBy($request->user()), 403);

        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    private function row(AdminTask $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status,
            'status_label' => AdminTask::STATUSES[$t->status] ?? $t->status,
            'priority' => $t->priority,
            'priority_label' => AdminTask::PRIORITIES[$t->priority] ?? $t->priority,
            'due_at' => optional($t->due_at)->toDateString(),
            'overdue' => $t->isOverdue(),
            'assigned_to' => $t->assigned_to,
            'assignee_name' => $t->assignee?->name,
            'assignee_job_title' => $t->assignee?->job_title,
            'created_by' => $t->created_by,
            'creator_name' => $t->creator?->name,
            'comments_count' => (int) ($t->comments_count ?? $t->comments()->count()),
            'completed_at' => optional($t->completed_at)->toIso8601String(),
            'updated_ago' => optional($t->updated_at)->diffForHumans(),
        ];
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
