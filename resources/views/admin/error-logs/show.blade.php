<x-layouts.admin :title="class_basename($errorLog->exception)" header="Error Logs">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">{{ class_basename($errorLog->exception) }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $errorLog->exception }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.error-logs.index') }}" class="mbui-anchor text-sm">Back</a>
            @can('error_logs.manage')
                @if ($errorLog->resolved_at)
                    <form method="POST" action="{{ route('admin.error-logs.reopen', $errorLog) }}">@csrf
                        <x-mbui.button type="submit" variant="secondary">Reopen</x-mbui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.error-logs.resolve', $errorLog) }}">@csrf
                        <x-mbui.button type="submit" variant="success">Mark resolved</x-mbui.button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.error-logs.destroy', $errorLog) }}"
                      onsubmit="return confirm('Delete this error log?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Message</h2>
                <p class="mt-2 text-sm text-gray-900 whitespace-pre-wrap break-words">{{ $errorLog->message }}</p>
                <p class="mt-3 font-mono text-xs text-gray-500 break-all">{{ $errorLog->location() }}</p>
            </div>

            <div class="mbui-card p-6">
                <h2 class="mbui-section-label">Stack trace</h2>
                <pre class="mt-2 max-h-[28rem] overflow-auto rounded-lg bg-gray-900 p-4 text-xs leading-relaxed text-gray-100">{{ $errorLog->trace }}</pre>
            </div>

            @if (!empty($errorLog->context['input']))
                <div class="mbui-card p-6">
                    <h2 class="mbui-section-label">Request input (sanitised)</h2>
                    <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700">{{ json_encode($errorLog->context['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="mbui-card p-6 text-sm">
                <dl class="space-y-3">
                    <div><dt class="text-gray-500">Occurrences</dt><dd class="font-semibold text-gray-900">{{ $errorLog->occurrences }}</dd></div>
                    <div><dt class="text-gray-500">First seen</dt><dd class="text-gray-900">{{ $errorLog->first_seen_at }}</dd></div>
                    <div><dt class="text-gray-500">Last seen</dt><dd class="text-gray-900">{{ $errorLog->last_seen_at }} ({{ $errorLog->last_seen_at?->diffForHumans() }})</dd></div>
                    <div><dt class="text-gray-500">URL</dt><dd class="text-gray-900 break-all">{{ $errorLog->method }} {{ $errorLog->url ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Route</dt><dd class="text-gray-900">{{ $errorLog->context['route'] ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">User</dt><dd class="text-gray-900">{{ $errorLog->user?->name ?? 'Guest' }} @if($errorLog->user) &lt;{{ $errorLog->user->email }}&gt; @endif</dd></div>
                    <div><dt class="text-gray-500">IP</dt><dd class="text-gray-900">{{ $errorLog->ip ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
                        <dd>
                            @if ($errorLog->resolved_at)
                                <span class="text-emerald-700">Resolved by {{ $errorLog->resolver?->name ?? '—' }} · {{ $errorLog->resolved_at->diffForHumans() }}</span>
                            @else
                                <span class="text-red-600 font-medium">Open</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

</x-layouts.admin>
