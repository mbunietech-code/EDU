<x-layouts.user title="Video help" header="Video help">
    @php($tz = config('app.business_timezone', 'Africa/Dar_es_Salaam'))

    <div class="mx-auto max-w-2xl" x-data="videoHelpPage(@js([
        'active' => $activeStatus,
        'storeUrl' => route('user.video-help.store'),
        'chatUrl' => route('user.chat.index'),
    ]))">
        <div class="mbui-page-header">
            <div>
                <h1 class="mbui-title">Video help</h1>
                <p class="mt-1 text-sm text-gray-500">Talk to our support team face to face. We call every support member at once; the first one free answers and you meet in the Support &amp; Help Desk live room.</p>
            </div>
        </div>

        <div class="mt-6 mbui-card p-6">
            {{-- Ask --}}
            <div x-show="state === 'idle'">
                <form @submit.prevent="start()" class="space-y-4">
                    <div>
                        <label for="reason" class="mbui-label">What do you need help with? <span class="font-normal text-gray-400">(optional)</span></label>
                        <textarea id="reason" x-model="reason" rows="3" maxlength="500" class="mbui-input mt-1 w-full"
                            placeholder="For example: I paid but my plan is not active yet."></textarea>
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
                    <button type="submit" :disabled="busy"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-60 sm:w-auto">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg>
                        <span x-text="busy ? 'Calling...' : 'Call support on video'"></span>
                    </button>
                    <p class="text-xs text-gray-500">Allow your camera and microphone when the room opens. Prefer to type? <a href="{{ route('user.chat.index') }}" class="mbui-anchor">Send us a message</a>.</p>
                </form>
            </div>

            {{-- Ringing --}}
            <div x-show="state === 'ringing'" x-cloak class="flex flex-col items-center py-4 text-center">
                <span class="relative flex h-20 w-20 items-center justify-center">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-40"></span>
                    <span class="relative flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                    </span>
                </span>
                <p class="mt-5 text-lg font-semibold text-gray-900">Calling support...</p>
                <p class="mt-1 text-sm text-gray-500">Please keep this page open. We are ringing every support member.</p>
                <p class="mt-1 text-xs text-gray-400" x-show="secondsLeft > 0">Still ringing for <span x-text="clock(secondsLeft)"></span></p>
                <button type="button" @click="cancel()" :disabled="busy"
                    class="mt-6 inline-flex items-center gap-2 rounded-full bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-60">
                    Cancel call
                </button>
            </div>

            {{-- Answered: opening the room --}}
            <div x-show="state === 'connecting' || state === 'accepted'" x-cloak class="py-6 text-center">
                <p class="text-lg font-semibold text-emerald-700">Answered<span x-show="answeredBy" x-text="' by ' + answeredBy"></span>!</p>
                <p class="mt-1 text-sm text-gray-500">Opening the Support &amp; Help Desk room...</p>
                <a x-show="joinUrl" :href="joinUrl" class="mt-5 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Join the room now</a>
            </div>

            {{-- Missed --}}
            <div x-show="state === 'missed'" x-cloak class="py-6 text-center">
                <p class="text-lg font-semibold text-gray-900">Sorry, nobody could answer right now</p>
                <p class="mt-1 text-sm text-gray-500">Our team has been told about your call. Send us a message and we will get back to you as soon as we can.</p>
                <div class="mt-5 flex flex-col items-center justify-center gap-2 sm:flex-row">
                    <a href="{{ route('user.chat.index') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Message support</a>
                    <button type="button" @click="reset()" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Try again</button>
                </div>
            </div>

            {{-- Cancelled --}}
            <div x-show="state === 'cancelled'" x-cloak class="py-6 text-center">
                <p class="text-lg font-semibold text-gray-900">Call cancelled</p>
                <p class="mt-1 text-sm text-gray-500">You can call again whenever you are ready.</p>
                <button type="button" @click="reset()" class="mt-5 inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Call again</button>
            </div>
        </div>

        @if ($recent->isNotEmpty())
            <div class="mt-6 mbui-card p-6">
                <h2 class="mbui-section-label">Your recent calls</h2>
                <ul class="mt-3 divide-y divide-gray-100">
                    @foreach ($recent as $call)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 truncate text-gray-700">{{ $call->reason ?: 'Video help' }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="text-xs text-gray-400">{{ $call->created_at?->timezone($tz)->format('d M, H:i') }}</span>
                                <x-mbui.badge :appearance="match ($call->status) { 'accepted' => 'success', 'missed' => 'warning', 'ringing' => 'info', default => 'neutral' }">{{ $call->statusLabel() }}</x-mbui.badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <script>
        function videoHelpPage(cfg) {
            return {
                state: cfg.active ? cfg.active.status : 'idle',
                call: cfg.active,
                reason: '',
                busy: false,
                error: '',
                secondsLeft: cfg.active ? cfg.active.seconds_left : 0,
                joinUrl: cfg.active ? cfg.active.join_url : null,
                answeredBy: cfg.active ? (cfg.active.answered_by || '') : '',
                pollTimer: null,
                tickTimer: null,

                init() {
                    if (this.call) this.apply(this.call);
                    this.tickTimer = setInterval(() => { if (this.secondsLeft > 0) this.secondsLeft--; }, 1000);
                },

                csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; },

                clock(s) {
                    const m = Math.floor(s / 60);
                    const r = String(s % 60).padStart(2, '0');
                    return m + ':' + r;
                },

                async post(url, body) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify(body || {}),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
                    return data;
                },

                async start() {
                    if (this.busy) return;
                    this.busy = true;
                    this.error = '';
                    try {
                        this.apply(await this.post(cfg.storeUrl, { reason: this.reason }));
                    } catch (e) {
                        this.error = e.message;
                    }
                    this.busy = false;
                },

                async cancel() {
                    if (!this.call || this.busy) return;
                    this.busy = true;
                    try { this.apply(await this.post(this.call.cancel_url)); } catch (e) {}
                    this.busy = false;
                },

                apply(data) {
                    this.call = Object.assign({}, this.call || {}, data);
                    this.state = data.status;
                    this.secondsLeft = data.seconds_left || 0;
                    this.joinUrl = data.join_url || null;
                    this.answeredBy = data.answered_by || '';
                    clearTimeout(this.pollTimer);
                    if (this.state === 'accepted' && this.joinUrl) {
                        window.location.href = this.joinUrl;
                        return;
                    }
                    if (this.state === 'ringing' || this.state === 'connecting') {
                        this.pollTimer = setTimeout(() => this.check(), this.state === 'connecting' ? 1500 : 3000);
                    }
                },

                async check() {
                    try {
                        const res = await fetch(this.call.status_url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        if (!res.ok) throw new Error();
                        this.apply(await res.json());
                    } catch (e) {
                        this.pollTimer = setTimeout(() => this.check(), 6000);
                    }
                },

                reset() {
                    clearTimeout(this.pollTimer);
                    this.call = null;
                    this.state = 'idle';
                    this.error = '';
                },
            };
        }
    </script>
</x-layouts.user>