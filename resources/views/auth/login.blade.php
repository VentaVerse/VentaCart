<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Administration</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8f9fb;
            color: #3d4251;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-shell {
            width: 100%;
            max-width: 400px;
            padding: 0 24px;
        }

        /* Brand */
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 48px;
        }
        .brand-icon {
            width: 40px; height: 40px;
            background: #1e293b;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; color: #fff;
            letter-spacing: -0.03em;
        }
        .brand-text {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: -0.02em;
        }

        /* Heading */
        .heading {
            font-size: 28px;
            font-weight: 600;
            color: #111827;
            letter-spacing: -0.04em;
            line-height: 1.15;
            margin-bottom: 8px;
        }
        .subtext {
            font-size: 15px;
            color: #9ca3af;
            line-height: 1.5;
            margin-bottom: 40px;
        }

        /* Alerts */
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }

        /* Fields */
        .field + .field { margin-top: 22px; }
        .field-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 8px;
        }
        .field-input {
            width: 100%;
            height: 48px;
            padding: 0 16px;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            color: #111827;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .field-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        .field-input::placeholder {
            color: #c5c9d2;
        }

        /* Row */
        .meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 22px;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            font-size: 14px;
            color: #6b7280;
        }
        .remember input {
            width: 16px; height: 16px;
            border-radius: 5px;
            border: 1px solid #d1d5db;
            background: #fff;
            accent-color: #6366f1;
            cursor: pointer;
        }
        .forgot {
            font-size: 14px;
            color: #6b7280;
            text-decoration: none;
            transition: color 0.15s;
        }
        .forgot:hover { color: #6366f1; }

        /* Submit */
        .submit {
            width: 100%;
            height: 48px;
            margin-top: 32px;
            background: #1e293b;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
            letter-spacing: -0.01em;
        }
        .submit:hover {
            background: #334155;
            box-shadow: 0 1px 8px rgba(30, 41, 59, 0.15);
        }
        .submit:active {
            background: #0f172a;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 36px 0;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 13px;
            color: #c5c9d2;
        }
        .footer a {
            color: #9ca3af;
            text-decoration: none;
        }
        .footer a:hover {
            color: #6b7280;
        }
    </style>
</head>
<body>

<div class="login-shell">
    <div class="brand">
        <div class="brand-text">{{ config('app.name') }}</div>
    </div>

    <h1 class="heading">Sign in</h1>
    <p class="subtext">Enter your credentials to access the administration panel.</p>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="field">
            <label for="email" class="field-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field-input" placeholder="name@company.com">
        </div>

        <div class="field">
            <label for="password" class="field-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="field-input" placeholder="Enter password">
        </div>

        <div class="meta-row">
            <label class="remember">
                <input type="checkbox" name="remember">
                <span>Remember me</span>
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="forgot">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="submit">Sign in</button>
    </form>

    <div class="divider"></div>

    <div class="footer">
        <a href="/">{{ config('app.name') }}</a> &middot; &copy; {{ date('Y') }}
    </div>
</div>

</body>
</html>
