{{--
    Incoming video help call: polls for ringing calls and rings (Web Audio)
    on every admin page until one admin accepts. Accept sends this admin into
    the support live room; the popup disappears for everyone else on their
    next poll. Rendered only for admins who may answer (VideoHelpService::canAnswer).
--}}
<div x-data="videoHelpRinger(@js(route('admin.video-help.ringing')))" x-cloak>
    <template x-if="call">
        <div class="fixed inset-x-3 bottom-3 z-[60] sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-96" role="alertdialog" aria-live="assertive" aria-label="Incoming video help call">
            <div class="overflow-hidden rounded-2xl bg-gray-900 text-white shadow-2xl ring-1 ring-white/10">
                <div class="flex items-center gap-4 p-5">
                    <span class="relative flex h-14 w-14 shrink-0 items-center justify-center">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-50"></span>
                        <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">Video help call</p>
                        <p class="truncate text-lg font-semibold" x-text="call.name"></p>
                        <p class="truncate text-xs text-gray-400" x-text="call.email"></p>
                    </div>
                </div>

                <p x-show="call.reason" class="mx-5 -mt-1 mb-4 max-h-24 overflow-y-auto whitespace-pre-line break-words rounded-lg bg-white/5 px-3 py-2 text-sm text-gray-200" x-text="call.reason"></p>

                <p x-show="message" class="mx-5 mb-3 rounded-lg bg-amber-500/20 px-3 py-2 text-sm text-amber-200" x-text="message"></p>

                <p x-show="muted" class="mx-5 mb-3 text-xs text-gray-400">Click anywhere on the page to hear the ring.</p>

                <div class="flex items-center justify-between gap-3 border-t border-white/10 px-5 py-4">
                    <span class="text-xs text-gray-400">
                        <span x-text="calls.length > 1 ? (calls.length - 1) + ' more waiting' : 'Ringing all support staff'"></span>
                    </span>
                    <div class="flex gap-2">
                        <button type="button" @click="decline()" :disabled="busy"
                            class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-60">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            Decline
                        </button>
                        <button type="button" @click="accept()" :disabled="busy"
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400 disabled:opacity-60">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                            <span x-text="busy ? 'Joining...' : 'Accept'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function videoHelpRinger(pollUrl) {
        return {
            calls: [],
            call: null,
            busy: false,
            message: '',
            muted: false,
            stopped: false,
            audio: null,
            ringTimer: null,
            pollTimer: null,
            failures: 0,
            notified: {},
            baseTitle: document.title,
            titleTimer: null,

            init() {
                // Browsers only allow sound after the person has touched the page.
                const unlock = () => {
                    this.ensureAudio();
                    if (this.audio && this.audio.state === 'suspended') this.audio.resume();
                    this.muted = false;
                };
                window.addEventListener('pointerdown', unlock, { passive: true });
                window.addEventListener('keydown', unlock);
                this.poll();
            },

            csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.content || '';
            },

            async poll() {
                clearTimeout(this.pollTimer);
                if (this.stopped) return;
                try {
                    const res = await fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    if (res.status === 401 || res.status === 403 || res.status === 419) { this.stopped = true; this.setCalls([]); return; }
                    if (!res.ok) throw new Error('poll ' + res.status);
                    const data = await res.json();
                    this.failures = 0;
                    this.setCalls(Array.isArray(data.calls) ? data.calls : []);
                } catch (e) {
                    this.failures++;
                }
                // Faster while ringing; back off after errors.
                const delay = this.failures ? Math.min(60000, 4000 * Math.pow(2, this.failures)) : (this.call ? 3000 : 5000);
                this.pollTimer = setTimeout(() => this.poll(), delay);
            },

            setCalls(calls) {
                this.calls = calls;
                const current = this.call ? calls.find(c => c.id === this.call.id) : null;
                if (!current) { this.message = ''; }
                this.call = current || calls[0] || null;
                if (this.call) {
                    this.startRinging();
                    this.systemNotify(this.call);
                } else {
                    this.stopRinging();
                }
            },

            ensureAudio() {
                if (this.audio) return;
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (Ctx) this.audio = new Ctx();
            },

            // Two short double rings every 3 seconds, like a phone.
            ringOnce() {
                const ctx = this.audio;
                if (!ctx || ctx.state !== 'running') { this.muted = !!this.call; return; }
                this.muted = false;
                const burst = (start) => {
                    [440, 480].forEach(freq => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        gain.gain.setValueAtTime(0.0001, start);
                        gain.gain.exponentialRampToValueAtTime(0.18, start + 0.03);
                        gain.gain.setValueAtTime(0.18, start + 0.35);
                        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.4);
                        osc.connect(gain).connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + 0.42);
                    });
                };
                const t = ctx.currentTime + 0.02;
                burst(t);
                burst(t + 0.55);
            },

            startRinging() {
                if (this.ringTimer) return;
                this.ensureAudio();
                if (this.audio && this.audio.state === 'suspended') this.audio.resume().catch(() => {});
                this.ringOnce();
                this.ringTimer = setInterval(() => this.ringOnce(), 3000);
                if (navigator.vibrate) { try { navigator.vibrate([400, 200, 400]); } catch (e) {} }
                let on = false;
                this.titleTimer = setInterval(() => {
                    on = !on;
                    document.title = on && this.call ? 'Incoming call: ' + this.call.name : this.baseTitle;
                }, 1000);
            },

            stopRinging() {
                clearInterval(this.ringTimer);
                clearInterval(this.titleTimer);
                this.ringTimer = null;
                this.titleTimer = null;
                this.muted = false;
                document.title = this.baseTitle;
            },

            systemNotify(call) {
                if (this.notified[call.id] || !('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
                this.notified[call.id] = true;
                try {
                    const n = new Notification('Video help call', { body: call.name + ' is calling for video help', tag: 'video-help-' + call.id, requireInteraction: true });
                    n.onclick = () => { window.focus(); n.close(); };
                } catch (e) {}
            },

            async send(url) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                return { ok: res.ok && data.ok !== false, data };
            },

            async accept() {
                if (!this.call || this.busy) return;
                this.busy = true;
                this.message = '';
                try {
                    const { ok, data } = await this.send(this.call.accept_url);
                    if (ok && data.url) {
                        this.stopRinging();
                        this.stopped = true;
                        window.location.href = data.url;
                        return;
                    }
                    this.message = data.message || 'This call could not be answered.';
                    const id = this.call.id;
                    setTimeout(() => this.setCalls(this.calls.filter(c => c.id !== id)), 2500);
                } catch (e) {
                    this.message = 'Network problem. Try again.';
                }
                this.busy = false;
            },

            async decline() {
                if (!this.call || this.busy) return;
                const id = this.call.id;
                const url = this.call.decline_url;
                this.setCalls(this.calls.filter(c => c.id !== id));
                try { await this.send(url); } catch (e) {}
            },
        };
    }
</script>
