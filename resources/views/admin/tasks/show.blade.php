<x-layouts.admin :title="$task->title" header="Company">

    <div class="mbui-page-header">
        <div class="min-w-0">
            <a href="{{ route('admin.tasks.index') }}" class="mbui-anchor text-sm">&larr; Tasks</a>
            <h1 class="mbui-title mt-1 [overflow-wrap:anywhere]">{{ $task->title }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <x-mbui.badge :appearance="['todo' => 'neutral', 'in_progress' => 'info', 'done' => 'success', 'cancelled' => 'neutral'][$task->status] ?? 'neutral'">{{ \App\Models\AdminTask::STATUSES[$task->status] ?? $task->status }}</x-mbui.badge>
                <x-mbui.badge :appearance="$task->priority === 'urgent' ? 'danger' : ($task->priority === 'high' ? 'warning' : 'neutral')">{{ \App\Models\AdminTask::PRIORITIES[$task->priority] ?? $task->priority }} priority</x-mbui.badge>
                @if ($task->due_at)
                    <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">{{ $task->isOverdue() ? 'Overdue — was due' : 'Due' }} {{ $task->due_at->format('D, d M Y') }}</span>
                @endif
            </div>
        </div>
        @if ($canEdit)
            <div class="flex shrink-0 gap-2">
                <x-mbui.btn-link :href="route('admin.tasks.edit', $task)" variant="secondary">Edit</x-mbui.btn-link>
                <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')">
                    @csrf
                    @method('DELETE')
                    <x-mbui.button type="submit" variant="secondary" class="text-red-600">Delete</x-mbui.button>
                </form>
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="mbui-card p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900">Details</h2>
                @if ($task->description)
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 [overflow-wrap:anywhere]">{{ $task->description }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-400">No details.</p>
                @endif
            </div>

            {{-- Comments --}}
            <div id="comments" class="mbui-card p-5 sm:p-6">
                <h2 class="text-sm font-semibold text-gray-900">Updates &amp; comments</h2>
                <ul class="mt-3 space-y-3" role="list">
                    @forelse ($task->comments as $comment)
                        <li class="rounded-lg bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">
                                <span class="font-semibold text-gray-800">{{ $comment->user?->name ?? 'Former admin' }}</span>
                                @if ($comment->user?->job_title) · {{ $comment->user->job_title }}@endif
                                · {{ $comment->created_at->diffForHumans() }}
                            </p>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-800 [overflow-wrap:anywhere]">{{ $comment->body }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">No comments yet.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('admin.tasks.comment', $task) }}" class="mt-4">
                    @csrf
                    <label for="body" class="sr-only">Comment</label>
                    <textarea id="body" name="body" rows="3" required maxlength="5000" class="mbui-input" placeholder="Write an update or a question…">{{ old('body') }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    <div class="mt-2 flex justify-end">
                        <x-mbui.button type="submit">Post</x-mbui.button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            <div class="mbui-card p-5">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Given to</dt>
                        <dd class="font-medium text-gray-900">{{ $task->assignee?->name ?? 'Nobody yet' }}</dd>
                        @if ($task->assignee?->job_title)<dd class="text-xs text-gray-500">{{ $task->assignee->job_title }}</dd>@endif
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Given by</dt>
                        <dd class="text-gray-900">{{ $task->creator?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Created</dt>
                        <dd class="text-gray-900">{{ $task->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    @if ($task->completed_at)
                        <div>
                            <dt class="text-xs text-gray-500">Done</dt>
                            <dd class="text-gray-900">{{ $task->completed_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($canMove)
                <div class="mbui-card p-5">
                    <h2 class="text-sm font-semibold text-gray-900">Move it along</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @foreach (\App\Models\AdminTask::STATUSES as $key => $label)
                            <form method="POST" action="{{ route('admin.tasks.status', $task) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit" @disabled($task->status === $key)
                                    class="w-full rounded-lg px-3 py-2 text-sm font-medium ring-1 disabled:cursor-default {{ $task->status === $key ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50' }}">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
