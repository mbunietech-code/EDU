@php
    $editing = $task->exists;
@endphp
<x-layouts.admin :title="$editing ? 'Edit task' : 'New task'" header="Company">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ $editing ? 'Edit task' : 'New task' }}</h1>
            <p class="mt-1 text-sm text-gray-500">Say what needs doing, who does it and by when. They get a notification and an email.</p>
        </div>
        <a href="{{ $editing ? route('admin.tasks.show', $task) : route('admin.tasks.index') }}" class="mbui-anchor text-sm">Back</a>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.tasks.update', $task) : route('admin.tasks.store') }}" class="mt-6 max-w-3xl">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="mbui-card space-y-4 p-5 sm:p-6">
            <div>
                <label for="title" class="mbui-label">Task</label>
                <input id="title" name="title" type="text" required maxlength="200" class="mbui-input mt-1" value="{{ old('title', $task->title) }}" placeholder="e.g. Renew the SSL certificate">
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <label for="assigned_to" class="mbui-label">Give it to</label>
                <select id="assigned_to" name="assigned_to" class="mbui-input mt-1">
                    <option value="">— Decide later —</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected((int) old('assigned_to', $task->assigned_to) === $person->id)>
                            {{ $person->job_title ? $person->job_title.' — ' : '' }}{{ $person->name }}{{ $person->id === auth()->id() ? ' (me)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Job titles (CEO, MD, Developer…) are set in Team.</p>
                <x-input-error :messages="$errors->get('assigned_to')" class="mt-1" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="priority" class="mbui-label">Priority</label>
                    <select id="priority" name="priority" class="mbui-input mt-1">
                        @foreach (\App\Models\AdminTask::PRIORITIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('priority', $task->priority ?? 'normal') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="due_at" class="mbui-label">Due date</label>
                    <input id="due_at" name="due_at" type="date" class="mbui-input mt-1" value="{{ old('due_at', $task->due_at?->toDateString()) }}">
                    <x-input-error :messages="$errors->get('due_at')" class="mt-1" />
                </div>
            </div>

            <div>
                <label for="description" class="mbui-label">Details</label>
                <textarea id="description" name="description" rows="6" maxlength="10000" class="mbui-input mt-1" placeholder="What exactly, links, what 'done' looks like…">{{ old('description', $task->description) }}</textarea>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-3">
            <a href="{{ $editing ? route('admin.tasks.show', $task) : route('admin.tasks.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</a>
            <x-mbui.button type="submit">{{ $editing ? 'Save changes' : 'Create task' }}</x-mbui.button>
        </div>
    </form>
</x-layouts.admin>
