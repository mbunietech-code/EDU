<x-layouts.admin title="AI Optimization" header="AI Database Optimization">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">AI Database Optimization</h1>
            <p class="mt-1 text-sm text-gray-500">Read-only analysis of expired, inactive, duplicate and orphaned data. Nothing is ever changed without your explicit approval, and every change is backed up first.</p>
        </div>
        <form method="POST" action="{{ route('admin.optimization.scan') }}">
            @csrf
            <x-mbui.button type="submit" variant="primary">Run new scan</x-mbui.button>
        </form>
    </div>

    @if (! $supported)
        <div class="mt-6">
            <x-mbui.alert type="error">This feature requires a MySQL connection — the current database driver does not support it.</x-mbui.alert>
        </div>
    @elseif (! $scan)
        <div class="mt-6 mbui-card p-10 text-center">
            <p class="text-gray-500">No scan has been run yet. Click "Run new scan" to analyze the database.</p>
        </div>
    @else
        {{-- Dashboard cards ------------------------------------------------ --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-mbui.stats-card title="Database size" :value="number_format($scan->total_size_mb, 1).' MB'" :trend="number_format($scan->total_tables).' tables'" />
            <x-mbui.stats-card title="Expired data" :value="number_format($scan->expired_count)" trend="already expired" />
            <x-mbui.stats-card title="Expiring soon" :value="number_format($scan->expiring_soon_count)" trend="within 7 days" />
            <x-mbui.stats-card title="Inactive / archivable" :value="number_format($scan->inactive_count)" trend="rows across log tables" />
            <x-mbui.stats-card title="Duplicate records" :value="number_format($scan->duplicate_count)" trend="extra rows found" />
            <x-mbui.stats-card title="Index recommendations" :value="number_format($scan->missing_index_count)" trend="missing FK indexes" />
            <x-mbui.stats-card title="Estimated recovery" :value="number_format($scan->estimated_recovery_mb, 1).' MB'" trend="if all approved" />
            <x-mbui.stats-card title="Orphaned rows" :value="number_format($scan->orphaned_count)" trend="need manual review" />
        </div>

        <p class="mt-3 text-xs text-gray-400">Scan #{{ $scan->id }} · {{ $scan->created_at->diffForHumans() }} · {{ $scan->recommendations->count() }} recommendation(s)</p>

        {{-- Recommendations -------------------------------------------- --}}
        <div class="mt-6 mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">Recommendations</h2>
            </div>

            @if ($scan->recommendations->isEmpty())
                <p class="p-6 text-sm text-gray-500">No issues found — the database looks clean.</p>
            @else
                <div class="divide-y divide-gray-200">
                    @foreach ($scan->recommendations as $rec)
                        @php
                            $riskColor = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger'][$rec->risk_level];
                            $statusColor = ['pending' => 'neutral', 'approved' => 'info', 'rejected' => 'danger', 'executed' => 'success'][$rec->status];
                        @endphp
                        <details class="group">
                            <summary class="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-3 hover:bg-gray-50">
                                <x-mbui.badge appearance="{{ $riskColor }}">{{ ucfirst($rec->risk_level) }} risk</x-mbui.badge>
                                <x-mbui.badge appearance="{{ $statusColor }}">{{ ucfirst($rec->status) }}</x-mbui.badge>
                                <span class="font-mono text-sm text-gray-900">{{ $rec->table_name }}</span>
                                <span class="text-xs text-gray-400">{{ str_replace('_', ' ', $rec->category) }}</span>
                                <span class="ml-auto text-sm text-gray-500">{{ number_format($rec->affected_count) }} record(s)</span>
                            </summary>

                            <div class="border-t border-gray-100 bg-gray-50 px-4 py-4 space-y-3">
                                <p class="text-sm text-gray-700">{{ $rec->action }}</p>

                                <dl class="grid gap-2 text-xs text-gray-500 sm:grid-cols-2">
                                    @if ($rec->column_name)
                                        <div><dt class="font-medium text-gray-700">Column</dt><dd class="font-mono">{{ $rec->column_name }}</dd></div>
                                    @endif
                                    @if ($rec->expiration_status)
                                        <div><dt class="font-medium text-gray-700">Expiration status</dt><dd>{{ $rec->expiration_status }}</dd></div>
                                    @endif
                                    @if ($rec->estimated_recovery_mb)
                                        <div><dt class="font-medium text-gray-700">Estimated recovery</dt><dd>{{ number_format($rec->estimated_recovery_mb, 2) }} MB</dd></div>
                                    @endif
                                    <div><dt class="font-medium text-gray-700">Operation</dt><dd class="font-mono">{{ $rec->operation }}</dd></div>
                                </dl>

                                <div>
                                    <p class="text-xs font-medium text-gray-700">SQL preview</p>
                                    <pre class="mt-1 overflow-x-auto rounded-lg bg-gray-900 px-3 py-2 text-xs text-gray-100">{{ $rec->sql_preview }}</pre>
                                </div>

                                <div>
                                    <p class="text-xs font-medium text-gray-700">Backup / rollback strategy</p>
                                    <p class="text-xs text-gray-500">{{ $rec->rollback_note }}</p>
                                </div>

                                @if ($rec->status === 'executed')
                                    <p class="text-xs text-emerald-700">
                                        Executed {{ $rec->executed_at?->diffForHumans() }} by {{ $rec->executedBy?->name ?? 'system' }} —
                                        {{ number_format((int) $rec->executed_count) }} row(s) affected.
                                        @if ($rec->backup_path)
                                            Backup: <span class="font-mono">{{ $rec->backup_path }}</span>
                                        @endif
                                    </p>
                                @elseif ($rec->status === 'rejected')
                                    <p class="text-xs text-red-700">Rejected{{ $rec->rejection_reason ? ' — '.$rec->rejection_reason : '' }}.</p>
                                @else
                                    <div class="flex flex-wrap gap-2 pt-1">
                                        @if ($rec->status === 'pending')
                                            <form method="POST" action="{{ route('admin.optimization.approve', $rec) }}">
                                                @csrf
                                                <x-mbui.button type="submit" variant="secondary">Approve</x-mbui.button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.optimization.reject', $rec) }}"
                                                  onsubmit="return confirm('Reject this recommendation?');">
                                                @csrf
                                                <x-mbui.button type="submit" variant="ghost">Reject</x-mbui.button>
                                            </form>
                                        @elseif ($rec->status === 'approved')
                                            @if (in_array($rec->operation, ['delete', 'update', 'create_index']))
                                                <form method="POST" action="{{ route('admin.optimization.execute', $rec) }}"
                                                      onsubmit="return confirm('This will back up the affected rows and then run:\n\n{{ $rec->sql_preview }}\n\nContinue?');">
                                                    @csrf
                                                    <x-mbui.button type="submit" variant="danger">Backup &amp; Execute</x-mbui.button>
                                                </form>
                                            @else
                                                <p class="text-xs text-gray-500">Detection-only finding — no automatic execution available. Review manually.</p>
                                            @endif
                                            <form method="POST" action="{{ route('admin.optimization.reject', $rec) }}"
                                                  onsubmit="return confirm('Reject this recommendation?');">
                                                @csrf
                                                <x-mbui.button type="submit" variant="ghost">Reject</x-mbui.button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Scan history --------------------------------------------- --}}
        <div class="mt-6 mbui-card overflow-hidden">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">Audit history</h2>
            </div>
            <x-mbui.table>
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="mbui-th">Scan</th>
                        <th class="mbui-th">When</th>
                        <th class="mbui-th">Triggered by</th>
                        <th class="mbui-th">Recommendations</th>
                        <th class="mbui-th">Est. recovery</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($history as $row)
                        <tr>
                            <td class="mbui-td font-medium text-gray-900">#{{ $row->id }}</td>
                            <td class="mbui-td text-gray-500">{{ $row->created_at }}</td>
                            <td class="mbui-td text-gray-500">{{ $row->triggeredBy?->name ?? 'Scheduled' }}</td>
                            <td class="mbui-td text-gray-500">{{ $row->recommendations()->count() }}</td>
                            <td class="mbui-td text-gray-500">{{ number_format($row->estimated_recovery_mb, 1) }} MB</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-mbui.table>
        </div>
    @endif

</x-layouts.admin>
