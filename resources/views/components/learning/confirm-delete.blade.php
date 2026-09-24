@props([
    'action',
    'title',
    'impact' => [],
    'method' => 'DELETE',
    'buttonLabel' => 'Delete',
])

{{-- Delete with a required reason (min 3 characters) and a list of what the
     delete will take with it (LearningDeletionService::impact()). The default
     slot adds extra fields to the form (e.g. a "delete lessons too" checkbox);
     the optional "trigger" slot replaces the default trash-icon button. --}}
<div x-data="{ open: false, reason: '' }" x-id="['confirm-delete-title', 'confirm-delete-reason']"
    class="inline-flex" @keydown.escape.window="open = false">
    @isset($trigger)
        <span @click="open = true; $nextTick(() => $refs.reason?.focus())">{{ $trigger }}</span>
    @else
        <button type="button" title="{{ $buttonLabel }}"
            @click="open = true; $nextTick(() => $refs.reason?.focus())"
            {{ $attributes->class($attributes->has('class') ? [] : ['rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600']) }}>
            <span class="sr-only">{{ $buttonLabel }}</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
        </button>
    @endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
            role="dialog" aria-modal="true" :aria-labelledby="$id('confirm-delete-title')">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="open = false" aria-hidden="true"></div>

            <form x-show="open" x-transition method="POST" action="{{ $action }}"
                class="relative w-full max-w-md rounded-xl bg-white p-6 text-left shadow-xl"
                @submit="if (reason.trim().length < 3) { $event.preventDefault(); }">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method(strtoupper($method))
                @endif

                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 :id="$id('confirm-delete-title')" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                        @if (count($impact))
                            <p class="mt-2 text-sm text-gray-600">This will also affect:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-gray-700">
                                @foreach ($impact as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                {{ $slot }}

                <div class="mt-4">
                    <label :for="$id('confirm-delete-reason')" class="mbui-label">Reason <span class="text-red-600">*</span></label>
                    <textarea x-ref="reason" x-model="reason" :id="$id('confirm-delete-reason')" name="reason" rows="3"
                        required minlength="3" maxlength="500" class="mbui-input mt-1 w-full"
                        placeholder="Why is this being deleted?"></textarea>
                    <p class="mt-1 text-xs text-gray-500">Kept in the deletion record for audit.</p>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-mbui.button variant="secondary" @click="open = false">Cancel</x-mbui.button>
                    <x-mbui.button type="submit" variant="danger" x-bind:disabled="reason.trim().length < 3"
                        class="disabled:cursor-not-allowed disabled:opacity-50">{{ $buttonLabel }}</x-mbui.button>
                </div>
            </form>
        </div>
    </template>
</div>
