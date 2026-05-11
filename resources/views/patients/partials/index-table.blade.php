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
