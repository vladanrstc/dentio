@extends('layouts.app', ['title' => 'Promeni status pacijenta'])

@section('content')
    <div class="page-header">
        <h2>Promeni status pacijenta</h2>
        <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-secondary">Nazad na pacijenta</a>
    </div>

    <article class="panel">
        @include('patients.partials.status-form')
    </article>
@endsection
