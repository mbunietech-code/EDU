<x-layouts.app :title="'Edit '.$room->title" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>

    @php($canSchedule = in_array($room->status, ['draft', 'cancelled'], true))

    <div class="mbui-page-header">
        <div>
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor">Live rooms</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('studio.rooms.show', $room) }}" class="mbui-anchor">{{ \Illuminate\Support\Str::limit($room->title, 40) }}</a>
                <span aria-hidden="true">/</span>
                <span>Edit</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="mbui-title">Edit room</h1>
                <x-learning.room-status :status="$room->status" />
            </div>
            <p class="mt-1 text-sm text-gray-500">Changes are saved to the room; learners see them straight away.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('studio.rooms.show', $room)" variant="secondary">Back to room</x-mbui.btn-link>
        </div>
    </div>

    <form method="POST" action="{{ route('studio.rooms.update', $room) }}" x-data="learnRoomForm(@js($formConfig))" class="mt-6">
        @csrf
        @method('PUT')

        @include('studio.rooms.partials._form')

        <x-mbui.card class="mt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                @if ($canSchedule)
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="notify" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            @checked(session()->hasOldInput() ? old('notify') : true)>
                        <span class="text-sm">
                            <span class="block font-medium text-gray-900">Notify eligible learners</span>
                            <span class="block text-xs text-gray-500">Sent when you schedule the room.</span>
                        </span>
                    </label>
                @else
                    <p class="text-sm text-gray-500">
                        @if ($room->isScheduled())
                            This room is scheduled. Changing the time resets the 15-minute reminder.
                        @elseif ($room->isLive())
                            Locked fields are kept as they are.
                        @else
                            Saving keeps the room {{ strtolower($room->statusLabel()) }}.
                        @endif
                    </p>
                @endif

                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    @if ($canSchedule)
                        <x-mbui.button type="submit" name="action" value="draft" variant="secondary" class="w-full sm:w-auto">
                            {{ $room->isDraft() ? 'Save draft' : 'Save changes' }}
                        </x-mbui.button>
                        <x-mbui.button type="submit" name="action" value="schedule" data-schedule class="w-full sm:w-auto">
                            {{ $room->isCancelled() ? 'Save & schedule again' : 'Save & schedule' }}
                        </x-mbui.button>
                    @else
                        <x-mbui.button type="submit" name="action" value="save" class="w-full sm:w-auto">Save changes</x-mbui.button>
                    @endif
                </div>
            </div>
        </x-mbui.card>
    </form>
</x-layouts.app>
