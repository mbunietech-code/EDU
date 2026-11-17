<x-layouts.admin title="Video help" header="Video help">
    @php($tz = config('app.business_timezone', 'Africa/Dar_es_Salaam'))

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Video help calls</h1>
            <p class="mt-1 text-sm text-gray-500">
                Members call from the "Video help" tab. Every support admin hears it ringing on any admin page until one accepts;
                the first to accept goes into
                @if ($room)
                    <a href="{{ route('studio.rooms.show', $room->id) }}" class="mbui-anchor">{{ $room->title }}</a>
                @else
                    the support live room
                @endif
                and the member follows. Calls nobody answers become missed.
            </p>
        </div>
        <div x-data="{ perm: ('Notification' in window) ? Notification.permission : 'unsupported' }" x-show="perm === 'default'" x-cloak>
            <x-mbui.button variant="secondary" @click="Notification.requestPermission().then(p => perm = p)">Turn on desktop alerts</x-mbui.button>
        </div>
    </div>

    @if (! $room || $room->isCancelled())
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            The support live room (id {{ (int) config('learning.video_help.room_id') }}) is missing or cancelled, so calls cannot be answered. Publish it again in Studio.
        </div>
    @endif

    {{-- Call devices: computers / phones that ring even when nobody is signed in on them. --}}
    <div class="mt-6 mbui-card" x-data="callDevices(@js([
        'vapidKey' => $vapidKey,
        'storeUrl' => route('admin.video-help.devices.store'),
        'swUrl' => asset('call-sw.js'),
        'hashes' => $devices->pluck('token_hash')->values(),
    ]))">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-gray-900">Devices that ring for calls</h2>
                <p class="mt-1 text-sm text-gray-500">
                    A registered computer rings (system notification) even after you sign out, as long as it is on, online and the browser is running.
                    A phone rings through the MHub app: Profile → "Ring this phone for video help calls". Answering always needs a sign-in.
                </p>
            </div>
            <div class="shrink-0">
                <template x-if="state === 'registered'">
                    <x-mbui.badge appearance="success">This computer rings</x-mbui.badge>
                </template>
                <template x-if="state === 'unsupported'">
                    <p class="max-w-xs text-xs text-gray-500">This browser cannot ring when the page is closed. Use Chrome, Edge or Firefox on a computer.</p>
                </template>
                <template x-if="state === 'blocked'">
                    <p class="max-w-xs text-xs text-amber-700">Notifications are blocked for this site. Allow them in the browser's site settings, then reload.</p>
                </template>
                <template x-if="state === 'ready'">
                    <x-mbui.button variant="primary" @click="register()" ::disabled="busy">
                        <span x-text="busy ? 'Registering...' : 'Ring this computer for calls'"></span>
                    </x-mbui.button>
                </template>
            </div>
        </div>
        <p x-show="message" x-cloak class="mx-4 mt-3 rounded-lg border px-3 py-2 text-sm" :class="failed ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'" x-text="message"></p>

        @if ($devices->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-500">No devices yet. Calls only ring for admins who have an admin page open.</p>
        @else
            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th px-4">Device</th>
                        <th class="mbui-th px-4">Admin</th>
                        <th class="mbui-th px-4">Last rung</th>
                        <th class="mbui-th px-4">Last checked in</th>
                        <th class="mbui-th px-4"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($devices as $device)
                        @php($mine = (int) $device->user_id === (int) auth()->id())
                        <tr>
                            <td class="mbui-td px-4">
                                <p class="font-medium text-gray-900">
                                    {{ $device->name ?: ($device->channel === 'fcm' ? 'Phone' : 'Computer') }}
                                    <span x-show="isThis(@js($device->token_hash))" x-cloak class="ml-1 text-xs font-normal text-indigo-600">(this computer)</span>
                                </p>
                                <p class="text-xs text-gray-500">{{ $device->kindLabel() }} · added {{ $device->created_at?->timezone($tz)->format('d M Y') }}</p>
                            </td>
                            <td class="mbui-td px-4 text-gray-700">{{ $device->user?->name ?? '-' }}</td>
                            <td class="mbui-td px-4 text-gray-500">{{ $device->last_rung_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="mbui-td px-4 text-gray-500">
                                @if ($device->channel === 'webpush')
                                    {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}
                                @else
                                    <span class="text-xs">Phones do not report back</span>
                                @endif
                            </td>
                            <td class="mbui-td px-4 text-right">
                                @if ($mine || auth()->user()->isSuperAdmin())
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.video-help.devices.test', $device) }}">
                                            @csrf
                                            <x-mbui.button type="submit" variant="secondary" class="!px-3">Test</x-mbui.button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.video-help.devices.destroy', $device) }}" onsubmit="return confirm('Stop this device ringing for calls?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-mbui.button type="submit" variant="ghost" class="!px-3 text-red-600">Remove</x-mbui.button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
            <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
                "Last checked in" updates when a computer wakes up for a ring or a test. Use Test to check that a device is online right now.
            </p>
        @endif
    </div>

    <script>
        function callDevices(cfg) {
            return {
                state: 'checking',
                busy: false,
                message: '',
                failed: false,
                thisHash: null,

                async init() {
                    if (!cfg.vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window) || !window.isSecureContext) {
                        this.state = 'unsupported';
                        return;
                    }
                    if (Notification.permission === 'denied') { this.state = 'blocked'; return; }
                    try {
                        const reg = await navigator.serviceWorker.getRegistration(cfg.swUrl);
                        const sub = reg ? await reg.pushManager.getSubscription() : null;
                        if (sub) this.thisHash = await this.sha256(sub.endpoint);
                    } catch (e) {}
                    this.state = this.thisHash && cfg.hashes.includes(this.thisHash) ? 'registered' : 'ready';
                },

                isThis(hash) { return this.thisHash === hash; },

                async sha256(text) {
                    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
                    return [...new Uint8Array(buf)].map(b => b.toString(16).padStart(2, '0')).join('');
                },

                key(b64) {
                    const pad = '='.repeat((4 - b64.length % 4) % 4);
                    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                    return Uint8Array.from(raw, c => c.charCodeAt(0));
                },

                deviceName() {
                    const ua = navigator.userAgent;
                    const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Browser';
                    const os = /Windows/.test(ua) ? 'Windows' : /Mac OS X/.test(ua) ? 'Mac' : /Android/.test(ua) ? 'Android' : /Linux/.test(ua) ? 'Linux' : 'computer';
                    return browser + ' on ' + os;
                },

                async register() {
                    this.busy = true; this.message = ''; this.failed = false;
                    try {
                        const perm = await Notification.requestPermission();
                        if (perm !== 'granted') { this.state = perm === 'denied' ? 'blocked' : 'ready'; throw new Error('Allow notifications to let this computer ring.'); }
                        const reg = await navigator.serviceWorker.register(cfg.swUrl);
                        await navigator.serviceWorker.ready;
                        let sub = await reg.pushManager.getSubscription();
                        if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.key(cfg.vapidKey) });
                        const name = prompt('Name this device (e.g. Office PC):', this.deviceName()) || this.deviceName();
                        const res = await fetch(cfg.storeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ endpoint: sub.endpoint, name }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(data.message || 'Could not register this computer.');
                        this.message = data.message; this.failed = !data.tested;
                        setTimeout(() => window.location.reload(), 2500);
                    } catch (e) {
                        this.failed = true;
                        this.message = e.message || 'Could not register this computer.';
                    }
                    this.busy = false;
                },
            };
        }
    </script>

    <div class="mt-6 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th px-4">Member</th>
                    <th class="mbui-th px-4">Reason</th>
                    <th class="mbui-th px-4">Called</th>
                    <th class="mbui-th px-4">Status</th>
                    <th class="mbui-th px-4">Answered by</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($calls as $call)
                    <tr>
                        <td class="mbui-td max-w-xs whitespace-normal break-words px-4">
                            <p class="font-medium text-gray-900">{{ $call->user?->name ?? 'Deleted member' }}</p>
                            <p class="break-all text-xs text-gray-500">{{ $call->user?->email }}</p>
                        </td>
                        <td class="mbui-td whitespace-normal break-words px-4 text-gray-700">{{ $call->reason ?: '-' }}</td>
                        <td class="mbui-td px-4 text-gray-500">
                            {{ $call->created_at?->timezone($tz)->format('d M Y') }}
                            <p class="text-xs text-gray-400">{{ $call->created_at?->timezone($tz)->format('H:i') }}</p>
                        </td>
                        <td class="mbui-td px-4">
                            <x-mbui.badge :appearance="match ($call->status) { 'accepted' => 'success', 'missed' => 'warning', 'ringing' => 'info', default => 'neutral' }">{{ $call->statusLabel() }}</x-mbui.badge>
                        </td>
                        <td class="mbui-td px-4 text-gray-700">
                            {{ $call->acceptedBy?->name ?? '-' }}
                            @if ($call->accepted_at)
                                <p class="text-xs text-gray-400">{{ $call->accepted_at->timezone($tz)->format('H:i') }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="mbui-td px-4 py-10 text-center text-gray-500">No video help calls yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-4">{{ $calls->links() }}</div>
</x-layouts.admin>
