@extends('layouts.app', ['title' => 'Pacijenti'])

@section('content')
    <section class="panel">
        <div style="display:flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center;">
            <h2 style="margin: 0;">Novi pacijent</h2>
            <a href="{{ route('patients.create') }}" class="btn btn-secondary">Otvorena forma</a>
        </div>
        <p class="muted">Brzi unos direktno sa ove stranice.</p>

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
                <span>Email</span>
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

            <div class="field full">
                <button class="btn" type="submit">Sacuvaj pacijenta</button>
            </div>
        </form>
    </section>

    <section class="panel" style="margin-top: 12px;">
        <div style="display:flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center;">
            <h2 style="margin: 0;">Pacijenti</h2>
            <form method="GET" action="{{ route('patients.index') }}">
                <div class="actions">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ime, prezime ili telefon">
                    <button class="btn" type="submit">Pretrazi</button>
                    @if($search !== '')
                        <a href="{{ route('patients.index') }}" class="btn btn-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($patients->isEmpty())
            <p class="muted">Nema rezultata.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Pacijent</th>
                    <th>Telefon</th>
                    <th>Status</th>
                    <th>Primarni lekar</th>
                    <th>Aktivne stavke</th>
                </tr>
                </thead>
                <tbody>
                @foreach($patients as $patient)
                    <tr>
                        <td>
                            <a href="{{ route('patients.show', $patient->id) }}">
                                {{ $patient->fullName() }}
                            </a>
                        </td>
                        <td>{{ $patient->phone ?: '-' }}</td>
                        <td>{{ $patient->manual_status }}</td>
                        <td>{{ $patient->primaryDentist?->fullName() ?? '-' }}</td>
                        <td>
                            @if($patient->open_tasks_count > 0)
                                <span class="tag warn">{{ $patient->open_tasks_count }}</span>
                            @else
                                <span class="tag">0</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="margin-top: 12px;">
                {{ $patients->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
