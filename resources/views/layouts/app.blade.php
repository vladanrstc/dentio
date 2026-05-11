<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dentio' }}</title>
    <style>
        :root {
            --bg: #f3f5f9;
            --surface: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dde3ec;
            --accent: #1e7f8f;
            --accent-dark: #15606c;
            --warn: #92400e;
            --danger: #991b1b;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a { color: inherit; text-decoration: none; }
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
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
        }

        .nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav a {
            padding: 8px 10px;
            border-radius: 6px;
            color: #374151;
        }

        .nav a.active {
            background: #e1f2f5;
            color: #0f4f58;
            font-weight: 600;
        }

        .page {
            padding: 20px 0 36px;
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
            padding: 14px;
        }

        .panel h2, .panel h3 {
            margin-top: 0;
        }

        .muted { color: var(--muted); }

        .btn {
            border: 0;
            background: var(--accent);
            color: #fff;
            padding: 9px 12px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn:hover { background: var(--accent-dark); }

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
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        input, select, textarea {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #cfd6e2;
            border-radius: 6px;
            background: #fff;
            color: #111827;
            font: inherit;
        }

        textarea { min-height: 110px; resize: vertical; }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
            font-size: 0.95rem;
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
        }
    </style>
</head>
<body>
@auth
    @php
        $authUser = auth()->user();
        $isPlatformAdmin = $authUser->isPlatformAdmin();
        $hasCompanyContext = $authUser->company_id !== null;
    @endphp
    <header class="topbar">
        <div class="container topbar-inner">
            <div>
                <div style="font-weight:700;">Dentio</div>
                <div class="muted" style="font-size: 0.9rem;">
                    {{ $isPlatformAdmin ? 'Platform admin' : ($authUser->company?->name ?? 'Bez kompanije') }}
                </div>
            </div>
            <nav class="nav">
                @if($isPlatformAdmin)
                    <a class="{{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Platform</a>
                @endif
                @if($hasCompanyContext)
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
