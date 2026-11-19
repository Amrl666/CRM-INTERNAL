<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name', 'Prospectra') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f8fafc;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .logo { height: 56px; width: 56px; margin: 0 auto 24px; display: block; }
        .code { font-size: 48px; font-weight: 700; letter-spacing: -0.03em; color: #1d4ed8; margin: 0; }
        h1 { font-size: 20px; font-weight: 600; margin: 8px 0 0; }
        p { font-size: 14px; line-height: 1.6; color: #475569; margin: 12px 0 0; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 28px; }
        .btn {
            display: inline-block;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            color: #334155;
            background: #ffffff;
        }
        .btn-primary { background: #1d4ed8; border-color: #1d4ed8; color: #ffffff; }
        .btn:hover { opacity: 0.9; }
        .footer { margin-top: 24px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <main class="card" role="main">
        <img src="{{ asset('image/logo.png') }}" alt="{{ config('app.name', 'Prospectra') }}" class="logo">
        <p class="code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Kembali ke beranda</a>
            <a href="javascript:history.back()" class="btn">Halaman sebelumnya</a>
        </div>
        <p class="footer">&copy; {{ date('Y') }} {{ config('app.name', 'Prospectra') }}</p>
    </main>
</body>
</html>
