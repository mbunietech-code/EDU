@props(['room'])

@php
    /** @var \App\Models\LearningRoom $room */
    $accessIcons = [
        // globe
        'public' => 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418',
        // lock
        'private' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
        // tag
        'category' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z',
        // academic cap
        'course' => 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342',
    ];
    $accessIcon = $accessIcons[$room->access] ?? $accessIcons['public'];
@endphp

<div {{ $attributes->merge(['class' => 'mbui-card flex flex-col p-4']) }}>
    <div class="flex items-start justify-between gap-3">
        <x-learning.room-status :status="$room->status" />
        <span class="inline-flex items-center gap-1 text-xs text-gray-500" title="{{ $room->accessLabel() }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $accessIcon }}" />
            </svg>
            <span class="sr-only">Access:</span>
            <span class="hidden sm:inline">{{ $room->accessLabel() }}</span>
        </span>
    </div>

    <h3 class="mt-3 line-clamp-2 text-sm font-semibold text-gray-900">
        <a href="{{ route('learn.rooms.show', $room) }}" class="hover:text-indigo-600">{{ $room->title }}</a>
    </h3>

    <dl class="mt-2 space-y-1 text-xs text-gray-600">
        @if ($room->scheduled_at)
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">When</dt>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                <dd><time datetime="{{ $room->scheduled_at->toIso8601String() }}">{{ $room->scheduled_at->format('D, d M Y · H:i') }}</time></dd>
            </div>
        @endif
        @if ($room->host)
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Host</dt>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <dd class="truncate">{{ $room->host->name }}</dd>
            </div>
        @endif
        @if ($room->category)
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Category</dt>
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>
                <dd class="truncate">{{ $room->category->name }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-auto pt-4">
        @if ($room->isLive())
            <x-mbui.btn-link :href="route('learn.rooms.live', $room)" variant="danger" class="w-full">Join now</x-mbui.btn-link>
        @else
            <x-mbui.btn-link :href="route('learn.rooms.show', $room)" variant="secondary" class="w-full">View details</x-mbui.btn-link>
        @endif
    </div>
</div>
