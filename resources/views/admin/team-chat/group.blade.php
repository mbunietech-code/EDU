@php
    $other = $group->isDirect() ? $group->otherMember(auth()->user()) : null;
    $title = $group->isDirect() ? ($other?->name ?? 'Former admin') : $group->name;
@endphp
<x-layouts.admin title="{{ $title }}" header="Team Chat">

    <div class="flex h-[calc(100vh-9rem)] min-h-[26rem] flex-col">
        <div class="mbui-page-header shrink-0">
            <div>
                <a href="{{ route('admin.team-chat.index') }}" class="mbui-anchor text-sm">&larr; All chats</a>
            </div>
        </div>

        <div class="mt-4 flex min-h-0 flex-1 flex-col mbui-card p-4 sm:p-6"
            x-data="chatThread({{ $group->id }}, true, '{{ route('admin.team-chat.groups.send', $group) }}', '{{ route('admin.team-chat.groups.fetch', $group) }}', {{ $other?->isOnline() ? 'true' : 'false' }})">
            <div class="mb-4 flex shrink-0 items-center gap-3 border-b border-gray-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $other ? 'bg-indigo-600' : 'bg-emerald-600' }} text-sm font-semibold text-white">
                    {{ strtoupper(substr($title, 0, 1)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ $title }}</p>
                    @if ($group->isDirect())
                        <p class="flex items-center gap-1.5 text-xs">
                            <span class="text-gray-500">Private — only you and {{ $other?->name ?? 'them' }} can read this</span>
                            <span class="inline-flex items-center gap-1" :class="otherOnline ? 'text-emerald-600' : 'text-gray-400'">
                                <span class="h-2 w-2 rounded-full" :class="otherOnline ? 'bg-emerald-500' : 'bg-gray-300'"></span>
                                <span x-text="otherOnline ? 'Online' : 'Offline'"></span>
                            </span>
                        </p>
                    @else
                        <p class="truncate text-xs text-gray-500">{{ $group->members->count() }} members: {{ $group->members->pluck('name')->implode(', ') }}</p>
                    @endif
                </div>
                @if (! $group->isDirect() && auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.team-chat.groups.settings', $group) }}" class="mbui-anchor shrink-0 text-sm">Settings</a>
                @endif
            </div>

            @include('chat.partials.thread', [
                'conversation' => $group,
                'payload' => $payload,
                'isAdmin' => true,
                'sendUrl' => route('admin.team-chat.groups.send', $group),
                'fetchUrl' => route('admin.team-chat.groups.fetch', $group),
                'placeholder' => 'Message ' . $title . '...',
            ])
        </div>
    </div>

</x-layouts.admin>
