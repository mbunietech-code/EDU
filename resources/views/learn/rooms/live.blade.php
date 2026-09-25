{{-- Live classroom on our self-hosted WebRTC stack. All behaviour lives in resources/js/learning/classroom.js. --}}
<x-layouts.classroom :title="$room->title">
    <div class="relative flex min-h-0 flex-1 flex-col" x-data="learnClassroom(@js($config))" x-cloak
        @mousemove="onPointerMove($event)"
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

            {{-- Desktop, panel tucked away: a tab on the right edge (hover or click to open) --}}
            <button type="button" x-show="autoHide && !chrome.panel" x-cloak x-transition.opacity
                @mouseenter="showPanel()" @click="openTab(tab)"
                class="absolute right-0 top-1/2 z-30 flex -translate-y-1/2 flex-col items-center gap-1 rounded-l-lg bg-gray-800/90 px-1.5 py-3 text-gray-200 shadow-lg ring-1 ring-gray-700 hover:bg-gray-700"
                :aria-label="'Open chat and people' + (unread.chat + unread.qa > 0 ? ', ' + (unread.chat + unread.qa) + ' unread' : '')">
                @include('learn.rooms.partials.icon', ['name' => 'chat', 'class' => 'h-5 w-5'])
                <span x-show="unread.chat + unread.qa > 0" class="min-w-[1.1rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-semibold leading-[1.1rem] text-white"
                    x-text="unread.chat + unread.qa > 99 ? '99+' : unread.chat + unread.qa"></span>
            </button>
        </div>

        @include('learn.rooms.partials.classroom-controls')

        {{-- Desktop, control bar tucked away: a small handle hints where it is --}}
        <div x-show="autoHide && !chrome.bar" x-cloak x-transition.opacity
            class="pointer-events-none absolute inset-x-0 bottom-1.5 z-20 flex justify-center" aria-hidden="true">
            <span class="h-1 w-16 rounded-full bg-white/40"></span>
        </div>

        @include('learn.rooms.partials.classroom-confirm')
    </div>

    <noscript>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950 p-6 text-center text-gray-100">
            <p>The live classroom needs JavaScript. Please enable it and reload the page.</p>
        </div>
    </noscript>
</x-layouts.classroom>
