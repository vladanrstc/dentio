<article class="panel">
    <h3>Istorija termina</h3>
    @if($appointments->isEmpty())
        <p class="muted">Nema zakazanih termina.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Vreme</th>
                <th>Tip</th>
                <th>Status</th>
                <th>Zakazao</th>
                <th>Lekar</th>
            </tr>
            </thead>
            <tbody>
            @foreach($appointments as $appointment)
                <tr>
                    <td>{{ $appointment->starts_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ $appointment->type }}</td>
                    <td>{{ $appointment->status }}</td>
                    <td>{{ $appointment->scheduledBy?->fullName() ?? '-' }}</td>
                    <td>{{ $appointment->assignedTo?->fullName() ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</article>
