{{-- Detailed error page — shown only to signed-in admins when "show error details" is on. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ class_basename($e) }} — MbunieEduHub (admin debug)</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#0d1117;color:#c9d1d9;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.5}
        .wrap{max-width:1000px;margin:0 auto;padding:32px 20px 80px}
        .tag{display:inline-block;background:#7f1d1d;color:#fecaca;font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;margin-bottom:16px}
        h1{margin:0 0 6px;font-size:20px;color:#fff}
        .exc{color:#8b949e;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px}
        .msg{margin:16px 0;padding:16px;background:#161b22;border-left:3px solid #f85149;border-radius:6px;color:#f0f6fc;white-space:pre-wrap;word-break:break-word}
        .loc{font-family:ui-monospace,monospace;font-size:12px;color:#8b949e;word-break:break-all}
        h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#8b949e;margin:28px 0 10px}
        pre{margin:0;background:#161b22;border:1px solid #30363d;border-radius:6px;overflow:auto;font-family:ui-monospace,monospace;font-size:12.5px}
        .code{padding:0}
        .row{display:flex;padding:0 12px;white-space:pre}
        .row.cur{background:#3d1d1d}
        .ln{color:#6e7681;min-width:44px;text-align:right;padding-right:14px;user-select:none}
        .trace{padding:14px;color:#8b949e;font-size:12px;white-space:pre-wrap;word-break:break-word;max-height:420px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        td{padding:6px 10px;border-bottom:1px solid #21262d;vertical-align:top}
        td.k{color:#8b949e;width:120px}
        a{color:#58a6ff}
        .hint{margin-top:32px;padding:12px 14px;background:#161b22;border:1px solid #30363d;border-radius:6px;color:#8b949e;font-size:12.5px}
    </style>
</head>
<body>
<div class="wrap">
    <span class="tag">ADMIN DEBUG — visitors see a friendly page</span>
    <h1>{{ class_basename($e) }}</h1>
    <div class="exc">{{ get_class($e) }}</div>

    <div class="msg">{{ $e->getMessage() ?: '(no message)' }}</div>
    <div class="loc">{{ str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()) }}:{{ $e->getLine() }}</div>

    @if (!empty($snippet))
        <h2>Code</h2>
        <pre class="code">@foreach ($snippet as $l)<div class="row {{ $l['current'] ? 'cur' : '' }}"><span class="ln">{{ $l['n'] }}</span>{{ $l['code'] === '' ? ' ' : $l['code'] }}</div>@endforeach</pre>
    @endif

    <h2>Request</h2>
    <table>
        <tr><td class="k">Method</td><td>{{ $request['method'] }}</td></tr>
        <tr><td class="k">URL</td><td style="word-break:break-all">{{ $request['url'] }}</td></tr>
        <tr><td class="k">Route</td><td>{{ $request['route'] ?? '—' }}</td></tr>
        <tr><td class="k">User</td><td>{{ auth()->user()->name }} &lt;{{ auth()->user()->email }}&gt;</td></tr>
        @if (!empty($request['input']))
            <tr><td class="k">Input</td><td><pre style="padding:10px;border:none;background:#0d1117">{{ json_encode($request['input'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></td></tr>
        @endif
    </table>

    <h2>Stack trace</h2>
    <pre class="trace">{{ $e->getTraceAsString() }}</pre>

    <div class="hint">
        This detailed view is on because <strong>Settings → Developer tools → Show error details</strong> is enabled.
        Turn it off when you are done. Every error is also recorded under
        @if (\Illuminate\Support\Facades\Route::has('admin.error-logs.index'))
            <a href="{{ route('admin.error-logs.index') }}">Admin → Error Logs</a>.
        @else
            Admin → Error Logs.
        @endif
    </div>
</div>
</body>
</html>
