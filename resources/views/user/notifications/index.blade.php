<x-layouts.user title="Notifications" header="Notifications">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Notifications</h1>
        </div>
        <form method="POST" action="{{ route('user.notifications.mark-all-read') }}">
            @csrf
            <x-mbui.button type="submit" variant="secondary" class="!px-3 !py-1.5 text-xs">Mark all as read</x-mbui.button>
        </form>
    </div>

    <div class="mt-6">
        @if ($notifications->isEmpty())
            <x-mbui.card>
                <x-mbui.empty-state title="No notifications" message="We'll notify you about orders, payments, and subscriptions here." />
            </x-mbui.card>
        @else
            <x-mbui.table>
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">Message</th>
                        <th class="mbui-th">Received</th>
                        <th class="mbui-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($notifications as $notification)
                        <tr class="{{ $notification->read_at ? '' : 'bg-indigo-50/50' }}">
                            <td class="mbui-td {{ $notification->read_at ? 'text-gray-600' : 'font-medium text-gray-900' }}">
                                @php
                                    $url = $notification->data['url'] ?? null;
                                    $home = url('/');
                                    $sameHost = is_string($url) && ($url === $home || str_starts_with($url, $home.'/'));
                                @endphp
                                @if ($sameHost)
                                    <a href="{{ $url }}" class="hover:text-indigo-600 hover:underline">{{ $notification->data['message'] ?? 'Notification' }}</a>
                                @else
                                    {{ $notification->data['message'] ?? 'Notification' }}
                                @endif
                            </td>
                            <td class="mbui-td text-gray-500 whitespace-nowrap">{{ $notification->created_at->diffForHumans() }}</td>
                            <td class="mbui-td">
                                <div class="flex items-center justify-between gap-3">
                                    @if ($notification->read_at)
                                        <x-mbui.badge appearance="neutral">Read</x-mbui.badge>
                                    @else
                                        <x-mbui.badge appearance="info">Unread</x-mbui.badge>
                                        <form method="POST" action="{{ route('user.notifications.mark-read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">Mark read</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
            <div class="mt-6">{{ $notifications->links() }}</div>
        @endif
    </div>

</x-layouts.user>