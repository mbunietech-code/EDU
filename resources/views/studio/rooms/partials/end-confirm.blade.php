{{--
    "End session" button with a confirmation dialog.
    Expects: $room. Optional: $compact (small text button), $label.
--}}
@php
    $compact = $compact ?? false;
    $label = $label ?? 'End session';
@endphp

<div x-data="{ open: false }" class="inline-flex" @keydown.escape.window="open = false">
    @if ($compact)
        <button type="button" @click="open = true"
            class="rounded-md px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50"
            aria-label="End the live session of “{{ $room->title }}”">End</button>
    @else
        <x-mbui.button variant="danger" @click="open = true" class="w-full sm:w-auto">
            <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z" /></svg>
            {{ $label }}
        </x-mbui.button>
    @endif

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true"
            aria-label="End the live session">
            <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="open = false" aria-hidden="true"></div>
            <form x-show="open" x-transition method="POST" action="{{ route('studio.rooms.end', $room) }}"
                class="relative w-full max-w-md rounded-xl bg-white p-6 text-left shadow-xl">
                @csrf
                <h2 class="text-base font-semibold text-gray-900">End the session for everyone?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    “{{ $room->title }}” will be marked completed and everyone still in the call is disconnected.
                    Attendance is saved. You can start a new session later.
                </p>
                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-mbui.button variant="secondary" @click="open = false">Keep it running</x-mbui.button>
                    <x-mbui.button type="submit" variant="danger">End session</x-mbui.button>
                </div>
            </form>
        </div>
    </template>
</div>
