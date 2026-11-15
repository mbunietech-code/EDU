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
