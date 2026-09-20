<x-layouts.admin title="Messages" header="Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Chats</h1>
            <p class="mt-1 text-sm text-gray-500">Conversations with customers. Open one to reply, or record a voice note.</p>
        </div>
        <div class="flex items-center gap-3">
            @can('chat.manage')
                <a href="{{ route('admin.chat.auto-reply.edit') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Auto-reply</a>
            @endcan
            <a href="{{ route('admin.chat.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">New message</a>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <form method="GET" class="flex gap-3">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search by name or email..." class="mbui-input sm:w-80">
                <x-mbui.button type="submit" variant="secondary" class="!px-4 !py-2">Filter</x-mbui.button>
                @if ($search)
                    <a href="{{ route('admin.chat.index') }}" class="mbui-anchor self-center text-sm">Clear</a>
                @endif
            </form>
        </div>
        @forelse ($conversations as $conversation)
            <a href="{{ route('admin.chat.show', $conversation) }}"
               class="flex items-center gap-3 border-b border-gray-100 p-4 transition hover:bg-gray-50">
                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ strtoupper(substr($conversation->user->name, 0, 1)) }}
                    <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white {{ $conversation->user->isOnline() ? 'bg-emerald-500' : 'bg-gray-300' }}"
                        title="{{ $conversation->user->isOnline() ? 'Online' : 'Offline' }}"></span>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-semibold text-gray-900">{{ $conversation->user->name }}</p>
                        <span class="shrink-0 text-xs text-gray-400">
                            {{ $conversation->latestMessage?->created_at->diffForHumans() ?? '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm {{ $conversation->unread > 0 ? 'font-medium text-gray-900' : 'text-gray-500' }}">
                            @php
                                $latestMessage = $conversation->latestMessage;
                                $messageType = $latestMessage?->type ?? 'text';
                            @endphp
                            @switch($messageType)
                                @case('text')
                                    {{ $latestMessage?->body ?? 'No message' }}
                                    @break
                                @case('image')
                                    Attached an image
                                    @break
                                @case('video')
                                    Attached a video
                                    @break
                                @case('audio')
                                    Attached a voice note
                                    @break
                                @default
                                    New message
                            @endswitch
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
            <x-mbui.empty-state title="No conversations yet" message="When a customer sends a message it will appear here." />
        @endforelse
    </div>

</x-layouts.admin>