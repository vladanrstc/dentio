@extends('layouts.app', ['title' => 'Pacijent'])

@section('content')
    @php
        $interventions = $patient->interventions->sortByDesc('intervention_date');
        $appointments = $patient->appointments->sortByDesc('starts_at');
        $tasks = $patient->tasks->sortByDesc('created_at');
        $statusLogs = $patient->statusLogs->sortByDesc('created_at');
        $totalCost = (float) $interventions->sum('total_cost');
        $paidAmount = (float) $interventions->sum('paid_amount');
        $outstandingAmount = max(0, $totalCost - $paidAmount);
    @endphp

    @include('patients.partials.summary')

    <section class="grid cols-2 section">
        <article class="panel">
            <h3>Podaci o pacijentu</h3>
            <div class="actions">
                <a href="{{ route('patients.edit', $patient->id) }}" class="btn">Izmeni pacijenta</a>
            </div>
        </article>
        @include('patients.partials.status-and-task-forms')
    </section>

    <section class="grid cols-2 section">
        <article class="panel">
            <h3>Novi termin</h3>
            <div class="actions">
                <a href="{{ route('patients.appointments.create', $patient->id) }}" class="btn">Zakaži termin</a>
            </div>
        </article>
        <article class="panel">
            <h3>Nova intervencija</h3>
            <div class="actions">
                <a href="{{ route('patients.interventions.create', $patient->id) }}" class="btn">Unesi intervenciju</a>
            </div>
        </article>
    </section>

    <section class="grid cols-2 section">
        @include('patients.partials.tasks-table')
        @include('patients.partials.status-history')
    </section>

    <section class="grid cols-2 section">
        @include('patients.partials.appointment-history')
        @include('patients.partials.intervention-history')
    </section>
@endsection
