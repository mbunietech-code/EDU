{{--
    Action bar of the studio room page. Expects: $room, $canManage, $impact.
--}}
@php
    $canStart = $canManage && in_array($room->status, ['draft', 'scheduled', 'completed'], true);
    $canCancel = $canManage && in_array($room->status, ['draft', 'scheduled'], true);
    $canPublish = $canManage && in_array($room->status, ['draft', 'cancelled'], true);
@endphp

<div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
    @if ($room->isLive())
        <x-mbui.btn-link :href="route('learn.rooms.live', $room)" class="w-full sm:w-auto">
            <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
            Open live room
        </x-mbui.btn-link>
        @if ($canManage)
            @include('studio.rooms.partials.end-confirm', ['room' => $room])
        @endif
    @endif

    @if ($canStart)
        <form method="POST" action="{{ route('studio.rooms.start', $room) }}" class="w-full sm:w-auto">
            @csrf
            <x-mbui.button type="submit" variant="success" class="w-full sm:w-auto">
                <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                {{ $room->status === 'completed' ? 'Start a new session' : 'Start session now' }}
            </x-mbui.button>
        </form>
    @endif

    @if ($canPublish)
        @if ($room->scheduled_at)
            <form method="POST" action="{{ route('studio.rooms.publish', $room) }}" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="notify" value="1">
                <x-mbui.button type="submit" class="w-full sm:w-auto" title="Schedule and notify eligible learners">
                    {{ $room->isCancelled() ? 'Schedule again' : 'Publish & schedule' }}
                </x-mbui.button>
            </form>
        @else
            <span class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-400 sm:w-auto"
                title="Set a date and start time first (Edit)">Publish & schedule</span>
        @endif
    @endif

    @if ($canManage)
        <x-mbui.btn-link :href="route('studio.rooms.edit', $room)" variant="secondary" class="w-full sm:w-auto">
            <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
            Edit
        </x-mbui.btn-link>
    @endif

    @if ($canCancel)
        <div x-data="{ open: false, reason: '' }" class="w-full sm:w-auto" @keydown.escape.window="open = false">
            <x-mbui.button variant="secondary" class="w-full text-red-700 sm:w-auto" @click="open = true; $nextTick(() => $refs.cancelReason?.focus())">Cancel room</x-mbui.button>
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="cancel-room-title">
                    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/50" @click="open = false" aria-hidden="true"></div>
                    <form x-show="open" x-transition method="POST" action="{{ route('studio.rooms.cancel', $room) }}"
                        class="relative w-full max-w-md rounded-xl bg-white p-6 text-left shadow-xl">
                        @csrf
                        <h2 id="cancel-room-title" class="text-base font-semibold text-gray-900">Cancel “{{ $room->title }}”?</h2>
                        <p class="mt-2 text-sm text-gray-600">
                            @if ($room->isScheduled())
                                Everyone who could join is notified that the session is cancelled. You can schedule it again later.
                            @else
                                This draft was never announced, so nobody is notified.
                            @endif
                        </p>
                        <div class="mt-4">
                            <label for="cancel-reason" class="mbui-label">Reason <span class="text-gray-400">(optional, shown to learners)</span></label>
                            <textarea id="cancel-reason" x-ref="cancelReason" x-model="reason" name="reason" rows="3" maxlength="500"
                                class="mbui-input mt-1 w-full" placeholder="e.g. The instructor is unwell — we will reschedule next week."></textarea>
                            <p class="mt-1 text-right text-xs text-gray-400"><span x-text="reason.length"></span>/500</p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <x-mbui.button variant="secondary" @click="open = false">Keep room</x-mbui.button>
                            <x-mbui.button type="submit" variant="danger">Cancel room</x-mbui.button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    @endif

    @if ($canManage && ! $room->isLive())
        <x-learning.confirm-delete :action="route('studio.rooms.destroy', $room)"
            :title="'Delete “'.$room->title.'”?'" :impact="$impact" button-label="Delete room">
            <x-slot:trigger>
                <x-mbui.button variant="ghost" class="w-full text-red-600 hover:bg-red-50 sm:w-auto">
                    <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    Delete
                </x-mbui.button>
            </x-slot:trigger>
        </x-learning.confirm-delete>
    @elseif ($canManage)
        <span class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-lg px-3 py-2 text-sm font-medium text-gray-300 sm:w-auto"
            title="End the live session before deleting the room">Delete</span>
    @endif
</div>
