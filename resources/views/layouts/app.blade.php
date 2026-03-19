<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM') — Flitsmoment</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #fcd34d;
            --primary-dark: #f59e0b;
            --sidebar-bg: #1e293b;
            --sidebar-text: #cbd5e1;
            --sidebar-active: #f59e0b;
            --bg: #f1f5f9;
            --white: #fff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #d97706;
        }

        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f7f5; color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar - Neumorphic Light Design */
        .sidebar { width: 240px; background: #f8f7f5; color: #1e293b; display: flex; flex-direction: column; flex-shrink: 0; border-right: 1px solid rgba(0, 0, 0, 0.04); }
        .sidebar-logo { padding: 1.5rem 1.25rem; border-bottom: none; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: flex-start; }
        .logo-img { max-width: 100%; height: auto; max-height: 23px; display: block; }
        .sidebar-logo h1 { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .sidebar-logo span { font-size: .75rem; color: #64748b; }
        .sidebar-nav { flex: 1; padding: .5rem 0.75rem; }
        .nav-item { display: flex; align-items: center; gap: .75rem; padding: .625rem 1rem; font-size: .875rem; color: #64748b; text-decoration: none; transition: all .2s ease; border-radius: 0.75rem; margin-bottom: 0.25rem; position: relative; }
        .nav-item:hover { color: #1e293b; background: rgba(252, 211, 77, 0.05); box-shadow: -1px -1px 2px rgba(255, 255, 255, 0.6), 1px 1px 2px rgba(0, 0, 0, 0.05); }
        .nav-item.active { background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%); color: white; font-weight: 500; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.3), 2px 2px 5px rgba(252, 211, 77, 0.3); }
        .nav-item svg { width: 1.125rem; height: 1.125rem; flex-shrink: 0; }
        .nav-section { padding: .75rem 1.25rem .5rem; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-top: 0.75rem; }
        .sidebar-footer { padding: 1rem 1.25rem; border-top: 1px solid rgba(0, 0, 0, 0.04); margin-top: auto; }
        .sidebar-user { font-size: .8rem; color: #64748b; margin-bottom: .5rem; }
        .sidebar-user strong { display: block; color: #1e293b; }
        .btn-logout { display: block; text-align: center; padding: .5rem; background: white; color: #64748b; text-decoration: none; border-radius: .75rem; font-size: .8rem; border: none; cursor: pointer; width: 100%; box-shadow: -1px -1px 2px rgba(255, 255, 255, 0.6), 1px 1px 2px rgba(0, 0, 0, 0.05); transition: all .2s ease; }
        .btn-logout:hover { color: #dc2626; box-shadow: -1px -1px 2px rgba(255, 255, 255, 0.6), 2px 2px 4px rgba(220, 38, 38, 0.15); }

        /* Main */
        .main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .topbar { background: white; border: none; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.8), 1px 1px 3px rgba(0, 0, 0, 0.08); }
        .topbar h2 { font-size: 1.1rem; font-weight: 600; }
        .content { padding: 1.5rem; flex: 1; overflow-y: auto; }

        /* Cards */
        .card { background: white; border: none; border-radius: 1rem; padding: 1.25rem; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.8), 2px 2px 5px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; }
        .card:hover { box-shadow: -2px -2px 5px rgba(255, 255, 255, 0.8), 3px 3px 8px rgba(0, 0, 0, 0.12); }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: .75rem; border: none; }
        .card-title { font-size: 1rem; font-weight: 600; }

        /* Alerts */
        .alert { padding: .75rem 1rem; border-radius: .75rem; margin-bottom: 1rem; font-size: .875rem; border: none; box-shadow: -2px -2px 5px rgba(255, 255, 255, 0.6), 2px 2px 5px rgba(0, 0, 0, 0.1); }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; border-radius: .75rem; font-size: .875rem; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: all .3s ease; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.3), 2px 2px 5px rgba(252, 211, 77, 0.3), 0 4px 8px rgba(252, 211, 77, 0.15); }
        .btn-primary:hover { box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.3), 3px 3px 7px rgba(252, 211, 77, 0.4), 0 6px 12px rgba(252, 211, 77, 0.25); }
        .btn-secondary { background: white; color: var(--text); box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.8), 2px 2px 5px rgba(0, 0, 0, 0.08); }
        .btn-secondary:hover { box-shadow: -2px -2px 5px rgba(255, 255, 255, 0.8), 3px 3px 8px rgba(0, 0, 0, 0.12); }
        .btn-danger { background: var(--danger); color: #fff; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.3), 2px 2px 5px rgba(220, 38, 38, 0.3), 0 4px 8px rgba(220, 38, 38, 0.15); }
        .btn-danger:hover { box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.3), 3px 3px 7px rgba(220, 38, 38, 0.4), 0 6px 12px rgba(220, 38, 38, 0.25); }
        .btn-sm { padding: .3rem .65rem; font-size: .8rem; }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .875rem; table-layout: fixed; }
        th { text-align: left; padding: .625rem .75rem; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); background: #f8f7f5; border: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        td { padding: .625rem .75rem; border-bottom: none; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        td.wrap { white-space: normal; }
        tr:hover td { background: white; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: .8rem; font-weight: 500; margin-bottom: .3rem; color: var(--text-muted); }
        input[type=text], input[type=email], input[type=tel], input[type=date], input[type=number], select, textarea {
            width: 100%; padding: .5rem .75rem; border: 1px solid #e5e5e5; border-radius: .75rem;
            font-size: .875rem; background: #fafafa; color: var(--text); outline: none;
            transition: all .2s ease;
        }
        input:focus, select:focus, textarea:focus {
            background: white;
            border-color: #fcd34d;
        }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        .error-msg { font-size: .75rem; color: var(--danger); margin-top: .25rem; }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; padding: .2rem .55rem; border-radius: 9999px; font-size: .75rem; font-weight: 500; }

        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: white; border: none; border-radius: 1rem; padding: 1.25rem; box-shadow: -1px -1px 3px rgba(255, 255, 255, 0.8), 2px 2px 5px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; }
        .stat-card:hover { box-shadow: -2px -2px 5px rgba(255, 255, 255, 0.8), 3px 3px 8px rgba(0, 0, 0, 0.12); }
        .stat-label { font-size: .75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
        .stat-value { font-size: 1.875rem; font-weight: 700; margin-top: .25rem; }
        .stat-sub { font-size: .75rem; color: var(--text-muted); margin-top: .125rem; }

        /* Pagination */
        .pagination { display: flex; gap: .25rem; margin-top: 1rem; }
        .pagination a, .pagination span { padding: .35rem .65rem; border: 1px solid var(--border); border-radius: .25rem; font-size: .8rem; text-decoration: none; color: var(--text); }
        .pagination .active span { background: var(--primary); color: #fff; border-color: var(--primary); }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
        <img src="/logo.png" alt="Flitsmoment Logo" class="logo-img">
        @php $vestiging = str_replace('Flitsmoment', '', auth()->user()->account->name); @endphp
        @if(trim($vestiging))
        <span style="font-size:.7rem;color:#475569;letter-spacing:.05em;text-transform:lowercase;">{{ trim($vestiging) }}</span>
        @endif
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section">Overzicht</span>
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>
        <span class="nav-section">Verkoop</span>
        <a href="{{ route('leads.index') }}" class="nav-item {{ request()->routeIs('leads.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Leads
        </a>
        <a href="{{ route('bookings.index') }}" class="nav-item {{ request()->routeIs('bookings.index') || request()->routeIs('bookings.create') || request()->routeIs('bookings.show') || request()->routeIs('bookings.edit') || request()->routeIs('bookings.calendar') || request()->routeIs('bookings.follow-up') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Boekingen
        </a>
        <a href="{{ route('bookings.planning') }}" class="nav-item {{ request()->routeIs('bookings.planning') ? 'active' : '' }}" style="{{ request()->routeIs('bookings.planning') ? '' : 'color:#7c3aed;' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            Planning
        </a>
        <span class="nav-section">Beheer</span>
        <a href="{{ route('assets.index') }}" class="nav-item {{ request()->routeIs('assets.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            Producten
        </a>
        <a href="{{ route('mail-templates.index') }}" class="nav-item {{ request()->routeIs('mail-templates.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Mailtemplates
        </a>
        @if(auth()->user()->isAdmin())
        <span class="nav-section">Admin</span>
        <a href="{{ route('admin.users') }}" class="nav-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Gebruikers
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Instellingen
        </a>
        @endif
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <strong>{{ auth()->user()->full_name }}</strong>
            {{ auth()->user()->account->name }}
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">Uitloggen</button>
        </form>
    </div>
</aside>

<main class="main">
    <div class="topbar">
        <h2>@yield('title', 'Dashboard')</h2>
        <div>@yield('actions')</div>
    </div>
    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if(session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @yield('content')
    </div>
</main>

@stack('scripts')
@stack('modals')
</body>
</html>
