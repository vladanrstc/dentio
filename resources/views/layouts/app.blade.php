<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dentio' }}</title>
    <style>
        :root {
            --bg: #f3f5f9;
            --bg-soft: #eef3f7;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dde3ec;
            --border-strong: #c8d2df;
            --accent: #1e7f8f;
            --accent-dark: #15606c;
            --accent-soft: #e1f2f5;
            --warn: #92400e;
            --danger: #991b1b;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
        }

        * { box-sizing: border-box; }

        html {
            min-width: 320px;
        }

        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 15px;
            line-height: 1.5;
        }

        a { color: inherit; text-decoration: none; }

        a:hover {
            color: var(--accent-dark);
        }

        .container {
            width: min(1200px, 94vw);
            margin: 0 auto;
        }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
        }

        .brand {
            min-width: 150px;
        }

        .brand-title {
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-subtitle {
            font-size: 0.9rem;
        }

        .nav {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
            flex: 1;
        }

        .nav a {
            padding: 8px 11px;
            border-radius: 6px;
            color: #374151;
            font-weight: 600;
            line-height: 1.2;
        }

        .nav a:hover,
        .nav a.active {
            background: var(--accent-soft);
            color: #0f4f58;
        }

        .page {
            padding: 24px 0 40px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .page-header h1,
        .page-header h2,
        .page-header h3 {
            margin: 0;
        }

        .stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .section {
            margin-top: 14px;
        }

        .section:first-child {
            margin-top: 0;
        }

        .grid {
            display: grid;
            gap: 14px;
        }

        .grid.cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .grid.cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        .panel h2, .panel h3 {
            margin-top: 0;
            margin-bottom: 12px;
            line-height: 1.25;
        }

        .panel > :last-child {
            margin-bottom: 0;
        }

        .muted { color: var(--muted); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: 0;
            background: var(--accent);
            color: #fff;
            padding: 9px 12px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            min-height: 38px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .btn:hover {
            background: var(--accent-dark);
            color: #fff;
        }

        .btn:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        .nav a:focus-visible {
            outline: 3px solid rgba(30, 127, 143, 0.22);
            outline-offset: 2px;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-danger {
            background: #b91c1c;
        }

        .btn-danger:hover {
            background: #991b1b;
        }

        .form-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field span {
            color: #374151;
            font-weight: 600;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #cfd6e2;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            font: inherit;
            min-height: 38px;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(30, 127, 143, 0.12);
            outline: 0;
        }

        textarea { min-height: 110px; resize: vertical; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 0.95rem;
        }

        th {
            color: #4b5563;
            font-size: 0.82rem;
            letter-spacing: 0;
            text-transform: uppercase;
            background: var(--surface-muted);
        }

        tbody tr:hover td {
            background: #fbfdff;
        }

        .tag {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .tag.warn {
            background: #ffedd5;
            border-color: #fdba74;
            color: var(--warn);
        }

        .tag.danger {
            background: #fee2e2;
            border-color: #fca5a5;
            color: var(--danger);
        }

        .flash {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            border: 1px solid transparent;
        }

        .flash.ok {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .flash.err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions input,
        .actions select {
            width: auto;
            min-width: 220px;
        }

        .metric {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .auth-card {
            max-width: 480px;
            margin: 40px auto;
        }

        .wide-card {
            max-width: 900px;
        }

        .invite-card {
            max-width: 720px;
            margin: 20px auto;
        }

        @media (max-width: 900px) {
            .grid.cols-2,
            .grid.cols-3,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .nav {
                width: 100%;
            }

            .panel {
                padding: 14px;
            }
        }

        @media (max-width: 640px) {
            body {
                font-size: 14px;
            }

            .container {
                width: min(100% - 24px, 1200px);
            }

            .page {
                padding: 16px 0 28px;
            }

            .topbar-inner {
                gap: 10px;
            }

            .nav a,
            .btn {
                width: 100%;
            }

            .actions {
                width: 100%;
            }

            .actions input,
            .actions select,
            .actions .btn,
            .actions a {
                width: 100%;
                min-width: 0;
            }

            table {
                min-width: 620px;
            }
        }
    </style>
</head>
<body>
@auth
    @php
        $authUser = auth()->user();
        $isPlatformAdmin = $authUser->isPlatformAdmin();
        $isCompanyUser = in_array($authUser->role, [
            \App\Models\User::ROLE_COMPANY_ADMIN,
            \App\Models\User::ROLE_DENTIST,
            \App\Models\User::ROLE_NURSE,
        ], true);
    @endphp
    <header class="topbar">
        <div class="container topbar-inner">
            <div class="brand">
                <div class="brand-title">Dentio</div>
                <div class="muted brand-subtitle">
                    {{ $isPlatformAdmin ? 'Platform admin' : ($authUser->company?->name ?? 'Bez kompanije') }}
                </div>
            </div>
            <nav class="nav">
                @if($isPlatformAdmin)
                    <a class="{{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Platform</a>
                @endif
                @if($isCompanyUser)
                    <a class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">Dashboard</a>
                    <a class="{{ request()->routeIs('patients.*') ? 'active' : '' }}" href="{{ route('patients.index') }}">Pacijenti</a>
                @endif
                @if($authUser->isCompanyAdmin())
                    <a class="{{ request()->routeIs('team.invites.*') ? 'active' : '' }}" href="{{ route('team.invites.index') }}">Tim i Invite</a>
                @endif
            </nav>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Odjava</button>
            </form>
        </div>
    </header>
@endauth

<main class="page">
    <div class="container">
        @include('partials.alerts')
        {{ $slot ?? '' }}
        @yield('content')
    </div>
</main>
</body>
</html>
