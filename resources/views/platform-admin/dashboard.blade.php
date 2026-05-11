@extends('layouts.app', ['title' => 'Platform Admin'])

@section('content')
    <section class="grid cols-3">
        <article class="panel">
            <h3>Kompanije</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['companies_total'] }}</div>
            <div class="muted">Ukupan broj registrovanih firmi</div>
        </article>

        <article class="panel">
            <h3>Korisnici</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['users_total'] }}</div>
            <div class="muted">
                platform admin: {{ $summary['platform_admins_total'] }},
                company admin: {{ $summary['company_admins_total'] }}
            </div>
        </article>

        <article class="panel">
            <h3>Medicinsko osoblje</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['dentists_total'] + $summary['nurses_total'] }}</div>
            <div class="muted">stomatolozi: {{ $summary['dentists_total'] }}, sestre: {{ $summary['nurses_total'] }}</div>
        </article>
    </section>

    <section class="panel" style="margin-top: 14px;">
        <div style="display:flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center;">
            <div>
                <h3 style="margin: 0;">Pozovi company owner-a</h3>
                <div class="muted">Posalji vlasniku firme email pozivnicu za kreiranje kompanije na platformi.</div>
            </div>
            <form method="POST" action="{{ route('admin.invites.company-owner.store') }}">
                @csrf
                <div class="actions">
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="owner@kompanija.rs" required>
                    <button type="submit" class="btn">Posalji invite</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel" style="margin-top: 14px;">
        <div style="display:flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center;">
            <h3 style="margin: 0;">Kompanije na platformi</h3>
            <form method="GET" action="{{ route('admin.dashboard') }}">
                <div class="actions">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Naziv, adresa ili email">
                    <button type="submit" class="btn">Pretrazi</button>
                    @if($search !== '')
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($companies->isEmpty())
            <p class="muted" style="margin-top: 12px;">Nema kompanija za prikaz.</p>
        @else
            <table style="margin-top: 10px;">
                <thead>
                <tr>
                    <th>Kompanija</th>
                    <th>Osoblje</th>
                    <th>Pacijenti</th>
                    <th>Aktivni termini</th>
                    <th>Pending invite</th>
                </tr>
                </thead>
                <tbody>
                @foreach($companies as $company)
                    <tr>
                        <td>
                            <a href="{{ route('admin.companies.show', $company->id) }}">
                                <strong>{{ $company->name }}</strong>
                            </a><br>
                            <span class="muted">{{ $company->address }}</span>
                        </td>
                        <td>
                            {{ $company->staff_count }}<br>
                            <span class="muted">D: {{ $company->dentists_count }}, S: {{ $company->nurses_count }}</span>
                        </td>
                        <td>{{ $company->patients_count }}</td>
                        <td>{{ $company->scheduled_appointments_count }}</td>
                        <td>
                            @if($company->pending_invites_count > 0)
                                <span class="tag warn">{{ $company->pending_invites_count }}</span>
                            @else
                                <span class="tag">0</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top: 12px;">
                {{ $companies->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
