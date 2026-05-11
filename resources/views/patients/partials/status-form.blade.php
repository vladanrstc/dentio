<h3>Rucna promena statusa</h3>
<form method="POST" action="{{ route('patients.status.update', $patient->id) }}" class="grid">
    @csrf
    @method('PATCH')

    <label class="field">
        <span>Status</span>
        <select name="manual_status" required>
            @foreach(['active' => 'active', 'inactive' => 'inactive', 'transferred' => 'transferred', 'completed' => 'completed'] as $value => $label)
                <option value="{{ $value }}" @selected(old('manual_status', $patient->manual_status) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </label>

    <label class="field">
        <span>Obrazlozenje</span>
        <textarea name="manual_status_reason">{{ old('manual_status_reason', $patient->manual_status_reason) }}</textarea>
    </label>

    <button class="btn" type="submit">Promeni status</button>
</form>
