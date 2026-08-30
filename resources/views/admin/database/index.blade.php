<x-layouts.admin title="Database" header="Database">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Database</h1>
            <p class="mt-1 text-sm text-gray-500">Apply schema changes safely and download a full backup.</p>
        </div>
        <a href="{{ route('admin.database.backup') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download full backup (.sql)
        </a>
    </div>

    {{-- Health --------------------------------------------------------- --}}
    @php
        $badge = [
            'stable' => 'bg-emerald-100 text-emerald-800',
            'attention' => 'bg-amber-100 text-amber-800',
            'problem' => 'bg-red-100 text-red-800',
        ][$report['status']];
    @endphp
    <div class="mt-6 mbui-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">Health</h2>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $badge }}">
                {{ $report['status_label'] }}
            </span>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-2xl font-bold text-gray-900">{{ number_format($report['tables']) }}</p>
                <p class="text-sm text-gray-500">Tables</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-2xl font-bold text-gray-900">{{ number_format($report['rows']) }}</p>
                <p class="text-sm text-gray-500">Rows (approx)</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-2xl font-bold text-gray-900">{{ $report['size_mb'] }} MB</p>
                <p class="text-sm text-gray-500">Size on disk</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-2xl font-bold text-gray-900">{{ $report['database'] }}</p>
                <p class="text-sm text-gray-500">Database</p>
            </div>
        </div>

        <ul class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
            @foreach ($report['checks'] as $check)
                <li class="flex items-center justify-between py-2 text-sm">
                    <span class="flex items-center gap-2">
                        @if ($check['ok'])
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        @endif
                        {{ $check['label'] }}
                    </span>
                    <span class="text-gray-500">{{ $check['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Pending schema changes -------------------------------------- --}}
    <div class="mt-6 mbui-card p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Pending schema changes</h2>
            @if (count($pending))
                <form method="POST" action="{{ route('admin.database.apply') }}"
                      onsubmit="return confirm('Apply {{ count($pending) }} schema change(s) to the live database? This is additive and safe, but download a backup first if unsure.');">
                    @csrf
                    <x-mbui.button type="submit">Apply {{ count($pending) }} change(s)</x-mbui.button>
                </form>
            @endif
        </div>

        @if (! count($pending))
            <p class="mt-3 text-sm text-gray-500">Everything is applied — the schema is up to date.</p>
        @else
            <p class="mt-2 text-sm text-gray-500">Review each file, then click apply. Changes run once and are recorded.</p>
            <div class="mt-4 space-y-3">
                @foreach ($pending as $file)
                    <details class="rounded-lg border border-gray-200">
                        <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-gray-900">
                            {{ $file['filename'] }}
                            <span class="ml-2 text-xs font-normal text-gray-500">{{ count($file['statements']) }} statement(s)</span>
                        </summary>
                        <pre class="overflow-x-auto border-t border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-700">{{ $file['contents'] }}</pre>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    {{-- History ---------------------------------------------------- --}}
    @if ($history->isNotEmpty())
        <div class="mt-6 mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">Applied history</h2>
            </div>
            <x-mbui.table>
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="mbui-th">File</th>
                        <th class="mbui-th">Statements</th>
                        <th class="mbui-th">Result</th>
                        <th class="mbui-th">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($history as $row)
                        <tr>
                            <td class="mbui-td font-medium text-gray-900">{{ $row->filename }}</td>
                            <td class="mbui-td text-gray-500">{{ $row->statements }}</td>
                            <td class="mbui-td">
                                @if ($row->ok)
                                    <span class="text-emerald-700">Applied</span>
                                @else
                                    <span class="text-red-600" title="{{ $row->error }}">Failed</span>
                                @endif
                            </td>
                            <td class="mbui-td text-gray-500">{{ $row->applied_at ?? $row->updated_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

    {{-- Schema overview ------------------------------------------ --}}
    <div class="mt-6 mbui-card overflow-hidden">
        <div class="border-b border-gray-200 px-4 py-3">
            <h2 class="text-base font-semibold text-gray-900">Tables ({{ count($schema) }})</h2>
        </div>
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Table</th>
                    <th class="mbui-th">Columns</th>
                    <th class="mbui-th">Rows (approx)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($schema as $table)
                    <tr>
                        <td class="mbui-td font-mono text-gray-900">{{ $table['name'] }}</td>
                        <td class="mbui-td text-gray-500">{{ $table['columns'] }}</td>
                        <td class="mbui-td text-gray-500">{{ number_format($table['rows']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-mbui.table>
    </div>

</x-layouts.admin>
