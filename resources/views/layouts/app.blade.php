<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM') — Flitsmoment</title>
    <link rel="icon" type="image/png" href="/logo.png?v=3">
    <link rel="apple-touch-icon" href="/favicon-app.png">
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

        /* Sidebar - Icon-only (uitklapbaar) */
        .sidebar { width: 60px; background: #f8f7f5; color: #1e293b; display: flex; flex-direction: column; flex-shrink: 0; border-right: 1px solid rgba(0,0,0,.05); transition: width .25s ease; }
        .sidebar.expanded { width: 200px; }
        /* Toggle-knop */
        .sidebar-toggle { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; margin: .25rem auto .15rem; padding: 0; background: #fff; border: none; border-radius: .6rem; color: #64748b; cursor: pointer; box-shadow: -1px -1px 2px rgba(255,255,255,.6), 1px 1px 2px rgba(0,0,0,.06); transition: all .2s ease; }
        .sidebar-toggle:hover { color: #1e293b; box-shadow: -1px -1px 2px rgba(255,255,255,.6), 2px 2px 4px rgba(0,0,0,.1); }
        .sidebar-toggle svg { width: 1rem; height: 1rem; transition: transform .25s ease; }
        .sidebar.expanded .sidebar-toggle svg { transform: rotate(180deg); }
        /* Expanded states */
        .sidebar.expanded .nav-item { justify-content: flex-start; padding: .55rem .75rem; gap: .65rem; }
        .sidebar.expanded .nav-label { display: inline; font-size: .82rem; font-weight: 500; line-height: 1.2; }
        .sidebar.expanded .nav-item::after, .sidebar.expanded .nav-item::before,
        .sidebar.expanded .btn-logout::after, .sidebar.expanded .btn-logout::before { display: none; }
        .sidebar.expanded .btn-logout { justify-content: flex-start; gap: .65rem; padding: .6rem .75rem; }
        .sidebar.expanded .btn-logout::after-text { display: none; }
        .sidebar-logo { padding: .35rem 0 .65rem; display: flex; align-items: center; justify-content: center; }
        .logo-img { height: 48px; width: auto; display: block; border-radius: 22%; }
        .sidebar-nav { flex: 1; padding: .25rem .5rem; }
        .nav-item { display: flex; align-items: center; justify-content: center; padding: .65rem; font-size: .875rem; color: #64748b; text-decoration: none; transition: all .2s ease; border-radius: 0.75rem; margin-bottom: 0.2rem; position: relative; }
        .nav-item:hover { color: #1e293b; background: rgba(252,211,77,.08); box-shadow: -1px -1px 2px rgba(255,255,255,.6), 1px 1px 2px rgba(0,0,0,.05); }
        .nav-item.active { background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 100%); color: white; box-shadow: -1px -1px 3px rgba(255,255,255,.3), 2px 2px 5px rgba(252,211,77,.3); }
        .nav-item svg { width: 1.2rem; height: 1.2rem; flex-shrink: 0; }
        .nav-label { display: none; }
        /* Tooltip */
        .nav-item::after { content: attr(data-tooltip); position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); background: #1e293b; color: #fff; padding: .3rem .65rem; border-radius: .5rem; font-size: .75rem; font-weight: 500; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 9999; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
        .nav-item::before { content: ''; position: absolute; left: calc(100% + 4px); top: 50%; transform: translateY(-50%); border: 5px solid transparent; border-right-color: #1e293b; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 9999; }
        .nav-item:hover::after, .nav-item:hover::before { opacity: 1; }
        /* Nav section = dunne scheidingslijn */
        .nav-section { height: 1px; background: rgba(0,0,0,.06); margin: .5rem .25rem; padding: 0; font-size: 0; overflow: hidden; }
        /* Footer */
        .sidebar-footer { padding: .75rem .5rem; border-top: 1px solid rgba(0,0,0,.05); margin-top: auto; display: flex; flex-direction: column; align-items: center; gap: .5rem; }
        .sidebar-user { display: none; }
        .btn-logout { display: flex; align-items: center; justify-content: center; padding: .6rem; background: white; color: #64748b; border-radius: .75rem; font-size: .8rem; border: none; cursor: pointer; width: 100%; box-shadow: -1px -1px 2px rgba(255,255,255,.6), 1px 1px 2px rgba(0,0,0,.05); transition: all .2s ease; position: relative; }
        .btn-logout:hover { color: #dc2626; box-shadow: -1px -1px 2px rgba(255,255,255,.6), 2px 2px 4px rgba(220,38,38,.15); }
        .btn-logout::after { content: 'Uitloggen'; position: absolute; left: calc(100% + 10px); top: 50%; transform: translateY(-50%); background: #1e293b; color: #fff; padding: .3rem .65rem; border-radius: .5rem; font-size: .75rem; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 9999; }
        .btn-logout::before { content: ''; position: absolute; left: calc(100% + 4px); top: 50%; transform: translateY(-50%); border: 5px solid transparent; border-right-color: #1e293b; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 9999; }
        .btn-logout:hover::after, .btn-logout:hover::before { opacity: 1; }

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

        /* ── Mobile responsive ──────────────────────────────────────── */
        @media (max-width: 768px) {
            /* Sidebar → fixed bottom nav */
            .sidebar {
                position: fixed; bottom: 0; left: 0; right: 0;
                width: 100%; height: 56px;
                flex-direction: row;
                border-right: none;
                border-top: 1px solid rgba(0,0,0,.08);
                box-shadow: 0 -2px 8px rgba(0,0,0,.07);
                z-index: 1000;
            }
            .sidebar-logo, .sidebar-footer, .sidebar-toggle { display: none; }
            .nav-section { display: none; }
            /* Neutraliseer desktop "expanded"-stand volledig op mobiel (hogere specificiteit dan desktop-rules) */
            .sidebar.expanded { width: 100%; }
            .sidebar-nav {
                display: flex; flex: 1; padding: 0;
                flex-direction: row; align-items: stretch;
                overflow-x: hidden;
                scrollbar-width: none;
            }
            .sidebar-nav::-webkit-scrollbar { display: none; }
            .nav-item,
            .sidebar.expanded .nav-item {
                display: flex; flex: 1; flex-direction: column;
                align-items: center; justify-content: center;
                padding: .3rem .2rem .2rem; margin: 0; border-radius: 0;
                min-width: 0; gap: .1rem;
            }
            /* Only show primary nav items in bottom bar */
            .nav-item.mobile-hidden,
            .sidebar.expanded .nav-item.mobile-hidden { display: none; }
            .nav-item.active { border-radius: 0; border-top: 2px solid var(--primary-dark); background: rgba(252,211,77,.12); color: var(--primary-dark); box-shadow: none; }
            .nav-item svg { width: 1.1rem; height: 1.1rem; }
            .nav-label,
            .sidebar.expanded .nav-label {
                display: block; font-size: .58rem; font-weight: 500;
                line-height: 1; text-align: center; margin: 0;
                overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;
            }
            .nav-item::after, .nav-item::before,
            .btn-logout::after, .btn-logout::before { display: none !important; }

            /* Meer button in topbar */
            .btn-meer { display: flex; }

            /* Layout */
            .main { width: 100%; }
            .topbar { padding: .75rem 1rem; }
            .content { padding: 1rem .75rem 80px; }

            /* Grids */
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
            .form-grid-3 { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 480px) {
            .topbar h2 { font-size: .95rem; }
            .content { padding: .75rem .6rem 80px; }
        }

        /* Analytics-only nav item: hidden on desktop, shown only in mobile bottom nav */
        .mobile-analytics-only { display: none; }
        @media (max-width: 768px) {
            .mobile-analytics-only { display: flex; }
        }

        /* Meer button — hidden on desktop */
        .btn-meer {
            display: none;
            align-items: center; justify-content: center;
            width: 36px; height: 36px;
            background: white;
            border: none; border-radius: .75rem; cursor: pointer;
            color: #64748b;
            box-shadow: -1px -1px 2px rgba(255,255,255,.8), 1px 1px 3px rgba(0,0,0,.08);
        }
        .btn-meer:hover { color: #1e293b; }

        /* Meer overlay */
        .meer-overlay {
            display: none; position: fixed; inset: 0; z-index: 1100;
        }
        .meer-overlay.open { display: block; }
        .meer-backdrop {
            position: absolute; inset: 0; background: rgba(0,0,0,.25);
        }
        .meer-panel {
            position: absolute; top: 52px; right: 12px;
            background: white; border-radius: 1rem;
            box-shadow: 0 8px 24px rgba(0,0,0,.15);
            padding: .5rem; min-width: 180px;
            display: flex; flex-direction: column; gap: .15rem;
        }
        .meer-item {
            display: flex; align-items: center; gap: .65rem;
            padding: .6rem .85rem; border-radius: .65rem;
            font-size: .875rem; color: #374151; text-decoration: none;
            font-weight: 500;
        }
        .meer-item:hover { background: rgba(252,211,77,.12); color: #1e293b; }
        .meer-item.active { background: rgba(252,211,77,.15); color: var(--primary-dark); }
        .meer-item svg { width: 1rem; height: 1rem; flex-shrink: 0; color: #64748b; }
        .meer-item.active svg { color: var(--primary-dark); }
        .meer-divider { height: 1px; background: #f1f5f9; margin: .2rem 0; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="/logo.png?v=3" alt="Flitsmoment" class="logo-img">
    </div>
    <button type="button" id="sidebar-toggle" class="sidebar-toggle mobile-hidden" aria-label="Zijbalk uitklappen">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <nav class="sidebar-nav">
        <span class="nav-section">Overzicht</span>
        <a href="{{ route('dashboard') }}" class="nav-item mobile-hidden {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tooltip="Analytics">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span class="nav-label">Analytics</span>
        </a>
        <span class="nav-section">Verkoop</span>
        <a href="{{ route('leads.index') }}" class="nav-item {{ request()->routeIs('leads.*') && ! request()->routeIs('leads.call-list') ? 'active' : '' }}" data-tooltip="Leads">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="nav-label">Leads</span>
        </a>
        <a href="{{ route('leads.call-list') }}" class="nav-item {{ request()->routeIs('leads.call-list') ? 'active' : '' }}" data-tooltip="Bellijst">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span class="nav-label">Bellijst</span>
        </a>
        <a href="{{ route('bookings.index') }}" class="nav-item {{ request()->routeIs('bookings.index') || request()->routeIs('bookings.create') || request()->routeIs('bookings.show') || request()->routeIs('bookings.edit') || request()->routeIs('bookings.calendar') || request()->routeIs('bookings.follow-up') ? 'active' : '' }}" data-tooltip="Boekingen">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="nav-label">Boekingen</span>
        </a>
        <a href="{{ route('bookings.planning') }}" class="nav-item {{ request()->routeIs('bookings.planning') ? 'active' : '' }}" data-tooltip="Planning" style="{{ request()->routeIs('bookings.planning') ? '' : 'color:#7c3aed;' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
            <span class="nav-label">Planning</span>
        </a>
        {{-- Analytics item — only in bottom nav on mobile --}}
        <a href="{{ route('dashboard') }}" class="nav-item mobile-analytics-only {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tooltip="Analytics">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <span class="nav-label">Analytics</span>
        </a>
        <a href="{{ route('tasks.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('tasks.*') ? 'active' : '' }}" data-tooltip="Taken">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span class="nav-label">Taken</span>
        </a>
        <span class="nav-section">Beheer</span>
        <a href="{{ route('bookings.staff-planning') }}" class="nav-item mobile-hidden {{ request()->routeIs('bookings.staff-planning') || request()->routeIs('staff.*') ? 'active' : '' }}" data-tooltip="Inplannen">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="nav-label">Team</span>
        </a>
        <a href="{{ route('briefings.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('briefings.*') ? 'active' : '' }}" data-tooltip="Briefings">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span class="nav-label">Briefings</span>
        </a>
        <a href="{{ route('staff-hours.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('staff-hours.*') ? 'active' : '' }}" data-tooltip="Uren">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="nav-label">Uren</span>
        </a>
        <a href="{{ route('assets.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('assets.*') ? 'active' : '' }}" data-tooltip="Producten">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            <span class="nav-label">Producten</span>
        </a>
        <a href="{{ route('strip-templates.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('strip-templates.*') ? 'active' : '' }}" data-tooltip="Strip templates">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="nav-label">Strip templates</span>
        </a>
        <a href="{{ route('canva-templates.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('canva-templates.*') ? 'active' : '' }}" data-tooltip="Canva templates">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            <span class="nav-label">Canva templates</span>
        </a>
        <a href="{{ route('mail-templates.index') }}" class="nav-item mobile-hidden {{ request()->routeIs('mail-templates.*') ? 'active' : '' }}" data-tooltip="Mailtemplates">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span class="nav-label">Templates</span>
        </a>
        @if(auth()->user()->isAdmin())
        <span class="nav-section">Admin</span>
        <a href="{{ route('admin.users') }}" class="nav-item mobile-hidden {{ request()->routeIs('admin.users*') ? 'active' : '' }}" data-tooltip="Gebruikers">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <span class="nav-label">Gebruikers</span>
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-item mobile-hidden {{ request()->routeIs('admin.settings*') ? 'active' : '' }}" data-tooltip="Instellingen">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="nav-label">Instellingen</span>
        </a>
        @endif
    </nav>
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout" title="Uitloggen">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.1rem;height:1.1rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </button>
        </form>
    </div>
</aside>

{{-- "Meer" overlay for mobile secondary nav items --}}
<div class="meer-overlay" id="meerOverlay">
    <div class="meer-backdrop" id="meerBackdrop"></div>
    <div class="meer-panel">
        <a href="{{ route('tasks.index') }}" class="meer-item {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            Taken
        </a>
        <a href="{{ route('bookings.staff-planning') }}" class="meer-item {{ request()->routeIs('bookings.staff-planning') || request()->routeIs('staff.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Inplannen
        </a>
        <a href="{{ route('staff-hours.index') }}" class="meer-item {{ request()->routeIs('staff-hours.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Uren
        </a>
        <a href="{{ route('assets.index') }}" class="meer-item {{ request()->routeIs('assets.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
            Producten
        </a>
        <a href="{{ route('mail-templates.index') }}" class="meer-item {{ request()->routeIs('mail-templates.*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Mailtemplates
        </a>
        @if(auth()->user()->isAdmin())
        <div class="meer-divider"></div>
        <a href="{{ route('admin.users') }}" class="meer-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Gebruikers
        </a>
        <a href="{{ route('admin.settings') }}" class="meer-item {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Instellingen
        </a>
        @endif
        <div class="meer-divider"></div>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="meer-item" style="width:100%;border:none;background:none;cursor:pointer;text-align:left;">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Uitloggen
            </button>
        </form>
    </div>
</div>

<main class="main">
    <div class="topbar">
        <h2>@yield('title', 'Dashboard')</h2>
        <div style="display:flex;align-items:center;gap:.5rem;">
            @yield('actions')
            <button class="btn-meer" id="btnMeer" title="Meer">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:1.2rem;height:1.2rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
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

{{-- ── Global confirm modal (vervangt native confirm() die niet werkt in WKWebView) ── --}}
<div id="crm-confirm-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:99999;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:.875rem;padding:1.5rem;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <p id="crm-confirm-msg" style="margin:0 0 1.25rem;font-size:.95rem;color:#1e293b;line-height:1.55;"></p>
        <div style="display:flex;gap:.625rem;justify-content:flex-end;">
            <button id="crm-confirm-cancel" style="padding:.5rem 1.1rem;border:1px solid #e2e8f0;border-radius:.5rem;background:#fff;color:#64748b;font-size:.875rem;cursor:pointer;font-weight:500;">Annuleer</button>
            <button id="crm-confirm-ok" style="padding:.5rem 1.1rem;border:none;border-radius:.5rem;background:#dc2626;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">Doorgaan</button>
        </div>
    </div>
</div>
<script>
(function(){
    var overlay  = document.getElementById('crm-confirm-overlay');
    var msgEl    = document.getElementById('crm-confirm-msg');
    var btnOk    = document.getElementById('crm-confirm-ok');
    var btnCancel= document.getElementById('crm-confirm-cancel');
    var _cb = null;

    window.crmConfirm = function(msg, onConfirm) {
        msgEl.textContent = msg;
        _cb = onConfirm;
        overlay.style.display = 'flex';
        btnOk.focus();
    };

    btnOk.addEventListener('click', function() {
        overlay.style.display = 'none';
        if (_cb) { var cb = _cb; _cb = null; cb(); }
    });
    btnCancel.addEventListener('click', function() {
        overlay.style.display = 'none'; _cb = null;
    });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) { overlay.style.display = 'none'; _cb = null; }
    });
    document.addEventListener('keydown', function(e) {
        if (overlay.style.display !== 'flex') return;
        if (e.key === 'Escape') { overlay.style.display = 'none'; _cb = null; }
        if (e.key === 'Enter')  { btnOk.click(); }
    });

    // Formulieren met data-confirm attribuut automatisch onderscheppen
    document.addEventListener('submit', function(e) {
        var msg = e.target.getAttribute('data-confirm');
        if (!msg) return;
        if (e.target._crmConfirmed) { e.target._crmConfirmed = false; return; }
        e.preventDefault();
        crmConfirm(msg, function() {
            e.target._crmConfirmed = true;
            e.target.submit();
        });
    });
})();
</script>

@stack('scripts')
@stack('modals')

<script>
// Sidebar uitklap-toggle met localStorage-persistentie
(function() {
    var sb = document.querySelector('.sidebar');
    var btn = document.getElementById('sidebar-toggle');
    if (!sb || !btn) return;
    if (localStorage.getItem('crm-sidebar-expanded') === '1') sb.classList.add('expanded');
    btn.addEventListener('click', function() {
        sb.classList.toggle('expanded');
        localStorage.setItem('crm-sidebar-expanded', sb.classList.contains('expanded') ? '1' : '0');
        btn.setAttribute('aria-label', sb.classList.contains('expanded') ? 'Zijbalk inklappen' : 'Zijbalk uitklappen');
    });
})();
</script>
<script>
(function(){
    var btn = document.getElementById('btnMeer');
    var overlay = document.getElementById('meerOverlay');
    var backdrop = document.getElementById('meerBackdrop');
    if (!btn || !overlay) return;
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        overlay.classList.toggle('open');
    });
    backdrop.addEventListener('click', function(){
        overlay.classList.remove('open');
    });
})();
</script>
</body>
</html>
