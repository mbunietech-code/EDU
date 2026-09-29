<x-layouts.admin title="Team Chat" header="Team Chat">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Team Chat</h1>
            <p class="mt-1 text-sm text-gray-500">Private one-to-one chats and groups. Only the people in a chat can read it — nobody else, super admins included.</p>
        </div>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.team-chat.groups.create') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ New group</a>
        @endif
    </div>

    @unless ($ready)
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Private chats need database alter <strong>0016_2026_09_29_private_team_chat.sql</strong>. Apply it from Admin → Database.
        </div>
    @endunless

    <div class="mt-6 mbui-card overflow-hidden">
        @forelse ($chats as $chat)
            @php
                $latest = $chat->latestMessage;
                $other = $chat->isDirect() ? $chat->otherMember(auth()->user()) : null;
                $title = $chat->isDirect() ? ($other?->name ?? 'Former admin') : $chat->name;
            @endphp
            <a href="{{ route('admin.team-chat.groups.show', $chat) }}"
               class="flex items-center gap-3 border-b border-gray-100 p-4 transition last:border-0 hover:bg-gray-50">
                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $chat->isDirect() ? 'bg-indigo-600' : 'bg-emerald-600' }} text-sm font-semibold text-white">
                    {{ strtoupper(substr($title, 0, 1)) }}
                    @if ($other)
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white {{ $other->isOnline() ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-semibold text-gray-900">
                            {{ $title }}
                            @unless ($chat->isDirect())
                                <span class="ml-1 text-xs font-normal text-gray-400">Group · {{ $chat->members->count() }}</span>
                            @endunless
                        </p>
                        <span class="shrink-0 text-xs text-gray-400">{{ $latest?->created_at->diffForHumans() ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm {{ $chat->unread > 0 ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                            @if (! $latest)
                                No messages yet
                            @elseif ($latest->is_deleted)
                                <span class="italic">Message deleted</span>
                            @else
                                @if ($latest->sender_id === auth()->id())
                                    <span class="text-gray-400">You:</span>
                                @elseif (! $chat->isDirect())
                                    <span class="text-gray-400">{{ $latest->sender?->name }}:</span>
                                @endif
                                {{ $latest->type === 'text' ? $latest->body : 'Attached a '.$latest->type }}
                            @endif
                        </p>
                        @if ($chat->unread > 0)
                            <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">
                                {{ $chat->unread > 99 ? '99+' : $chat->unread }}
                            </span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <x-mbui.empty-state title="No chats yet" message="Start a private chat with a colleague below." />
        @endforelse
    </div>

    @if ($others->isNotEmpty())
        <div class="mt-6 mbui-card overflow-hidden">
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Start a private chat</div>
            @foreach ($others as $admin)
                <form method="POST" action="{{ route('admin.team-chat.start', $admin) }}" class="border-b border-gray-100 last:border-0">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 p-4 text-left transition hover:bg-gray-50">
                        <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-300 text-sm font-semibold text-white">
                            {{ strtoupper(substr($admin->name, 0, 1)) }}
                            <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white {{ $admin->isOnline() ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $admin->name }}</p>
                            <p class="text-xs text-gray-400">{{ $admin->isSuperAdmin() ? 'Super admin' : 'Admin' }}</p>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

</x-layouts.admin>
