<x-layouts.user title="Messages" header="Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Chat with {{ config('app.name', 'MbunieEduHub') }} Support</h1>
            <p class="mt-1 text-sm text-gray-500">Reply to us here or upload a screenshot, video or voice note.</p>
        </div>
    </div>

    <div class="mt-6 mbui-card p-4 sm:p-6">
        <div class="mb-4 flex items-center gap-3 border-b border-gray-100 pb-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                {{ strtoupper(substr(config('app.name', 'MbunieEduHub'), 0, 1)) }}
            </span>
            <div>
                <p class="text-sm font-semibold text-gray-900">{{ config('app.name', 'MbunieEduHub') }} Support</p>
                <p class="text-xs text-emerald-600 flex items-center gap-1">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Online
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

</x-layouts.user>