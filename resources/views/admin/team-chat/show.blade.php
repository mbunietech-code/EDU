<x-layouts.admin title="{{ $viewerIsTheAdmin ? 'Super Admin' : $conversation->admin->name }}" header="Team Chat">

    <div class="flex h-[calc(100vh-9rem)] min-h-[26rem] flex-col">
        <div class="mbui-page-header shrink-0">
            <div>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.team-chat.index') }}" class="mbui-anchor text-sm">&larr; All threads</a>
                @endif
            </div>
        </div>

        <div class="mt-4 flex min-h-0 flex-1 flex-col mbui-card p-4 sm:p-6"
            x-data="chatThread({{ $conversation->id }}, {{ $viewerIsTheAdmin ? 'true' : 'false' }}, '{{ route('admin.team-chat.store', $conversation) }}', '{{ route('admin.team-chat.fetch', $conversation) }}', {{ $conversation->admin->isOnline() ? 'true' : 'false' }})">
            <div class="mb-4 flex shrink-0 items-center gap-3 border-b border-gray-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ $viewerIsTheAdmin ? 'S' : strtoupper(substr($conversation->admin->name, 0, 1)) }}
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $viewerIsTheAdmin ? 'Super Admin' : $conversation->admin->name }}</p>
                    <p class="text-xs flex items-center gap-1.5">
                        @if (! $viewerIsTheAdmin)
                            <span class="text-gray-500">{{ $conversation->admin->email }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1" :class="otherOnline ? 'text-emerald-600' : 'text-gray-400'">
                            <span class="h-2 w-2 rounded-full" :class="otherOnline ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                            <span x-text="otherOnline ? 'Online' : 'Offline'"></span>
                        </span>
                    </p>
                </div>
            </div>

            @include('chat.partials.thread', [
                'conversation' => $conversation,
                'payload' => $payload,
                'isAdmin' => true,
                'sendUrl' => route('admin.team-chat.store', $conversation),
                'fetchUrl' => route('admin.team-chat.fetch', $conversation),
                'placeholder' => $viewerIsTheAdmin ? 'Message Super Admin...' : 'Reply to ' . $conversation->admin->name . '...',
            ])
        </div>
    </div>

</x-layouts.admin>
