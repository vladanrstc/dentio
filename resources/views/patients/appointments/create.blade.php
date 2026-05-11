@extends('layouts.app', ['title' => 'Zakazi termin'])

@section('content')
    <div class="page-header">
        <h2>Zakazi termin</h2>
        <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-secondary">Nazad na pacijenta</a>
    </div>

    @include('patients.partials.appointment-form')
@endsection
