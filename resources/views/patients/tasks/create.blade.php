@extends('layouts.app', ['title' => 'Dodaj aktivnu stavku'])

@section('content')
    <div class="page-header">
        <h2>Dodaj aktivnu stavku</h2>
        <a href="{{ route('patients.show', $patient->id) }}" class="btn btn-secondary">Nazad na pacijenta</a>
    </div>

    <article class="panel">
        @include('patients.partials.task-form')
    </article>
@endsection
