@extends('layouts.app', ['title' => 'Izmeni pacijenta'])

@section('content')
    <div class="page-header">
        <h2>Izmeni pacijenta</h2>
        <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-secondary">Nazad na pacijenta</a>
    </div>

    @include('patients.partials.edit-form')
@endsection
