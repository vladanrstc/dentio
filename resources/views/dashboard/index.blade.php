@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <section class="grid cols-3">
        <article class="panel">
            <h3>Pacijenti</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['patients_total'] }}</div>
            <div class="muted">Ukupno unetih pacijenata</div>
        </article>

        <article class="panel">
            <h3>Aktivne stavke</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['patients_with_open_tasks'] }}</div>
            <div class="muted">Pacijenti sa otvorenim zadacima</div>
        </article>

        <article class="panel">
            <h3>Danasnji termini</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['appointments_today'] }}</div>
            <div class="muted">Zakazano za danas</div>
        </article>

        <article class="panel">
            <h3>Podsetnici za slanje</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ $summary['reminders_due'] }}</div>
            <div class="muted">Ceka slanje email podsetnika</div>
        </article>

        <article class="panel">
            <h3>Dugovanja</h3>
            <div style="font-size: 2rem; font-weight: 700;">{{ number_format($summary['outstanding_amount'], 2) }} RSD</div>
            <div class="muted">Ukupna razlika cena - uplata</div>
        </article>

        <article class="panel">
            <h3>Brze akcije</h3>
            <div class="actions">
                <a href="{{ route('patients.create') }}" class="btn">Novi pacijent</a>
                <a href="{{ route('patients.index') }}" class="btn btn-secondary">Pretraga pacijenata</a>
            </div>
        </article>
    </section>

    <section class="panel" style="margin-top: 14px;">
        <h3>Naredni termini</h3>
        @if($summary['upcoming_appointments']->isEmpty())
            <p class="muted">Nema narednih termina.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Vreme</th>
                    <th>Pacijent</th>
                    <th>Lekar</th>
                    <th>Tip</th>
                </tr>
                </thead>
                <tbody>
                @foreach($summary['upcoming_appointments'] as $appointment)
                    <tr>
                        <td>{{ $appointment->starts_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <a href="{{ route('patients.show', $appointment->patient_id) }}">
                                {{ $appointment->patient?->fullName() }}
                            </a>
                        </td>
                        <td>{{ $appointment->assignedTo?->fullName() ?? '-' }}</td>
                        <td>{{ $appointment->type }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

