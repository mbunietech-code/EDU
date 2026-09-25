{{-- Live classroom on our self-hosted WebRTC stack. All behaviour lives in resources/js/learning/classroom.js. --}}
<x-layouts.classroom :title="$room->title">
    <div class="flex min-h-0 flex-1 flex-col" x-data="learnClassroom(@js($config))" x-cloak
        @keydown.escape.window="confirm.open ? closeConfirm() : closePanel()">

        @include('learn.rooms.partials.classroom-topbar')

        {{-- Connection banner (video reconnects + feed polling backoff) --}}
        <div x-show="showConnectionBanner" x-transition.opacity role="status" aria-live="polite"
            class="flex items-center justify-center gap-2 bg-amber-500 px-4 py-1.5 text-center text-sm font-medium text-gray-950">
            @include('learn.rooms.partials.icon', ['name' => 'wifi', 'class' => 'h-4 w-4 shrink-0'])
            <span x-text="connectionBannerText"></span>
        </div>

        <div class="relative flex min-h-0 flex-1">
            @include('learn.rooms.partials.classroom-stage')
            @include('learn.rooms.partials.classroom-panel')
        </div>

        @include('learn.rooms.partials.classroom-controls')
        @include('learn.rooms.partials.classroom-confirm')
    </div>

    <noscript>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950 p-6 text-center text-gray-100">
            <p>The live classroom needs JavaScript. Please enable it and reload the page.</p>
        </div>
    </noscript>
</x-layouts.classroom>
