<x-layouts.admin title="Tasks" header="Company">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Tasks</h1>
            <p class="mt-1 text-sm text-gray-500">Give work to the right person — CEO, MD, developer… — and follow it until it is done.</p>
        </div>
        <x-mbui.btn-link :href="route('admin.tasks.create')">+ New task</x-mbui.btn-link>
    </div>

    {{-- Whose tasks --}}
    <div class="mt-6 flex flex-wrap gap-1 border-b border-gray-200 text-sm" role="tablist" aria-label="Whose tasks">
        @foreach (array_filter(['mine' => 'Given to me', 'given' => 'I gave', 'all' => $canAll ? 'Everyone\'s' : null]) as $key => $label)
            <a href="{{ route('admin.tasks.index', array_filter(['view' => $key, 'status' => $status !== 'open' ? $status : null])) }}"
                role="tab" aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                class="-mb-px border-b-2 px-3 py-2 font-medium {{ $view === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                {{ $label }}
                <span class="ml-1 rounded-full px-1.5 text-xs {{ $view === $key ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.tasks.index') }}" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="flex flex-wrap gap-1">
            @foreach (['open' => 'Open', 'done' => 'Done', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
                <a href="{{ route('admin.tasks.index', array_filter(['view' => $view, 'status' => $key === 'open' ? null : $key, 'q' => $search ?: null])) }}"
                    class="rounded-full px-3 py-1.5 text-sm font-medium {{ $status === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($status !== 'open')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div class="flex flex-1 gap-2 sm:justify-end">
            <input type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search tasks…" class="mbui-input min-w-0 flex-1 sm:max-w-xs" aria-label="Search tasks">
            <x-mbui.button type="submit" variant="secondary">Search</x-mbui.button>
        </div>
    </form>

    @if ($tasks->isEmpty())
        <div class="mbui-card mt-4">
            <x-mbui.empty-state title="No tasks here" :message="$view === 'mine' ? 'Nobody has given you an open task.' : 'Create a task and give it to someone.'" />
        </div>
    @else
        <ul class="mt-4 space-y-2" role="list">
            @foreach ($tasks as $task)
                @php
                    $prio = ['urgent' => 'bg-red-500', 'high' => 'bg-amber-500', 'normal' => 'bg-indigo-400', 'low' => 'bg-gray-300'][$task->priority] ?? 'bg-gray-300';
                    $statusBadge = ['todo' => 'neutral', 'in_progress' => 'info', 'done' => 'success', 'cancelled' => 'neutral'][$task->status] ?? 'neutral';
                @endphp
                <li>
                    <a href="{{ route('admin.tasks.show', $task) }}" class="mbui-card flex overflow-hidden hover:ring-2 hover:ring-indigo-100">
                        <span class="w-1.5 shrink-0 {{ $prio }}" title="{{ \App\Models\AdminTask::PRIORITIES[$task->priority] ?? '' }} priority" aria-hidden="true"></span>
                        <div class="flex min-w-0 flex-1 flex-col gap-2 p-4 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 [overflow-wrap:anywhere] {{ $task->status === 'done' ? 'line-through decoration-gray-400' : '' }}">{{ $task->title }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 [overflow-wrap:anywhere]">
                                    @if ($view === 'mine')
                                        From {{ $task->creator?->name ?? 'someone' }}
                                    @else
                                        To {{ $task->assignee?->name ?? 'nobody yet' }}@if ($task->assignee?->job_title) ({{ $task->assignee->job_title }})@endif
                                    @endif
                                    @if ($task->comments_count) · {{ $task->comments_count }} comment{{ $task->comments_count === 1 ? '' : 's' }}@endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2 text-xs">
                                <x-mbui.badge :appearance="$task->priority === 'urgent' ? 'danger' : ($task->priority === 'high' ? 'warning' : 'neutral')">{{ \App\Models\AdminTask::PRIORITIES[$task->priority] ?? $task->priority }}</x-mbui.badge>
                                <x-mbui.badge :appearance="$statusBadge">{{ \App\Models\AdminTask::STATUSES[$task->status] ?? $task->status }}</x-mbui.badge>
                                @if ($task->due_at)
                                    <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">
                                        {{ $task->isOverdue() ? 'Overdue · ' : 'Due ' }}{{ $task->due_at->format('d M') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $tasks->links() }}</div>
    @endif
</x-layouts.admin>
