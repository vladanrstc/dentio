@extends('layouts.app', ['title' => 'Novi pacijent'])

@section('content')
    <section class="panel wide-card">
        <div class="page-header">
            <h2>Novi pacijent</h2>
            <a class="btn btn-secondary" href="{{ route('patients.index') }}">Nazad</a>
        </div>

        <form method="POST" action="{{ route('patients.store') }}" class="form-grid">
            @csrf

            <label class="field">
                <span>Ime</span>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required>
            </label>

            <label class="field">
                <span>Prezime</span>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required>
            </label>

            <label class="field">
                <span>Telefon</span>
                <input type="text" name="phone" value="{{ old('phone') }}" required>
            </label>

            <label class="field">
                <span>Email (opciono, za podsetnike)</span>
                <input type="email" name="email" value="{{ old('email') }}">
            </label>

            <label class="field full">
                <span>Adresa</span>
                <input type="text" name="address" value="{{ old('address') }}" required>
            </label>

            <label class="field full">
                <span>Primarni stomatolog</span>
                <select name="primary_dentist_id">
                    <option value="">-- nije dodeljen --</option>
                    @foreach($dentists as $dentist)
                        <option value="{{ $dentist->id }}" @selected((string) old('primary_dentist_id') === (string) $dentist->id)>
                            {{ $dentist->fullName() }} ({{ $dentist->role }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field full">
                <span>Napomena</span>
                <textarea name="notes">{{ old('notes') }}</textarea>
            </label>

            <div class="field full actions">
                <button class="btn" type="submit">Sacuvaj</button>
            </div>
        </form>
    </section>
@endsection
