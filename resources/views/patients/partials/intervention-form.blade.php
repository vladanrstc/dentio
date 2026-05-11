<article class="panel">
    <h3>Nova intervencija</h3>
    <form method="POST" action="{{ route('patients.interventions.store', $patient->id) }}" class="form-grid">
        @csrf

        <label class="field full">
            <span>Naziv intervencije</span>
            <input type="text" name="title" value="{{ old('title') }}" required>
        </label>

        <label class="field">
            <span>Datum</span>
            <input type="date" name="intervention_date" value="{{ old('intervention_date', now()->format('Y-m-d')) }}" required>
        </label>

        <label class="field">
            <span>Izvrsio lekar</span>
            <select name="performed_by_user_id">
                <option value="">-- automatski prijavljen korisnik --</option>
                @foreach($dentists as $dentist)
                    <option value="{{ $dentist->id }}" @selected((string) old('performed_by_user_id') === (string) $dentist->id)>
                        {{ $dentist->fullName() }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="field full">
            <span>Povezan termin (opciono)</span>
            <select name="appointment_id">
                <option value="">-- bez povezanog termina --</option>
                @foreach($appointments as $appointment)
                    <option value="{{ $appointment->id }}">
                        #{{ $appointment->id }} {{ $appointment->starts_at?->format('d.m.Y H:i') }} ({{ $appointment->type }})
                    </option>
                @endforeach
            </select>
        </label>

        <label class="field full">
            <span>Opis uradjenog</span>
            <textarea name="description">{{ old('description') }}</textarea>
        </label>

        <label class="field full">
            <span>Sledeci korak</span>
            <textarea name="next_step">{{ old('next_step') }}</textarea>
        </label>

        <label class="field">
            <span>Dodeli naredni korak</span>
            <select name="assigned_to_user_id">
                <option value="">-- automatski primarni lekar --</option>
                @foreach($dentists as $dentist)
                    <option value="{{ $dentist->id }}" @selected((string) old('assigned_to_user_id') === (string) $dentist->id)>
                        {{ $dentist->fullName() }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="field">
            <span>Rok za naredni korak</span>
            <input type="date" name="task_due_date" value="{{ old('task_due_date') }}">
        </label>

        <label class="field">
            <span>Cena intervencije (RSD)</span>
            <input type="number" step="0.01" min="0" name="total_cost" value="{{ old('total_cost', '0') }}">
        </label>

        <label class="field">
            <span>Placeno (RSD)</span>
            <input type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', '0') }}">
        </label>

        <label class="field">
            <span>Podsetnik osoblju</span>
            <input type="datetime-local" name="reminder_staff_at" value="{{ old('reminder_staff_at') }}">
        </label>

        <label class="field">
            <span>Podsetnik pacijentu</span>
            <input type="datetime-local" name="reminder_patient_at" value="{{ old('reminder_patient_at') }}">
        </label>

        <div class="field full">
            <button class="btn" type="submit">Sacuvaj intervenciju</button>
        </div>
    </form>
</article>
