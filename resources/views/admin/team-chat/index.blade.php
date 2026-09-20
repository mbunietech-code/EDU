<x-layouts.admin title="Team Chat" header="Team Chat">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Team Chat</h1>
            <p class="mt-1 text-sm text-gray-500">Private threads with each admin, and groups where several admins chat together. Every super admin sees and can reply in the private threads — the admin sees you all as one "Super Admin" conversation.</p>
        </div>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.team-chat.groups.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ New group</a>
        @endif
    </div>

    @if ($groups->isNotEmpty())
        <div class="mt-6 mbui-card overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Groups</div>
            @foreach ($groups as $group)
                @php $latest = $group->latestMessage; @endphp
                <a href="{{ route('admin.team-chat.groups.show', $group) }}"
                   class="flex items-center gap-3 border-b border-gray-100 p-4 transition last:border-0 hover:bg-gray-50">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">
                        {{ strtoupper(substr($group->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $group->name }}</p>
                            <span class="shrink-0 text-xs text-gray-400">{{ $latest?->created_at->diffForHumans() ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-sm {{ $group->unread > 0 ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                                @if (! $latest)
                                    No messages yet
                                @elseif ($latest->is_deleted)
                                    <span class="italic">Message deleted</span>
                                @else
                                    <span class="text-gray-400">{{ $latest->sender_id === auth()->id() ? 'You' : $latest->sender?->name }}:</span>
                                    {{ $latest->type === 'text' ? $latest->body : 'Attached a '.$latest->type }}
                                @endif
                            </p>
                            @if ($group->unread > 0)
                                <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">
                                    {{ $group->unread > 99 ? '99+' : $group->unread }}
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($ownThread)
        <div class="mt-6 mbui-card overflow-hidden">
            <a href="{{ route('admin.team-chat.show', $ownThread) }}" class="flex items-center gap-3 p-4 transition hover:bg-gray-50">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">S</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900">Super Admin</p>
                    <p class="text-xs text-gray-400">Private conversation</p>
                </div>
            </a>
        </div>
    @endif

    <div class="mt-6 mbui-card overflow-hidden">
        @forelse ($conversations as $conversation)
            <a href="{{ route('admin.team-chat.show', $conversation) }}"
               class="flex items-center gap-3 border-b border-gray-100 p-4 transition hover:bg-gray-50">
                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ strtoupper(substr($conversation->admin->name, 0, 1)) }}
                    <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white {{ $conversation->admin->isOnline() ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $conversation->admin->name }}</p>
                        <span class="shrink-0 text-xs text-gray-400">
                            {{ $conversation->latestMessage?->created_at->diffForHumans() ?? '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm {{ $conversation->unread > 0 ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                            @php $latest = $conversation->latestMessage; @endphp
                            @if (! $latest)
                                No messages yet
                            @elseif ($latest->is_deleted)
                                <span class="italic">Message deleted</span>
                            @elseif ($latest->type === 'text')
                                {{ $latest->body }}
                            @else
                                Attached a {{ $latest->type }}
                            @endif
                        </p>
                        @if ($conversation->unread > 0)
                            <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">
                                {{ $conversation->unread > 99 ? '99+' : $conversation->unread }}
                            </span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <x-mbui.empty-state title="No threads yet" message="Once an admin exists, their thread will appear here." />
        @endforelse

        @foreach ($missing as $admin)
            <form method="POST" action="{{ route('admin.team-chat.start', $admin) }}" class="border-b border-gray-100 last:border-0">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-300 text-sm font-semibold text-white">
                        {{ strtoupper(substr($admin->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $admin->name }}</p>
                        <p class="text-xs text-gray-400">Start a conversation</p>
                    </div>
                </button>
            </form>
        @endforeach
    </div>

</x-layouts.admin>
