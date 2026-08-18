<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MbunieEduHub</title>
    <style>
        :root {
            --bg: #e2e2e5;
            --card: #fff;
            --panel: #474a59;
            --text: #f1f1f2;
            --text-muted: #c2c2c5;
            --accent: #ff0000;
            --highlight: #ff00ff;
            --input-bg: transparent;
            --input-border: #c2c2c5;
            --submit-color: #707075;
            --error: #ff6b6b;
            --success: #6bffb0;
            --border-radius: 8px;
            --font: 'Inter UI', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
        }
        .page {
            background: var(--bg);
            display: flex;
            flex-direction: column;
            height: calc(100% - 40px);
            position: absolute;
            place-content: center;
            width: calc(100% - 40px);
        }
        @media (max-width: 767px) {
            .page { height: auto; margin-bottom: 20px; padding-bottom: 20px; }
        }
        .container {
            display: flex;
            height: 340px;
            margin: 0 auto;
            width: 640px;
        }
        @media (max-width: 767px) {
            .container { flex-direction: column; height: 640px; width: 320px; }
        }
        .left {
            background: var(--card);
            height: calc(100% - 40px);
            top: 20px;
            position: relative;
            width: 50%;
        }
        @media (max-width: 767px) {
            .left {
                height: 100%;
                left: 20px;
                width: calc(100% - 40px);
                max-height: 270px;
            }
        }
        .home-link {
            position: absolute;
            top: 20px;
            left: 40px;
            font-size: 13px;
            color: #999;
            text-decoration: none;
            transition: color 200ms;
        }
        .home-link:hover { color: var(--highlight); }
        .login {
            font-size: 38px;
            font-weight: 900;
            margin: 60px 40px 20px;
            line-height: 1.15;
        }
        .eula {
            color: #999;
            font-size: 14px;
            line-height: 1.5;
            margin: 0 40px 40px;
        }
        .right {
            background: var(--panel);
            box-shadow: 0px 0px 40px 16px rgba(0,0,0,0.22);
            color: var(--text);
            position: relative;
            width: 50%;
        }
        @media (max-width: 767px) {
            .right {
                flex-shrink: 0;
                height: 100%;
                width: 100%;
                max-height: 370px;
            }
        }
        .form {
            margin: 40px;
            position: absolute;
            width: calc(100% - 80px);
        }
        .status {
            color: var(--success);
            font-size: 13px;
            margin-bottom: 16px;
        }
        label {
            color: var(--text-muted);
            display: block;
            font-size: 14px;
            height: 16px;
            margin-top: 20px;
            margin-bottom: 5px;
        }
        input {
            background: var(--input-bg);
            border: 0;
            border-bottom: 1px solid var(--input-border);
            color: var(--text);
            font-size: 20px;
            height: 30px;
            line-height: 30px;
            outline: none !important;
            width: 100%;
            transition: border-color 200ms;
        }
        input:focus { border-bottom-color: var(--highlight); }
        input::-moz-focus-inner { border: 0; }
        .field-error {
            color: var(--error);
            font-size: 12px;
            margin-top: 4px;
        }
        #submit {
            color: var(--submit-color);
            margin-top: 40px;
            transition: color 300ms;
            cursor: pointer;
            background: transparent;
            border: none;
            font-size: 20px;
        }
        #submit:hover, #submit:focus { color: var(--text); }
        #submit:active { color: #d0d0d2; }
        .register-link {
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
        }
        .register-link a {
            color: var(--highlight);
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover { text-decoration: underline; }
        svg {
            position: absolute;
            width: 320px;
        }
        path {
            fill: none;
            stroke: var(--highlight);
            stroke-width: 4;
            stroke-dasharray: 240 1386;
            transition: stroke-dasharray 0.3s;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="container">
        <div class="left">
            <a href="{{ route('public.home') }}" class="home-link">&larr; Home</a>
            <div class="login">Reset Password</div>
            <div class="eula">Forgot your password? No problem. Just let us know your email address and we will email you a password reset link.</div>
        </div>
        <div class="right">
            <svg viewBox="0 0 320 300">
                <path d="m 40,120.00016 239.99984,-3.2e-4 c 0,0 24.99263,0.79932 25.00016,35.00016 0.008,34.20084 -25.00016,35 -25.00016,35 h -239.99984 c 0,-0.0205 -25,4.01348 -25,38.5 0,34.48652 25,38.5 25,38.5 h 215 c 0,0 20,-0.99604 20,-25 0,-24.00396 -20,-25 -20,-25 h -190 c 0,0 -20,1.71033 -20,25 0,24.00396 20,25 20,25 h 168.57143" />
            </svg>
            <form method="POST" action="{{ route('password.email') }}" class="form">
                @csrf

                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif

                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Email address">
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror

                <button type="submit" id="submit">Email Reset Link</button>

                <div class="register-link">
                    Remembered it? <a href="{{ route('login') }}">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>