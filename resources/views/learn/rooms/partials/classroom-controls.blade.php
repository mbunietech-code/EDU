{{-- Control bar: our own buttons driving our own WebRTC connection. Host tools sit in a popover to fit phones. --}}
@php
    $btn = 'relative inline-flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-full text-white transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 sm:h-12 sm:w-12';
    $menuItem = 'flex w-full items-center gap-2 px-3 py-2 text-left text-gray-100 hover:bg-gray-700 disabled:cursor-not-allowed disabled:text-gray-500';
@endphp
{{-- Desktop in a call: floats over the video and slides away; the mouse at the bottom edge brings it back. --}}
<nav x-ref="controlBar" class="shrink-0 border-t border-gray-800 px-2 py-2 transition-transform duration-300 ease-out sm:py-3" aria-label="Call controls"
    :class="autoHide ? ('absolute inset-x-0 bottom-0 z-30 bg-gray-900/90 backdrop-blur ' + (chrome.bar ? 'translate-y-0' : 'translate-y-full')) : 'bg-gray-900'"
    @mouseenter="barHover = true; showBar()" @mouseleave="barHover = false; scheduleBarHide()"
    @focusin="showBar()" @focusout="scheduleBarHide()"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
    <div class="mx-auto flex max-w-4xl items-center justify-center gap-1.5 sm:gap-3">
        {{-- Microphone --}}
        <button type="button" @click="toggleMic()" class="{{ $btn }}" :disabled="mediaBusy.mic"
            :class="!inCall || !canUseMic ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.mic ? 'bg-gray-700 hover:bg-gray-600' : 'bg-red-600 hover:bg-red-500')"
            :aria-disabled="(!inCall || !canUseMic).toString()"
            :aria-pressed="(inCall && media.mic).toString()"
            :aria-label="!canUseMic ? 'Microphone not allowed by the host' : (media.mic ? 'Turn microphone off' : 'Turn microphone on')"
            :title="!canUseMic ? mediaLockedText : (media.mic ? 'Turn microphone off' : 'Turn microphone on')">
            <span x-show="!media.mic">@include('learn.rooms.partials.icon', ['name' => 'mic-off'])</span>
            <span x-show="media.mic" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'mic'])</span>
        </button>

        {{-- Camera --}}
        <button type="button" @click="toggleCamera()" class="{{ $btn }}" :disabled="mediaBusy.cam"
            :class="!inCall || !canUseCam ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.cam ? 'bg-gray-700 hover:bg-gray-600' : 'bg-red-600 hover:bg-red-500')"
            :aria-disabled="(!inCall || !canUseCam).toString()"
            :aria-pressed="(inCall && media.cam).toString()"
            :aria-label="!canUseCam ? 'Camera not allowed by the host' : (media.cam ? 'Turn camera off' : 'Turn camera on')"
            :title="!canUseCam ? mediaLockedText : (media.cam ? 'Turn camera off' : 'Turn camera on')">
            <span x-show="!media.cam">@include('learn.rooms.partials.icon', ['name' => 'camera-off'])</span>
            <span x-show="media.cam" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'camera'])</span>
        </button>

        {{-- Share screen (hidden where the browser cannot share) --}}
        <button type="button" @click="toggleShare()" class="{{ $btn }}" x-show="screenShareSupported" :disabled="mediaBusy.screen"
            :class="!inCall || !canShare ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : (media.screen ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-gray-700 hover:bg-gray-600')"
            :aria-disabled="(!inCall || !canShare).toString()"
            :aria-pressed="media.screen.toString()"
            :aria-label="!canShare ? 'Screen sharing not allowed by the host' : (media.screen ? 'Stop sharing your screen' : 'Share your screen')"
            :title="!canShare ? 'The host has not allowed participants to share their screen' : (media.screen ? 'Stop sharing your screen' : 'Share your screen, a window or a tab')">
            @include('learn.rooms.partials.icon', ['name' => 'screen'])
        </button>

        {{-- Layout --}}
        <button type="button" @click="setLayout(layout === 'grid' ? 'speaker' : 'grid')" class="{{ $btn }} hidden sm:inline-flex"
            :class="!inCall ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : 'bg-gray-700 hover:bg-gray-600'"
            :aria-disabled="(!inCall).toString()"
            :aria-label="layout === 'grid' ? 'Switch to speaker view' : 'Switch to grid view'"
            :title="layout === 'grid' ? 'Speaker view' : 'Grid view'">
            <span x-show="layout !== 'grid'">@include('learn.rooms.partials.icon', ['name' => 'grid'])</span>
            <span x-show="layout === 'grid'" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'speaker-view'])</span>
        </button>

        {{-- Record (host): server recording when available, otherwise recorded by this browser --}}
        <template x-if="isManager">
            <button type="button" @click="toggleRecord()" class="{{ $btn }}"
                :disabled="rec.starting || rec.uploading || recordingBusy"
                :class="(rec.active || (provider.supportsRecording && room.is_recording)) ? 'bg-red-600 hover:bg-red-500'
                    : (!inCall || rec.uploading ? 'bg-gray-800 text-gray-500 cursor-not-allowed' : 'bg-gray-700 hover:bg-gray-600')"
                :aria-pressed="(rec.active || (provider.supportsRecording && room.is_recording)).toString()"
                :aria-label="recordLabel" :title="recordLabel">
                @include('learn.rooms.partials.icon', ['name' => 'record'])
                <span x-show="rec.active || (provider.supportsRecording && room.is_recording)" x-cloak
                    class="absolute -right-0.5 -top-0.5 h-3 w-3 animate-pulse rounded-full bg-white ring-2 ring-red-600" aria-hidden="true"></span>
                <span x-show="rec.uploading" x-cloak class="absolute -bottom-1 rounded bg-gray-900 px-1 text-[9px] font-semibold tabular-nums text-white" x-text="rec.progress + '%'"></span>
            </button>
        </template>

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
            :aria-label="'People, ' + counts.participants + ' in the class'" title="People">
            @include('learn.rooms.partials.icon', ['name' => 'users'])
        </button>

        {{-- More: layout (phones), devices, full screen --}}
        <div class="relative" x-data="{ open: false }" @keydown.escape.stop="open = false; $refs.moreToggle.focus()" @click.outside="open = false">
            <button type="button" x-ref="moreToggle" @click="open = !open" class="{{ $btn }} bg-gray-700 hover:bg-gray-600"
                :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="More options" title="More options">
                @include('learn.rooms.partials.icon', ['name' => 'cog'])
            </button>
            <div x-show="open" x-transition.origin.bottom x-cloak role="menu" aria-label="More options"
                class="absolute bottom-full right-1/2 z-30 mb-2 w-60 translate-x-1/2 overflow-hidden rounded-lg bg-gray-800 py-1 text-sm shadow-xl ring-1 ring-gray-700">
                <button type="button" role="menuitem" @click="openDevices(); open = false" class="{{ $menuItem }}">
                    @include('learn.rooms.partials.icon', ['name' => 'adjustments'])
                    Camera, microphone &amp; speaker
                </button>
                <button type="button" role="menuitem" @click="setLayout(layout === 'grid' ? 'speaker' : 'grid'); open = false" :disabled="!inCall" class="{{ $menuItem }} sm:hidden">
                    @include('learn.rooms.partials.icon', ['name' => 'grid'])
                    <span x-text="layout === 'grid' ? 'Speaker view' : 'Grid view'"></span>
                </button>
                <button type="button" role="menuitem" x-show="fullscreenSupported" @click="toggleFullscreen(); open = false" class="{{ $menuItem }}">
                    @include('learn.rooms.partials.icon', ['name' => 'expand'])
                    <span x-text="isFullscreen ? 'Exit full screen' : 'Full screen'"></span>
                </button>
                <button type="button" role="menuitem" x-show="pinnedId" @click="pinnedId = null; open = false; $nextTick(() => attachVideos())" class="{{ $menuItem }}">
                    @include('learn.rooms.partials.icon', ['name' => 'pin'])
                    Unpin
                </button>
            </div>
        </div>

        {{-- Host tools --}}
        <template x-if="isManager">
            <div class="relative" x-data="{ open: false }" @keydown.escape.stop="open = false; $refs.hostToggle.focus()" @click.outside="open = false">
                <button type="button" x-ref="hostToggle" @click="open = !open" class="{{ $btn }}"
                    :class="room.is_recording ? 'bg-red-600 hover:bg-red-500' : 'bg-gray-700 hover:bg-gray-600'"
                    :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Host tools" title="Host tools">
                    @include('learn.rooms.partials.icon', ['name' => 'dots'])
                    <span x-show="room.is_recording" class="absolute -right-0.5 -top-0.5 h-3 w-3 animate-pulse rounded-full bg-red-500 ring-2 ring-gray-900" aria-hidden="true"></span>
                </button>
                <div x-show="open" x-transition.origin.bottom x-cloak role="menu" aria-label="Host tools"
                    class="absolute bottom-full right-0 z-30 mb-2 w-72 overflow-hidden rounded-lg bg-gray-800 py-1 text-sm shadow-xl ring-1 ring-gray-700 sm:right-1/2 sm:translate-x-1/2">
                    <button type="button" role="menuitem" @click="toggleRecord(); open = false" :disabled="!inCall || rec.starting || rec.uploading || recordingBusy" class="{{ $menuItem }}">
                        @include('learn.rooms.partials.icon', ['name' => 'record', 'class' => 'h-5 w-5 text-red-400'])
                        <span x-text="recordLabel"></span>
                    </button>
                    <button type="button" role="menuitem" @click="muteEveryone('audio'); open = false" :disabled="room.status !== 'live' || busy.mod" class="{{ $menuItem }}">
                        @include('learn.rooms.partials.icon', ['name' => 'speaker-off'])
                        Mute everyone
                    </button>
                    <button type="button" role="menuitem" @click="muteEveryone('video'); open = false" :disabled="room.status !== 'live' || busy.mod" class="{{ $menuItem }}">
                        @include('learn.rooms.partials.icon', ['name' => 'camera-off'])
                        Turn all cameras off
                    </button>
                    <div class="my-1 border-t border-gray-700"></div>
                    <button type="button" role="menuitemcheckbox" :aria-checked="room.allow_participant_media.toString()"
                        @click="setRoomMedia('allow_participant_media', !room.allow_participant_media)" :disabled="busy.mod" class="{{ $menuItem }}">
                        @include('learn.rooms.partials.icon', ['name' => 'mic'])
                        <span class="flex-1">Participants may use mic &amp; camera</span>
                        <span class="text-xs font-semibold" :class="room.allow_participant_media ? 'text-emerald-300' : 'text-gray-400'" x-text="room.allow_participant_media ? 'On' : 'Off'"></span>
                    </button>
                    <button type="button" role="menuitemcheckbox" :aria-checked="room.allow_screen_share.toString()"
                        @click="setRoomMedia('allow_screen_share', !room.allow_screen_share)" :disabled="busy.mod" class="{{ $menuItem }}">
                        @include('learn.rooms.partials.icon', ['name' => 'screen'])
                        <span class="flex-1">Participants may share screen</span>
                        <span class="text-xs font-semibold" :class="room.allow_screen_share ? 'text-emerald-300' : 'text-gray-400'" x-text="room.allow_screen_share ? 'On' : 'Off'"></span>
                    </button>
                    <button type="button" role="menuitemcheckbox" :aria-checked="room.is_locked.toString()"
                        @click="toggleLock(); open = false" :disabled="busy.mod" class="{{ $menuItem }}">
                        <span x-show="!room.is_locked">@include('learn.rooms.partials.icon', ['name' => 'lock'])</span>
                        <span x-show="room.is_locked" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'unlock'])</span>
                        <span x-text="room.is_locked ? 'Unlock the room' : 'Lock the room (no new people)'"></span>
                    </button>
                    <template x-if="room.status === 'live'">
                        <button type="button" role="menuitem" @click="askEnd(); open = false"
                            class="flex w-full items-center gap-2 border-t border-gray-700 px-3 py-2 text-left font-semibold text-red-300 hover:bg-gray-700">
                            @include('learn.rooms.partials.icon', ['name' => 'stop'])
                            End class for everyone
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Leave --}}
        <button type="button" @click="leaveCall()" x-show="inCall || state === 'reconnecting'" x-cloak
            class="inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-full bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 sm:h-12"
            aria-label="Leave the class" title="Leave the class">
            @include('learn.rooms.partials.icon', ['name' => 'leave'])
            <span class="hidden sm:inline">Leave</span>
        </button>
    </div>
</nav>

{{-- Device settings --}}
<div x-show="devices.open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="devices-title"
    @keydown.escape.window="devices.open = false">
    <div class="fixed inset-0 bg-black/60" @click="devices.open = false" aria-hidden="true"></div>
    <div class="relative w-full max-w-md rounded-xl bg-gray-900 p-5 text-sm text-gray-200 shadow-2xl ring-1 ring-gray-700">
        <h2 id="devices-title" class="text-base font-semibold text-white">Camera, microphone &amp; speaker</h2>
        <p class="mt-1 text-xs text-gray-400" x-show="!inCall">Join the class to switch devices during the call.</p>
        <div class="mt-4 space-y-3">
            <label class="block">
                <span class="text-xs text-gray-400">Camera</span>
                <select class="mt-1 w-full rounded-lg border-gray-700 bg-gray-800 text-sm text-white" x-model="devices.cam" @change="switchDevice('videoinput', devices.cam)" :disabled="!inCall || !devices.cams.length">
                    <template x-if="!devices.cams.length"><option value="">No camera found</option></template>
                    <template x-for="d in devices.cams" :key="d.id"><option :value="d.id" x-text="d.label"></option></template>
                </select>
            </label>
            <label class="block">
                <span class="text-xs text-gray-400">Microphone</span>
                <select class="mt-1 w-full rounded-lg border-gray-700 bg-gray-800 text-sm text-white" x-model="devices.mic" @change="switchDevice('audioinput', devices.mic)" :disabled="!inCall || !devices.mics.length">
                    <template x-if="!devices.mics.length"><option value="">No microphone found</option></template>
                    <template x-for="d in devices.mics" :key="d.id"><option :value="d.id" x-text="d.label"></option></template>
                </select>
            </label>
            <label class="block" x-show="devices.speakers.length">
                <span class="text-xs text-gray-400">Speaker</span>
                <select class="mt-1 w-full rounded-lg border-gray-700 bg-gray-800 text-sm text-white" x-model="devices.speaker" @change="switchDevice('audiooutput', devices.speaker)" :disabled="!inCall">
                    <template x-for="d in devices.speakers" :key="d.id"><option :value="d.id" x-text="d.label"></option></template>
                </select>
            </label>
            <p class="text-xs text-gray-500">Device names appear after you allow the camera or microphone once.</p>
        </div>
        <div class="mt-5 flex justify-end">
            <button type="button" @click="devices.open = false" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">Done</button>
        </div>
    </div>
</div>
