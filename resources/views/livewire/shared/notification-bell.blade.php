<div x-data="{ open: false }" class="relative" @click.outside="open = false">
    <button type="button" @click="open = !open" class="relative p-2 rounded-lg text-gray-600 hover:bg-gray-100">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-semibold text-white">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-transition
        class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
        <div class="border-b border-gray-100 px-4 py-3 flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-900">Notifications</p>
            <a href="{{ auth()->user()->is_admin ? route('admin.notifications.index') : route('user.notifications.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all</a>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
            @forelse ($recentNotifications as $notification)
                <a href="{{ auth()->user()->is_admin ? route('admin.notifications.index') : route('user.notifications.index') }}" class="block px-4 py-3 hover:bg-gray-50">
                    <p class="text-sm {{ $notification->read_at ? 'text-gray-600' : 'font-medium text-gray-900' }}">
                        {{ $notification->data['message'] ?? 'New notification' }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-500">You're all caught up.</div>
            @endforelse
        </div>
    </div>
</div>