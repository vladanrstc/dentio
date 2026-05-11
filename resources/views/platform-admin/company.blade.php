@extends('layouts.app', ['title' => 'Kompanija'])

@section('content')
    <section class="panel">
        <div style="display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div>
                <h2 style="margin: 0 0 4px;">{{ $company->name }}</h2>
                <div class="muted">{{ $company->address }}</div>
                <div class="muted">{{ $company->email ?: '-' }} | {{ $company->phone ?: '-' }}</div>
            </div>
            <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Nazad na listu</a>
        </div>
    </section>

    <section class="grid cols-3" style="margin-top: 12px;">
        <article class="panel">
            <h3>Osoblje</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $company->staff_count }}</div>
            <div class="muted">company admin, stomatolozi i sestre</div>
        </article>

        <article class="panel">
            <h3>Pacijenti</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $company->patients_count }}</div>
            <div class="muted">ukupno kartona u kompaniji</div>
        </article>

        <article class="panel">
            <h3>Termini/Intervencije</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $company->scheduled_appointments_count }} / {{ $company->interventions_count }}</div>
            <div class="muted">aktivni termini / istorija intervencija</div>
        </article>
    </section>

    <section class="grid cols-2" style="margin-top: 12px;">
        <article class="panel">
            <h3>Osoblje kompanije</h3>
            @if($company->users->isEmpty())
                <p class="muted">Nema unetog osoblja.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Ime</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Uloga</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($company->users as $member)
                        <tr>
                            <td>{{ $member->fullName() }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone ?: '-' }}</td>
                            <td>{{ $member->role }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>

        <article class="panel">
            <h3>Poslednji pacijenti</h3>
            @if($company->patients->isEmpty())
                <p class="muted">Nema pacijenata.</p>
            @else
                <table>
                    <thead>
                    <tr>
                        <th>Pacijent</th>
                        <th>Telefon</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($company->patients as $patient)
                        <tr>
                            <td>{{ $patient->fullName() }}</td>
                            <td>{{ $patient->phone ?: '-' }}</td>
                            <td>{{ $patient->manual_status }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </article>
    </section>

    <section class="panel" style="margin-top: 12px;">
        <h3>Poslednje pozivnice</h3>
        @if($company->invites->isEmpty())
            <p class="muted">Nema pozivnica.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Email</th>
                    <th>Uloga</th>
                    <th>Status</th>
                    <th>Istice</th>
                    <th>Kreirano</th>
                </tr>
                </thead>
                <tbody>
                @foreach($company->invites as $invite)
                    <tr>
                        <td>{{ $invite->email }}</td>
                        <td>{{ $invite->role }}</td>
                        <td>
                            @if($invite->accepted_at)
                                <span class="tag">Prihvaceno</span>
                            @elseif($invite->expires_at?->isPast())
                                <span class="tag danger">Isteklo</span>
                            @else
                                <span class="tag warn">Ceka odgovor</span>
                            @endif
                        </td>
                        <td>{{ $invite->expires_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $invite->created_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

