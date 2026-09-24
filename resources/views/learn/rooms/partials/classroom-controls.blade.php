{{-- Control bar: our buttons drive the Jitsi IFrame API. Host tools sit in a popover to fit phones. --}}
@php
    $btn = 'relative inline-flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-full text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 sm:h-12 sm:w-12';
@endphp
<nav class="shrink-0 border-t border-gray-800 bg-gray-900 px-2 py-2 sm:py-3" aria-label="Call controls"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
    <div class="mx-auto flex max-w-3xl items-center justify-center gap-1.5 sm:gap-3">
        {{-- Microphone --}}
        <button type="button" @click="toggleMic()" class="{{ $btn }}"
            :class="!inCall || !canUseMedia ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.audioMuted ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 hover:bg-gray-600')"
            :aria-disabled="(!inCall || !canUseMedia).toString()"
            :aria-pressed="(inCall && !media.audioMuted).toString()"
            :aria-label="!canUseMedia ? 'Microphone disabled by the host' : (media.audioMuted ? 'Turn microphone on' : 'Turn microphone off')"
            :title="!canUseMedia ? mediaLockedText : (media.audioMuted ? 'Turn microphone on' : 'Turn microphone off')">
            <span x-show="media.audioMuted || !canUseMedia">@include('learn.rooms.partials.icon', ['name' => 'mic-off'])</span>
            <span x-show="!media.audioMuted && canUseMedia" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'mic'])</span>
        </button>

        {{-- Camera --}}
        <button type="button" @click="toggleCamera()" class="{{ $btn }}"
            :class="!inCall || !canUseMedia ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.videoMuted ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 hover:bg-gray-600')"
            :aria-disabled="(!inCall || !canUseMedia).toString()"
            :aria-pressed="(inCall && !media.videoMuted).toString()"
            :aria-label="!canUseMedia ? 'Camera disabled by the host' : (media.videoMuted ? 'Turn camera on' : 'Turn camera off')"
            :title="!canUseMedia ? mediaLockedText : (media.videoMuted ? 'Turn camera on' : 'Turn camera off')">
            <span x-show="media.videoMuted || !canUseMedia">@include('learn.rooms.partials.icon', ['name' => 'camera-off'])</span>
            <span x-show="!media.videoMuted && canUseMedia" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'camera'])</span>
        </button>

        {{-- Share screen --}}
        <button type="button" @click="toggleShare()" class="{{ $btn }} hidden sm:inline-flex"
            :class="!inCall || !canUseMedia ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.sharing ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-gray-700 hover:bg-gray-600')"
            :aria-disabled="(!inCall || !canUseMedia).toString()"
            :aria-pressed="media.sharing.toString()"
            :aria-label="!canUseMedia ? 'Screen sharing disabled by the host' : (media.sharing ? 'Stop sharing your screen' : 'Share your screen')"
            :title="!canUseMedia ? mediaLockedText : (media.sharing ? 'Stop sharing your screen' : 'Share your screen')">
            @include('learn.rooms.partials.icon', ['name' => 'screen'])
        </button>

        {{-- Chat --}}
        <button type="button" @click="togglePanel('chat')" class="{{ $btn }}"
            :class="panelVisible && tab === 'chat' ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-gray-700 hover:bg-gray-600'"
            :aria-pressed="(panelVisible && tab === 'chat').toString()"
            :aria-label="unread.chat > 0 ? 'Chat, ' + unread.chat + ' unread' : 'Chat'" title="Chat">
            @include('learn.rooms.partials.icon', ['name' => 'chat'])
            <span x-show="unread.chat + unread.qa > 0" x-cloak aria-hidden="true"
                class="absolute -right-0.5 -top-0.5 min-w-[1.1rem] rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-[1.1rem] text-white"
                x-text="unread.chat + unread.qa > 99 ? '99+' : unread.chat + unread.qa"></span>
        </button>

        {{-- People --}}
        <button type="button" @click="togglePanel('people')" class="{{ $btn }}"
            :class="panelVisible && tab === 'people' ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-gray-700 hover:bg-gray-600'"
            :aria-pressed="(panelVisible && tab === 'people').toString()"
            :aria-label="'People, ' + counts.participants + ' in the call'" title="People">
            @include('learn.rooms.partials.icon', ['name' => 'users'])
        </button>

        {{-- Host tools --}}
        <template x-if="isManager">
            <div class="relative" x-data="{ open: false }" @keydown.escape.stop="open = false; $refs.hostToggle.focus()" @click.outside="open = false">
                <button type="button" x-ref="hostToggle" @click="open = !open" class="{{ $btn }}"
                    :class="media.recording ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 hover:bg-gray-600'"
                    :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Host tools" title="Host tools">
                    @include('learn.rooms.partials.icon', ['name' => 'dots'])
                    <span x-show="media.recording" class="absolute -right-0.5 -top-0.5 h-3 w-3 animate-pulse rounded-full bg-red-500 ring-2 ring-gray-900" aria-hidden="true"></span>
                </button>
                <div x-show="open" x-transition.origin.bottom x-cloak role="menu" aria-label="Host tools"
                    class="absolute bottom-full right-1/2 z-30 mb-2 w-60 translate-x-1/2 overflow-hidden rounded-lg bg-gray-800 py-1 text-sm shadow-xl ring-1 ring-gray-700">
                    <template x-if="provider.supportsRecording">
                        <button type="button" role="menuitem" @click="toggleRecording(); open = false" :disabled="!inCall || recordingBusy"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500">
                            @include('learn.rooms.partials.icon', ['name' => 'record', 'class' => 'h-5 w-5 text-red-400'])
                            <span x-text="media.recording ? 'Stop recording' : 'Start recording'"></span>
                        </button>
                    </template>
                    <button type="button" role="menuitem" @click="muteEveryone('audio'); open = false" :disabled="!inCall"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500">
                        @include('learn.rooms.partials.icon', ['name' => 'speaker-off'])
                        Mute everyone
                    </button>
                    <button type="button" role="menuitem" @click="muteEveryone('video'); open = false" :disabled="!inCall"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500">
                        @include('learn.rooms.partials.icon', ['name' => 'camera-off'])
                        Turn all cameras off
                    </button>
                    <button type="button" role="menuitem" @click="openAdvanced(); open = false" :disabled="!inCall"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500">
                        @include('learn.rooms.partials.icon', ['name' => 'adjustments'])
                        Advanced participant controls
                    </button>
                    <button type="button" role="menuitem" @click="toggleShare(); open = false" :disabled="!inCall"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500 sm:hidden">
                        @include('learn.rooms.partials.icon', ['name' => 'screen'])
                        <span x-text="media.sharing ? 'Stop sharing screen' : 'Share screen'"></span>
                    </button>
                    <template x-if="room.status === 'live'">
                        <button type="button" role="menuitem" @click="askEnd(); open = false"
                            class="flex w-full items-center gap-2 border-t border-gray-700 px-3 py-2 text-left font-semibold text-red-300 hover:bg-gray-700">
                            @include('learn.rooms.partials.icon', ['name' => 'stop'])
                            End session for everyone
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Leave --}}
        <button type="button" @click="leaveCall()" x-show="inCall || (state === 'joining' && hasApi)" x-cloak
            class="inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-full bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 sm:h-12"
            aria-label="Leave the call" title="Leave the call">
            @include('learn.rooms.partials.icon', ['name' => 'leave'])
            <span class="hidden sm:inline">Leave</span>
        </button>
    </div>
</nav>
