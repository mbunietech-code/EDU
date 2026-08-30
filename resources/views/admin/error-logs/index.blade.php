<x-layouts.admin title="Error Logs" header="Error Logs">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Error Logs</h1>
            <p class="mt-1 text-sm text-gray-500">Server-side errors captured automatically. Visitors only ever see a friendly page.</p>
        </div>
        @can('error_logs.manage')
            <form method="POST" action="{{ route('admin.error-logs.clear-resolved') }}"
                  onsubmit="return confirm('Delete all resolved error logs?');">
                @csrf
                <x-mbui.button type="submit" variant="secondary">Clear resolved</x-mbui.button>
            </form>
        @endcan
    </div>

    <div class="mt-6 flex gap-2 text-sm">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.error-logs.index', ['status' => $key]) }}"
               class="rounded-full px-3 py-1 font-medium {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $label }}@if ($key === 'open') ({{ $openCount }})@endif
            </a>
        @endforeach
    </div>

    <div class="mt-4 mbui-card overflow-hidden">
        <x-mbui.table>
            <thead>
                <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="mbui-th">Error</th>
                    <th class="mbui-th">Where</th>
                    <th class="mbui-th">Count</th>
                    <th class="mbui-th">Last seen</th>
                    <th class="mbui-th">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($logs as $log)
                    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='{{ route('admin.error-logs.show', $log) }}'">
                        <td class="mbui-td">
                            <p class="font-semibold text-gray-900">{{ class_basename($log->exception) }}</p>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ \Illuminate\Support\Str::limit($log->message, 140) }}</p>
                        </td>
                        <td class="mbui-td">
                            <p class="font-mono text-xs text-gray-700">{{ \Illuminate\Support\Str::limit($log->file, 46) }}@if ($log->line):{{ $log->line }}@endif</p>
                            @if ($log->url)
                                <p class="text-xs text-gray-400">{{ $log->method }} {{ \Illuminate\Support\Str::limit(parse_url($log->url, PHP_URL_PATH) ?? $log->url, 46) }}</p>
                            @endif
                        </td>
                        <td class="mbui-td">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $log->occurrences }}</span>
                        </td>
                        <td class="mbui-td text-gray-500 text-sm">{{ $log->last_seen_at?->diffForHumans() }}</td>
                        <td class="mbui-td">
                            @if ($log->resolved_at)
                                <span class="text-emerald-700 text-sm">Resolved</span>
                            @else
                                <span class="text-red-600 text-sm font-medium">Open</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="mbui-td text-center text-gray-400 py-10">No errors logged 🎉</td></tr>
                @endforelse
            </tbody>
        </x-mbui.table>
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>

</x-layouts.admin>
