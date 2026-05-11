@extends('layouts.app', ['title' => 'Unesi intervenciju'])

@section('content')
    <div class="page-header">
        <h2>Unesi intervenciju</h2>
        <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-secondary">Nazad na pacijenta</a>
    </div>

    @include('patients.partials.intervention-form')
@endsection
