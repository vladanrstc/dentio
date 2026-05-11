<article class="panel">
    <h3>Podaci o pacijentu</h3>
    <form method="POST" action="{{ route('patients.update', $patient->id) }}" class="form-grid">
        @csrf
        @method('PUT')

        <label class="field">
            <span>Ime</span>
            <input type="text" name="first_name" value="{{ old('first_name', $patient->first_name) }}" required>
        </label>

        <label class="field">
            <span>Prezime</span>
            <input type="text" name="last_name" value="{{ old('last_name', $patient->last_name) }}" required>
        </label>

        <label class="field">
            <span>Telefon</span>
            <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" required>
        </label>

        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email', $patient->email) }}">
        </label>

        <label class="field full">
            <span>Adresa</span>
            <input type="text" name="address" value="{{ old('address', $patient->address) }}" required>
        </label>

        <label class="field full">
            <span>Primarni stomatolog</span>
            <select name="primary_dentist_id">
                <option value="">-- nije dodeljen --</option>
                @foreach($dentists as $dentist)
                    <option value="{{ $dentist->id }}" @selected((string) old('primary_dentist_id', $patient->primary_dentist_id) === (string) $dentist->id)>
                        {{ $dentist->fullName() }} ({{ $dentist->role }})
                    </option>
                @endforeach
            </select>
        </label>

        <label class="field full">
            <span>Napomena</span>
            <textarea name="notes">{{ old('notes', $patient->notes) }}</textarea>
        </label>

        <div class="field full">
            <button class="btn" type="submit">Sacuvaj izmene</button>
        </div>
    </form>
</article>
