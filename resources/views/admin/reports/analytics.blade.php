<x-layouts.admin title="Analytics" header="Analytics">
@php
    $typeShort = ['page_view' => 'Views', 'download' => 'Downloads', 'app_open' => 'App opens'];
    $typeColor = ['page_view' => 'bg-indigo-500', 'download' => 'bg-emerald-500', 'app_open' => 'bg-amber-500'];
    $typeSoft = ['page_view' => 'bg-indigo-200', 'download' => 'bg-emerald-200', 'app_open' => 'bg-amber-200'];
    $fmt = fn ($n) => number_format((int) $n);
    $maxBy = [];
    foreach (array_keys($types) as $t) {
        $maxBy[$t] = max(1, collect($series)->max(fn ($r) => $r['stats'][$t]['total']));
    }
    $labelEvery = max(1, (int) ceil(count($series) / 12));
@endphp

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Analytics</h1>
            <p class="mt-1 text-sm text-gray-500">
                Website views on {{ request()->getHost() }}, MHub app downloads and app opens.
                <span class="font-medium text-gray-700">Unique</span> = different devices (the same phone or computer counts once).
                Times are {{ config('app.business_timezone', 'Africa/Dar_es_Salaam') }}.
            </p>
        </div>
    </div>

    {{-- Headline numbers --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($summary as $key => $win)
            <div class="mbui-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $win['label'] }}</h3>
                    @if ($key === 'live')
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span> live
                        </span>
                    @endif
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-3">
                    @foreach ($types as $t => $tLabel)
                        <div>
                            <dt class="text-[11px] uppercase tracking-wide text-gray-400">{{ $typeShort[$t] }}</dt>
                            <dd class="text-xl font-bold text-gray-900">{{ $fmt($win['stats'][$t]['total']) }}</dd>
                            <dd class="text-xs text-gray-500">{{ $fmt($win['stats'][$t]['unique']) }} unique</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" class="mbui-card mt-6 flex flex-wrap items-end gap-3 p-4" x-data="{ range: @js($w['range']) }">
        <div>
            <label class="block text-xs font-medium text-gray-500">Period</label>
            <select name="range" x-model="range" @change="if (range !== 'custom') $el.form.submit()" class="mbui-input mt-1 sm:w-64">
                @foreach ($ranges as $key => [$label])
                    <option value="{{ $key }}" @selected($w['range'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="range === 'custom'" x-cloak class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500">From</label>
                <input type="date" name="from" value="{{ $w['from']->format('Y-m-d') }}" class="mbui-input mt-1">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">To</label>
                <input type="date" name="to" value="{{ $w['to']->format('Y-m-d') }}" class="mbui-input mt-1">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">Group by</label>
                <select name="by" class="mbui-input mt-1">
                    <option value="">Auto</option>
                    @foreach ($groups as $g => $gLabel)
                        <option value="{{ $g }}" @selected($w['range'] === 'custom' && $w['group'] === $g)>{{ $gLabel }}</option>
                    @endforeach
                </select>
            </div>
            <x-mbui.button type="submit">Show</x-mbui.button>
        </div>
        <p class="ml-auto text-sm text-gray-500">{{ $w['label'] }}</p>
    </form>

    {{-- Totals for the period --}}
    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @foreach ($types as $t => $tLabel)
            @php $s = $totals[$t]; @endphp
            <div class="mbui-card p-5">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $typeColor[$t] }}"></span>
                    <h3 class="text-sm font-semibold text-gray-900">{{ $tLabel }}</h3>
                </div>
                <div class="mt-3 flex items-baseline gap-3">
                    <span class="text-3xl font-bold text-gray-900">{{ $fmt($s['total']) }}</span>
                    <span class="text-sm text-gray-500">total</span>
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                    <div><dt class="text-xs text-gray-400">Unique devices</dt><dd class="font-semibold text-gray-900">{{ $fmt($s['unique']) }}</dd></div>
                    <div><dt class="text-xs text-gray-400">New devices</dt><dd class="font-semibold text-gray-900">{{ $fmt($s['new']) }}</dd></div>
                    <div><dt class="text-xs text-gray-400">Returning</dt><dd class="font-semibold text-gray-900">{{ $fmt($s['returning']) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-gray-500">
                    @if ($s['unique'] > 0)
                        {{ number_format($s['total'] / $s['unique'], 1) }} per device
                        · {{ $fmt($s['users']) }} signed-in {{ \Illuminate\Support\Str::plural('user', $s['users']) }}
                    @else
                        Nothing in this period yet.
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    {{-- Chart --}}
    <div class="mbui-card mt-6 p-5" x-data="{ metric: 'page_view' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-900">Over time <span class="font-normal text-gray-500">— dark bar = total, light bar = unique devices</span></h3>
            <div class="inline-flex rounded-lg bg-gray-100 p-1 text-sm">
                @foreach ($types as $t => $tLabel)
                    <button type="button" @click="metric = '{{ $t }}'"
                        :class="metric === '{{ $t }}' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        class="rounded-md px-3 py-1 font-medium">{{ $typeShort[$t] }}</button>
                @endforeach
            </div>
        </div>

        @foreach ($types as $t => $tLabel)
            <div x-show="metric === '{{ $t }}'" @if ($t !== 'page_view') x-cloak @endif class="mt-4">
                <div class="flex h-56 items-end gap-px border-b border-gray-200">
                    @foreach ($series as $row)
                        @php
                            $total = $row['stats'][$t]['total'];
                            $uniq = $row['stats'][$t]['unique'];
                            $h = round($total / $maxBy[$t] * 100, 1);
                            $hu = round($uniq / $maxBy[$t] * 100, 1);
                        @endphp
                        <div class="group relative flex h-full flex-1 items-end justify-center"
                             title="{{ $row['label'] }}: {{ $fmt($total) }} {{ strtolower($typeShort[$t]) }}, {{ $fmt($uniq) }} unique">
                            <div class="relative w-full max-w-[28px] rounded-t {{ $typeColor[$t] }}" style="height: {{ $h }}%">
                                <div class="absolute inset-x-0 bottom-0 rounded-t {{ $typeSoft[$t] }}" style="height: {{ $h > 0 ? round($hu / $h * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-px text-[10px] text-gray-400">
                    @foreach ($series as $i => $row)
                        <div class="flex-1 overflow-visible whitespace-nowrap text-center">{{ $i % $labelEvery === 0 ? $row['label'] : '' }}</div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-400">Highest: {{ $fmt(collect($series)->max(fn ($r) => $r['stats'][$t]['total'])) }} per {{ $w['group'] }}.</p>
            </div>
        @endforeach
    </div>

    {{-- Table per minute / hour / day / month / year --}}
    <div class="mbui-card mt-6 overflow-hidden">
        <div class="border-b border-gray-200 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-900">Per {{ $w['group'] }} <span class="font-normal text-gray-500">(newest first)</span></h3>
        </div>
        <div class="max-h-[32rem] overflow-y-auto">
            <x-mbui.table>
                <thead>
                    <tr class="sticky top-0 border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">{{ ucfirst($w['group']) }}</th>
                        @foreach ($types as $t => $tLabel)
                            <th class="mbui-th text-right">{{ $typeShort[$t] }}</th>
                            <th class="mbui-th text-right">Unique</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach (array_reverse($series) as $row)
                        <tr>
                            <td class="mbui-td whitespace-nowrap">{{ $row['label'] }}</td>
                            @foreach ($types as $t => $tLabel)
                                <td class="mbui-td text-right font-semibold {{ $row['stats'][$t]['total'] ? 'text-gray-900' : 'text-gray-300' }}">{{ $fmt($row['stats'][$t]['total']) }}</td>
                                <td class="mbui-td text-right {{ $row['stats'][$t]['unique'] ? 'text-gray-600' : 'text-gray-300' }}">{{ $fmt($row['stats'][$t]['unique']) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    </div>

    {{-- Downloads and app --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-5 py-3"><h3 class="text-sm font-semibold text-gray-900">Downloads by platform</h3></div>
            <x-mbui.table>
                <thead><tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Platform</th><th class="mbui-th">From</th><th class="mbui-th text-right">Total</th><th class="mbui-th text-right">Unique</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($breakdowns['downloads'] as $r)
                        <tr>
                            <td class="mbui-td">{{ $platforms[$r['platform']] ?? ($r['platform'] ?: 'Unknown') }}</td>
                            <td class="mbui-td text-gray-500">{{ $r['source'] }}</td>
                            <td class="mbui-td text-right font-semibold text-gray-900">{{ $fmt($r['total']) }}</td>
                            <td class="mbui-td text-right">{{ $fmt($r['unique']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="mbui-td text-center text-gray-400">No downloads in this period</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </div>

        @foreach (['app_platforms' => 'App opens by platform', 'app_versions' => 'App opens by version'] as $key => $title)
            <div class="mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-3"><h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3></div>
                <x-mbui.table>
                    <thead><tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">{{ $key === 'app_versions' ? 'Version' : 'Platform' }}</th><th class="mbui-th text-right">Opens</th><th class="mbui-th text-right">Devices</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($breakdowns[$key] as $r)
                            <tr>
                                <td class="mbui-td">{{ $key === 'app_platforms' ? ($platforms[$r['name']] ?? ($r['name'] === 'Unknown' ? 'Unknown (older app)' : $r['name'])) : $r['name'] }}</td>
                                <td class="mbui-td text-right font-semibold text-gray-900">{{ $fmt($r['total']) }}</td>
                                <td class="mbui-td text-right">{{ $fmt($r['unique']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="mbui-td text-center text-gray-400">No app opens in this period</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>
        @endforeach
    </div>

    {{-- Website breakdowns --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([
            'pages' => ['Top pages', 'Page'],
            'referrers' => ['Where visitors came from', 'Website'],
            'devices' => ['Device type', 'Device'],
            'os' => ['Operating system', 'System'],
            'browsers' => ['Browser', 'Browser'],
        ] as $key => [$title, $col])
            <div class="mbui-card overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-3"><h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3></div>
                <x-mbui.table>
                    <thead><tr class="border-b border-gray-200 bg-gray-50">
                        <th class="mbui-th">{{ $col }}</th><th class="mbui-th text-right">Views</th><th class="mbui-th text-right">Unique</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($breakdowns[$key] as $r)
                            <tr>
                                <td class="mbui-td max-w-xs truncate" title="{{ $r['name'] }}">{{ $key === 'devices' ? ucfirst($r['name']) : $r['name'] }}</td>
                                <td class="mbui-td text-right font-semibold text-gray-900">{{ $fmt($r['total']) }}</td>
                                <td class="mbui-td text-right">{{ $fmt($r['unique']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="mbui-td text-center text-gray-400">{{ $key === 'referrers' ? 'No outside links in this period' : 'No views in this period' }}</td></tr>
                        @endforelse
                    </tbody>
                </x-mbui.table>
            </div>
        @endforeach
    </div>

    {{-- Same device or many? --}}
    <div class="mbui-card mt-6 overflow-hidden">
        <div class="border-b border-gray-200 px-5 py-3">
            <h3 class="text-sm font-semibold text-gray-900">Most active devices</h3>
            <p class="text-xs text-gray-500">Each row is one device (by anonymous id). Many visits from one row = the same device coming back.</p>
        </div>
        <div class="overflow-x-auto">
            <x-mbui.table>
                <thead><tr class="border-b border-gray-200 bg-gray-50">
                    <th class="mbui-th">Device id</th><th class="mbui-th">Type</th><th class="mbui-th text-right">Views</th><th class="mbui-th text-right">Downloads</th><th class="mbui-th text-right">App opens</th><th class="mbui-th">First seen</th><th class="mbui-th">Last seen</th><th class="mbui-th">Account</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($topDevices as $d)
                        <tr>
                            <td class="mbui-td font-mono text-xs">{{ $d['id'] }}</td>
                            <td class="mbui-td whitespace-nowrap text-gray-600">{{ ucfirst((string) $d['device']) }} · {{ $d['os'] }} · {{ $d['browser'] }}</td>
                            <td class="mbui-td text-right font-semibold text-gray-900">{{ $fmt($d['views']) }}</td>
                            <td class="mbui-td text-right">{{ $fmt($d['downloads']) }}</td>
                            <td class="mbui-td text-right">{{ $fmt($d['opens']) }}</td>
                            <td class="mbui-td whitespace-nowrap text-gray-500">{{ $d['first_at']->format('d M H:i') }}</td>
                            <td class="mbui-td whitespace-nowrap text-gray-500">{{ $d['last_at']->format('d M H:i') }}</td>
                            <td class="mbui-td">
                                @if ($d['user_id'])
                                    <a href="{{ route('admin.users.show', $d['user_id']) }}" class="text-indigo-600 hover:underline">#{{ $d['user_id'] }}</a>
                                @else
                                    <span class="text-gray-400">Guest</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="mbui-td text-center text-gray-400">No devices in this period</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Not counted: admin pages, signed-in admins, search-engine bots and link previews. Devices are kept as anonymous ids (no raw IP or cookie is stored).
        App opens from apps older than 1.2.4 show as "Unknown" platform and are told apart by network.
    </p>

</x-layouts.admin>
