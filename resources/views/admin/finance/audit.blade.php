@php
    $problems = collect($checks)->where('severity', 'problem')->count();
    $warnings = collect($checks)->where('severity', 'warning')->count();
    $tone = [
        'problem' => ['ring-red-200', 'bg-red-50', 'text-red-800', 'bg-red-100 text-red-700', 'Fix'],
        'warning' => ['ring-amber-200', 'bg-amber-50', 'text-amber-900', 'bg-amber-100 text-amber-800', 'Check'],
        'ok' => ['ring-gray-200', 'bg-white', 'text-gray-900', 'bg-emerald-100 text-emerald-700', 'OK'],
    ];
@endphp

<x-layouts.finance title="Audit" header="Finance">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Finance audit</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">What needs a look before the books can be trusted, and who added, changed or removed what.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-3">
        <x-mbui.card class="p-5">
            <p class="text-sm font-medium text-gray-500">To fix</p>
            <p class="mt-1 text-2xl font-bold tracking-tight {{ $problems ? 'text-red-600' : 'text-gray-900' }}">{{ $problems }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm font-medium text-gray-500">To check</p>
            <p class="mt-1 text-2xl font-bold tracking-tight {{ $warnings ? 'text-amber-600' : 'text-gray-900' }}">{{ $warnings }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-sm font-medium text-gray-500">All good</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-emerald-600">{{ collect($checks)->where('severity', 'ok')->count() }}</p>
        </x-mbui.card>
    </div>

    {{-- Checks: problems first --}}
    <div class="mt-8 space-y-4">
        @foreach (collect($checks)->sortBy(fn ($c) => ['problem' => 0, 'warning' => 1, 'ok' => 2][$c['severity']])->values() as $check)
            @php([$ring, $bg, $text, $badge, $word] = $tone[$check['severity']])
            <div class="mbui-card overflow-hidden ring-1 {{ $ring }}" x-data="{ open: {{ $check['severity'] === 'problem' ? 'true' : 'false' }} }">
                <button type="button" x-on:click="open = ! open" class="flex w-full items-start justify-between gap-3 px-6 py-4 text-left {{ $bg }}" @if (! $check['items']) disabled @endif>
                    <div>
                        <p class="font-semibold {{ $text }}">{{ $check['title'] }}@if ($check['items']) · {{ count($check['items']) }}@endif</p>
                        <p class="mt-0.5 text-sm text-gray-600">{{ $check['help'] }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $word }}</span>
                </button>
                @if ($check['items'])
                    <ul x-show="open" x-cloak class="divide-y divide-gray-100 border-t border-gray-100 text-sm" role="list">
                        @foreach ($check['items'] as $item)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-2.5">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">{{ $item['label'] }}</p>
                                    @if ($item['detail'])<p class="text-xs text-gray-500">{{ $item['detail'] }}</p>@endif
                                </div>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" class="mbui-anchor shrink-0 text-sm">Open</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Activity --}}
    <div class="mt-10 mbui-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Activity</h2>
                <p class="mt-1 text-sm text-gray-500">Who added, changed or removed what in Finance (Tanzania time). Removed records keep their reason.</p>
            </div>
            <nav class="flex flex-wrap gap-1" aria-label="Activity filter">
                @foreach ([null => 'All', 'added' => 'Added', 'changed' => 'Changed', 'removed' => 'Removed'] as $key => $text)
                    <a href="{{ route('admin.finance.audit', array_filter(['kind' => $key])) }}"
                        @if ($kind === ($key ?: null)) aria-current="page" @endif
                        class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $kind === ($key ?: null) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">{{ $text }}</a>
                @endforeach
            </nav>
        </div>
        <ul class="divide-y divide-gray-100 text-sm" role="list">
            @forelse ($activity as $a)
                <li class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 px-6 py-3">
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">
                            <span class="mr-1 rounded px-1.5 py-0.5 text-xs font-semibold {{ ['added' => 'bg-emerald-50 text-emerald-700', 'changed' => 'bg-indigo-50 text-indigo-700', 'removed' => 'bg-red-50 text-red-700'][$a['kind']] }}">{{ ucfirst($a['kind']) }}</span>
                            {{ $a['what'] }}
                        </p>
                        @if ($a['detail'])<p class="mt-0.5 text-xs text-gray-500">{{ $a['detail'] }}</p>@endif
                    </div>
                    <p class="shrink-0 text-xs text-gray-500">{{ $a['who'] ?? 'System' }} · {{ $a['at']?->format('d M Y, H:i') }}</p>
                </li>
            @empty
                <li class="px-6 py-4 text-gray-400">Nothing yet</li>
            @endforelse
        </ul>
        @unless (request()->boolean('more'))
            <div class="border-t border-gray-100 px-6 py-3 text-right">
                <a href="{{ route('admin.finance.audit', array_filter(['kind' => $kind, 'more' => 1])) }}" class="mbui-anchor text-sm">Show older activity</a>
            </div>
        @endunless
    </div>

</x-layouts.finance>
