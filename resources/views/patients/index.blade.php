@extends('layouts.app', ['title' => 'Pacijenti'])

@section('content')
    <section class="panel">
        <div class="page-header">
            <h2>Pacijenti</h2>
            <a href="{{ route('patients.create') }}" class="btn">Novi pacijent</a>
        </div>
    </section>

    <section class="panel section">
        <div class="page-header">
            <h2>Lista pacijenata</h2>
            @include('patients.partials.index-search')
        </div>

        @include('patients.partials.index-table')
    </section>
@endsection
