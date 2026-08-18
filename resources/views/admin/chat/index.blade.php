<x-layouts.admin title="Messages" header="Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Chats</h1>
            <p class="mt-1 text-sm text-gray-500">Conversations with customers. Open one to reply, or record a voice note.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card overflow-hidden">
        @forelse ($conversations as $conversation)
            <a href="{{ route('admin.chat.show', $conversation) }}"
               class="flex items-center gap-3 border-b border-gray-100 p-4 transition hover:bg-gray-50">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ strtoupper(substr($conversation->user->name, 0, 1)) }}
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