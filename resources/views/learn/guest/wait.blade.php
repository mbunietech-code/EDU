{{-- Waiting room: the guest waits here until the host lets them in. --}}
<x-layouts.classroom :title="$room->title">
    <main class="flex flex-1 items-center justify-center overflow-y-auto p-4"
        x-data="{
            state: 'waiting',
            timer: null,
            async check() {
                try {
                    const r = await fetch(@js($statusUrl), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const data = await r.json().catch(() => ({}));
                    this.state = r.ok ? (data.state || 'waiting') : 'ended';
                } catch (e) { /* offline: try again */ }
                if (this.state === 'admitted') { window.location.href = @js($liveUrl); return; }
                if (this.state === 'waiting') this.timer = setTimeout(() => this.check(), 4000);
            },
        }"
        x-init="check()">
        <div class="w-full max-w-sm rounded-2xl bg-gray-900 p-6 text-center shadow-2xl ring-1 ring-gray-800" role="status" aria-live="polite">
            <h1 class="text-lg font-semibold text-white">{{ $room->title }}</h1>

            <template x-if="state === 'waiting'">
                <div>
                    <div class="mx-auto mt-5 h-10 w-10 animate-spin rounded-full border-4 border-gray-700 border-t-indigo-500" aria-hidden="true"></div>
                    <p class="mt-4 text-sm text-gray-200">Please wait, the host will let you in soon.</p>
                    <p class="mt-1 text-xs text-gray-500">Keep this page open.</p>
                </div>
            </template>
            <template x-if="state === 'denied' || state === 'ended'">
                <p class="mt-4 text-sm text-gray-300">The host did not let you into this meeting.</p>
            </template>
        </div>
    </main>
</x-layouts.classroom>
