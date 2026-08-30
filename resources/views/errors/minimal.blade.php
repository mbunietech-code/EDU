{{-- Self-contained error page — no DB, no build assets, safe when things are broken. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code', 'Error') · MbunieEduHub</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
            font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            background:#0b1220;color:#e5e7eb;padding:24px}
        .card{max-width:420px;width:100%;text-align:center}
        .logo{width:56px;height:56px;border-radius:14px;margin:0 auto 20px;
            background:linear-gradient(160deg,#0B2A5B,#2C9CFF);display:flex;align-items:center;
            justify-content:center;font-weight:800;font-size:28px;color:#fff}
        .code{font-size:64px;font-weight:800;line-height:1;letter-spacing:-2px;color:#fff}
        h1{margin:14px 0 6px;font-size:18px;font-weight:600;color:#fff}
        p{margin:0 0 24px;font-size:14px;color:#9aa4b2;line-height:1.6}
        a.btn{display:inline-block;padding:10px 20px;border-radius:10px;background:#2563eb;color:#fff;
            text-decoration:none;font-size:14px;font-weight:600}
        a.btn:hover{background:#1d4ed8}
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">M</div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/') }}">Back to home</a>
    </div>
</body>
</html>
