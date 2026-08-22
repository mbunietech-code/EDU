<x-layouts.user title="Messages" header="Messages">

    <div class="flex h-[calc(100vh-9rem)] min-h-[26rem] flex-col">
        <div class="mbui-page-header shrink-0">
            <div>
                <h1 class="mbui-title">Chat with {{ config('app.name', 'MbunieEduHub') }} Support</h1>
                <p class="mt-1 text-sm text-gray-500">Reply to us here or upload a screenshot, video or voice note.</p>
            </div>
        </div>

        <div class="mt-4 flex min-h-0 flex-1 flex-col mbui-card p-4 sm:p-6"
            x-data="chatThread({{ $conversation->id }}, false, '{{ route('user.chat.store', $conversation) }}', '{{ route('user.chat.fetch', $conversation) }}', {{ $supportOnline ? 'true' : 'false' }})">
            <div class="mb-4 flex shrink-0 items-center gap-3 border-b border-gray-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                    {{ strtoupper(substr(config('app.name', 'MbunieEduHub'), 0, 1)) }}
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ config('app.name', 'MbunieEduHub') }} Support</p>
                    <p class="text-xs flex items-center gap-1" :class="otherOnline ? 'text-emerald-600' : 'text-gray-400'">
                        <span class="h-2 w-2 rounded-full" :class="otherOnline ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                        <span x-text="otherOnline ? 'Online' : 'Offline'"></span>
                    </p>
                </div>
            </div>

            @include('chat.partials.thread', [
                'conversation' => $conversation,
                'payload' => $payload,
                'isAdmin' => false,
                'sendUrl' => route('user.chat.store', $conversation),
                'fetchUrl' => route('user.chat.fetch', $conversation),
            ])
        </div>
    </div>

</x-layouts.user>