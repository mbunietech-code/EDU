{{--
    Incoming video help call: polls for ringing calls and rings (Web Audio)
    on every admin page until one admin accepts. Accept sends this admin into
    the support live room; the popup disappears for everyone else on their
    next poll. Rendered only for admins who may answer (VideoHelpService::canAnswer).
    Styled like the rest of the admin (white card, indigo, mbui buttons).
--}}
<div x-data="videoHelpRinger(@js(route('admin.video-help.ringing')))" x-cloak>
    <template x-if="call">
        <div class="fixed inset-x-3 bottom-3 z-[60] sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-96" role="alertdialog" aria-live="assertive" aria-label="Incoming video help call">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <x-brand-mark class="h-6 w-6" rounded="rounded-md" />
                        <p class="text-sm font-semibold text-gray-900">Video help call</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-indigo-600"></span>
                        Ringing
                    </span>
                </div>

                <div class="flex items-center gap-3 px-4 py-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white" x-text="initials(call.name)"></span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-900" x-text="call.name"></p>
                        <p class="truncate text-xs text-gray-500" x-text="call.email"></p>
                    </div>
                </div>

                <p x-show="call.reason" class="mx-4 mb-4 max-h-24 overflow-y-auto whitespace-pre-line break-words rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700" x-text="call.reason"></p>

                <p x-show="message" class="mx-4 mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800" x-text="message"></p>

                <p x-show="muted" class="mx-4 mb-3 text-xs text-gray-500">Click anywhere on the page to hear the ring.</p>

                <div class="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3">
                    <span class="text-xs text-gray-500" x-text="calls.length > 1 ? (calls.length - 1) + ' more waiting' : 'Ringing all support staff'"></span>
                    <div class="flex gap-2">
                        <x-mbui.button variant="secondary" class="!px-3" @click="decline()" ::disabled="busy">Decline</x-mbui.button>
                        <x-mbui.button variant="primary" class="!px-3 disabled:opacity-60" @click="accept()" ::disabled="busy">
                            <span x-text="busy ? 'Joining...' : 'Accept'"></span>
                        </x-mbui.button>
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

            initials(name) {
                return String(name || '?').trim().split(/\s+/).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('');
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
