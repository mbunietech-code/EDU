<x-layouts.app title="New live room" header="Teaching Studio">

    {{-- The admin layout has no [x-cloak] rule; keep hidden Alpine parts hidden until Alpine starts. --}}
    <style>[x-cloak]{display:none !important;}</style>

    <div class="mbui-page-header">
        <div>
            <nav class="mb-1 text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="{{ route('studio.rooms.index') }}" class="mbui-anchor">Live rooms</a>
                <span aria-hidden="true">/</span>
                <span>New</span>
            </nav>
            <h1 class="mbui-title">Create a live room</h1>
            <p class="mt-1 text-sm text-gray-500">Plan a live class: pick a time, choose who can join, then save it as a draft or schedule it.</p>
        </div>
        <div class="flex gap-2">
            <x-mbui.btn-link :href="route('studio.rooms.index')" variant="secondary">Cancel</x-mbui.btn-link>
        </div>
    </div>

    <form method="POST" action="{{ route('studio.rooms.store') }}" x-data="learnRoomForm(@js($formConfig))" class="mt-6">
        @csrf

        @include('studio.rooms.partials._form')

        <x-mbui.card class="mt-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="notify" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        @checked(session()->hasOldInput() ? old('notify') : true)>
                    <span class="text-sm">
                        <span class="block font-medium text-gray-900">Notify eligible learners</span>
                        <span class="block text-xs text-gray-500">When you schedule, everyone who can join gets a notification (and a reminder 15 minutes before).</span>
                    </span>
                </label>

                {{-- Draft first in the DOM so pressing Enter in a field saves a draft. --}}
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <x-mbui.button type="submit" name="action" value="draft" variant="secondary" class="w-full sm:w-auto">Save as draft</x-mbui.button>
                    <x-mbui.button type="submit" name="action" value="schedule" data-schedule class="w-full sm:w-auto">
                        <svg class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                        Schedule
                    </x-mbui.button>
                </div>
            </div>
            <p class="mt-3 text-xs text-gray-500">Drafts are visible only to you and room staff. You can start a draft room at any time for a quick session.</p>
        </x-mbui.card>
    </form>
</x-layouts.app>
