<h3>Dodaj aktivnu stavku</h3>
<form method="POST" action="{{ route('patients.tasks.store', $patient->id) }}" class="form-grid">
    @csrf

    <label class="field full">
        <span>Opis stavke</span>
        <textarea name="description" required>{{ old('description') }}</textarea>
    </label>

    <label class="field">
        <span>Rok</span>
        <input type="date" name="due_date" value="{{ old('due_date') }}">
    </label>

    <label class="field">
        <span>Dodeljeno</span>
        <select name="assigned_to_user_id">
            <option value="">-- nije dodeljeno --</option>
            @foreach($dentists as $dentist)
                <option value="{{ $dentist->id }}" @selected((string) old('assigned_to_user_id') === (string) $dentist->id)>
                    {{ $dentist->fullName() }}
                </option>
            @endforeach
        </select>
    </label>

    <div class="field full">
        <button class="btn" type="submit">Dodaj stavku</button>
    </div>
</form>
