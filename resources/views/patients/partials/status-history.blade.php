<article class="panel">
    <h3>Status log</h3>
    @if($statusLogs->isEmpty())
        <p class="muted">Nema promena statusa.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Vreme</th>
                <th>Promena</th>
                <th>Ko</th>
                <th>Razlog</th>
            </tr>
            </thead>
            <tbody>
            @foreach($statusLogs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                    <td>{{ $log->previous_status }} -> {{ $log->new_status }}</td>
                    <td>{{ $log->changedBy?->fullName() ?? '-' }}</td>
                    <td>{{ $log->reason ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</article>
