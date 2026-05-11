<article class="panel">
    <h3>Novi termin</h3>
    <form method="POST" action="{{ route('patients.appointments.store', $patient->id) }}" class="form-grid">
        @csrf

        <label class="field">
            <span>Pocetak</span>
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
        </label>

        <label class="field">
            <span>Kraj</span>
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
        </label>

        <label class="field">
            <span>Tip termina</span>
            <select name="type" required>
                @foreach(['checkup' => 'checkup', 'intervention' => 'intervention', 'control' => 'control'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="field">
            <span>Odgovorni lekar</span>
            <select name="assigned_user_id">
                <option value="">-- nije dodeljeno --</option>
                @foreach($dentists as $dentist)
                    <option value="{{ $dentist->id }}" @selected((string) old('assigned_user_id') === (string) $dentist->id)>
                        {{ $dentist->fullName() }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="field full">
            <span>Napomena</span>
            <textarea name="notes">{{ old('notes') }}</textarea>
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
            <button class="btn" type="submit">Zakazi termin</button>
        </div>
    </form>
</article>
