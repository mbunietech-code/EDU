<x-layouts.admin title="{{ $conversation->user->name }}" header="Chat">

    <div class="mbui-page-header">
        <div>
            <a href="{{ route('admin.chat.index') }}" class="mbui-anchor text-sm">&larr; All chats</a>
        </div>
    </div>

    <div class="mt-4 mbui-card p-4 sm:p-6">
        <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-4">
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

</x-layouts.admin>