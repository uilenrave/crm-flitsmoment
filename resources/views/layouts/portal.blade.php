<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Klantportaal') — {{ $booking->account->name ?? 'Flitsmoment' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #fcd34d;
            --primary-hover: #f59e0b;
            --primary-dark: #d97706;
            --green: #16a34a;
            --green-light: #dcfce7;
            --border: #e5e7eb;
            --text: #111827;
            --muted: #6b7280;
            --bg: #f8f7f5;
            --card-bg: #ffffff;
            --shadow-neu: -1px -1px 3px rgba(255,255,255,0.8), 2px 2px 5px rgba(0,0,0,0.08);
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .header { background: var(--card-bg); border-bottom: 1px solid var(--border); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; box-shadow: var(--shadow-neu); }
        .header-logo { font-size: 1.1rem; font-weight: 700; color: var(--text); }
        .header-badge { font-size: .8rem; color: var(--muted); }
        .alert { padding: .875rem 1.125rem; border-radius: .75rem; margin-bottom: 1.25rem; font-size: .9rem; }
        .alert-success { background: var(--green-light); color: #166534; border: 1px solid #bbf7d0; }
    </style>
    @stack('styles')
</head>
<body>
    <header class="header">
        <div class="header-logo">{{ $booking->account->name ?? '' }}</div>
        <div class="header-badge">Boeking {{ $booking->booking_number ?? '' }}</div>
    </header>

    @if(session('success'))
        <div style="max-width:720px;margin:1rem auto;padding:0 1.25rem;">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif

    @yield('content')
    @stack('scripts')
</body>
</html>
