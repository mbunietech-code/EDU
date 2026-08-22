<x-layouts.admin title="{{ $conversation->user->name }}" header="Chat">

    <div class="flex h-[calc(100vh-9rem)] min-h-[26rem] flex-col">
        <div class="mbui-page-header shrink-0">
            <div>
                <a href="{{ route('admin.chat.index') }}" class="mbui-anchor text-sm">&larr; All chats</a>
            </div>
        </div>

        <div class="mt-4 flex min-h-0 flex-1 flex-col mbui-card p-4 sm:p-6">
            <div class="mb-4 flex shrink-0 items-center gap-3 border-b border-gray-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ strtoupper(substr($conversation->user->name, 0, 1)) }}
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $conversation->user->name }}</p>
                    <p class="text-xs text-gray-500">{{ $conversation->user->email }}</p>
                </div>
            </div>

            @include('chat.partials.thread', [
                'conversation' => $conversation,
                'payload' => $payload,
                'isAdmin' => true,
                'sendUrl' => route('admin.chat.store', $conversation),
                'fetchUrl' => route('admin.chat.fetch', $conversation),
            ])
        </div>
    </div>

</x-layouts.admin>