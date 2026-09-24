{{-- Main stage: the Jitsi iframe container plus one overlay per classroom state. --}}
<main class="relative min-w-0 flex-1 bg-black" aria-label="Live video">
    {{-- Jitsi mounts its iframe here (always in the DOM so x-ref resolves). --}}
    <div x-ref="stage" class="absolute inset-0" x-show="hasApi" :aria-hidden="(!hasApi).toString()"></div>

    {{-- Waiting: not live yet --}}
    <section x-show="state === 'waiting'" class="absolute inset-0 flex items-center justify-center overflow-y-auto p-6" aria-labelledby="stage-waiting">
        <div class="max-w-md text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-sky-500/10 text-sky-300">
                @include('learn.rooms.partials.icon', ['name' => 'clock', 'class' => 'h-7 w-7'])
            </div>
            <h2 id="stage-waiting" class="mt-4 text-lg font-semibold text-white">This class has not started yet</h2>
            <p class="mt-1 text-sm text-gray-300" x-show="room.scheduled_label">
                Scheduled for <span class="font-medium text-white" x-text="room.scheduled_label"></span>
            </p>
            <p class="mt-3 text-sm text-gray-400">This page updates automatically — you will be able to join as soon as the host starts the session.</p>
            <template x-if="isManager">
                <div class="mt-6">
                    <button type="button" @click="startSession()" :disabled="busy.start"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500">
                        @include('learn.rooms.partials.icon', ['name' => 'play', 'class' => 'h-4 w-4'])
                        <span x-text="busy.start ? 'Starting…' : 'Start session'"></span>
                    </button>
                    <p class="mt-2 text-xs text-gray-400">Learners are notified when you start.</p>
                </div>
            </template>
        </div>
    </section>

    {{-- Pre-join --}}
    <section x-show="state === 'prejoin'" class="absolute inset-0 flex items-center justify-center overflow-y-auto p-6" aria-labelledby="stage-prejoin">
        <div class="w-full max-w-md text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-500/10 text-indigo-300">
                @include('learn.rooms.partials.icon', ['name' => 'camera', 'class' => 'h-7 w-7'])
            </div>
            <h2 id="stage-prejoin" class="mt-4 text-lg font-semibold text-white" x-text="hasLeft ? 'You left the call' : 'Ready to join?'">Ready to join?</h2>
            <p class="mt-1 text-sm text-gray-300">
                <span class="tabular-nums" x-text="counts.participants"></span>
                <span x-text="counts.participants === 1 ? 'person is' : 'people are'"></span> in the class.
            </p>

            <ul class="mt-5 space-y-2 rounded-lg bg-gray-900 p-4 text-left text-sm text-gray-300 ring-1 ring-gray-800">
                <template x-if="canUseMedia">
                    <li class="flex gap-2">
                        @include('learn.rooms.partials.icon', ['name' => 'info', 'class' => 'mt-0.5 h-4 w-4 shrink-0 text-sky-300'])
                        <span>Your browser will ask to use your camera and microphone — choose <strong class="text-white">Allow</strong>.
                            <span x-show="!isManager">You join muted; turn them on when you want to speak.</span></span>
                    </li>
                </template>
                <template x-if="!canUseMedia">
                    <li class="flex gap-2">
                        @include('learn.rooms.partials.icon', ['name' => 'mic-off', 'class' => 'mt-0.5 h-4 w-4 shrink-0 text-amber-300'])
                        <span x-text="mediaLockedText"></span>
                    </li>
                </template>
                <li class="flex gap-2">
                    @include('learn.rooms.partials.icon', ['name' => 'chat', 'class' => 'mt-0.5 h-4 w-4 shrink-0 text-sky-300'])
                    <span>Use the Chat and Q&amp;A tabs to talk to the class and ask the host questions.</span>
                </li>
                <li class="flex gap-2">
                    @include('learn.rooms.partials.icon', ['name' => 'wifi', 'class' => 'mt-0.5 h-4 w-4 shrink-0 text-sky-300'])
                    <span>Headphones and a stable connection give the best experience.</span>
                </li>
            </ul>

            <template x-if="provider.demoWarning">
                <div class="mt-4 rounded-lg bg-amber-500/10 p-3 text-left text-xs text-amber-200 ring-1 ring-amber-500/30" role="note">
                    <p class="font-semibold">Demo video server</p>
                    <p class="mt-0.5" x-text="provider.demoWarning"></p>
                </div>
            </template>

            <button type="button" @click="join()" x-ref="joinButton"
                class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 sm:w-auto">
                @include('learn.rooms.partials.icon', ['name' => 'camera', 'class' => 'h-4 w-4'])
                <span x-text="hasLeft ? 'Rejoin the class' : 'Join the class'">Join the class</span>
            </button>
        </div>
    </section>

    {{-- Joining (until the Jitsi iframe exists) --}}
    <section x-show="state === 'joining' && !hasApi" class="absolute inset-0 flex items-center justify-center p-6" role="status" aria-live="polite">
        <div class="text-center">
            <svg class="mx-auto h-10 w-10 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="mt-4 text-sm font-medium text-white">Connecting to the class…</p>
            <p class="mt-1 text-xs text-gray-400">Loading the video service. This can take a few seconds.</p>
        </div>
    </section>

    {{-- Ended --}}
    <section x-show="state === 'ended'" class="absolute inset-0 flex items-center justify-center overflow-y-auto p-6" aria-labelledby="stage-ended">
        <div class="max-w-md text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-300">
                @include('learn.rooms.partials.icon', ['name' => 'check-circle', 'class' => 'h-7 w-7'])
            </div>
            <h2 id="stage-ended" class="mt-4 text-lg font-semibold text-white"
                x-text="room.status === 'cancelled' ? 'This class was cancelled' : 'This session has ended'">This session has ended</h2>
            <p class="mt-1 text-sm text-gray-300" x-show="room.status === 'cancelled' && room.cancel_reason">
                Reason: <span x-text="room.cancel_reason"></span>
            </p>
            <p class="mt-3 text-sm text-gray-400" x-show="room.status !== 'cancelled'">Thanks for taking part. If the host shares a recording, you will find it on the class page.</p>
            <div class="mt-6 flex flex-col items-center justify-center gap-2 sm:flex-row">
                <a href="{{ route('learn.rooms.show', $room) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">Back to class details</a>
                <a href="{{ route('learn.rooms.index') }}" class="text-sm font-medium text-indigo-300 hover:text-indigo-200">All live classes</a>
            </div>
            <template x-if="isManager && room.status === 'completed'">
                <button type="button" @click="startSession()" :disabled="busy.start"
                    class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-60">
                    @include('learn.rooms.partials.icon', ['name' => 'play', 'class' => 'h-4 w-4'])
                    <span x-text="busy.start ? 'Starting…' : 'Start a new session'"></span>
                </button>
            </template>
        </div>
    </section>

    {{-- Removed --}}
    <section x-show="state === 'removed'" class="absolute inset-0 flex items-center justify-center overflow-y-auto p-6" aria-labelledby="stage-removed" role="alert">
        <div class="max-w-md text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 text-red-300">
                @include('learn.rooms.partials.icon', ['name' => 'no-entry', 'class' => 'h-7 w-7'])
            </div>
            <h2 id="stage-removed" class="mt-4 text-lg font-semibold text-white">You were removed from this session</h2>
            <p class="mt-2 text-sm text-gray-300">The host removed you from the live class. You cannot rejoin this session. If you think this was a mistake, contact the host.</p>
            <a href="{{ route('learn.rooms.show', $room) }}"
                class="mt-6 inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-100">Back to class details</a>
        </div>
    </section>

    {{-- Error --}}
    <section x-show="state === 'error'" class="absolute inset-0 flex items-center justify-center overflow-y-auto p-6" aria-labelledby="stage-error" role="alert">
        <div class="max-w-md text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/10 text-amber-300">
                @include('learn.rooms.partials.icon', ['name' => 'warning', 'class' => 'h-7 w-7'])
            </div>
            <h2 id="stage-error" class="mt-4 text-lg font-semibold text-white" x-text="errorTitle || 'Something went wrong'"></h2>
            <p class="mt-2 break-words text-sm text-gray-300" x-text="errorText"></p>
            <div class="mt-6 flex flex-col items-center justify-center gap-2 sm:flex-row">
                <button type="button" @click="retry()" x-show="!feedStopped"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                    @include('learn.rooms.partials.icon', ['name' => 'retry', 'class' => 'h-4 w-4'])
                    Retry
                </button>
                <button type="button" @click="window.location.reload()" x-show="feedStopped"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                    Reload page
                </button>
                <a href="{{ route('learn.rooms.show', $room) }}" class="text-sm font-medium text-indigo-300 hover:text-indigo-200">Back to class details</a>
            </div>
        </div>
    </section>

    {{-- Camera / microphone guidance --}}
    <div x-show="mediaError && (state === 'in_call' || state === 'joining')" x-transition.opacity x-cloak
        class="absolute inset-x-3 top-3 z-10 mx-auto flex max-w-xl items-start gap-3 rounded-lg bg-amber-500 p-3 text-sm text-gray-950 shadow-lg" role="alert">
        @include('learn.rooms.partials.icon', ['name' => 'warning', 'class' => 'mt-0.5 h-5 w-5 shrink-0'])
        <p class="min-w-0 flex-1" x-text="mediaError"></p>
        <button type="button" @click="mediaError = ''" class="shrink-0 rounded p-0.5 hover:bg-amber-400" aria-label="Dismiss device warning">
            @include('learn.rooms.partials.icon', ['name' => 'x', 'class' => 'h-4 w-4'])
        </button>
    </div>

    {{-- Toast --}}
    <div class="pointer-events-none absolute inset-x-3 bottom-3 z-10 flex justify-center" aria-live="polite" role="status">
        <p x-show="notice" x-transition.opacity x-cloak
            class="pointer-events-auto max-w-lg break-words rounded-lg bg-gray-800 px-4 py-2 text-center text-sm text-white shadow-lg ring-1 ring-gray-700"
            x-text="notice"></p>
    </div>
</main>
