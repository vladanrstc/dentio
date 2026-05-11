<article class="panel">
    <h3>Aktivne i zavrsene stavke</h3>
    @if($tasks->isEmpty())
        <p class="muted">Nema unetih stavki.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Opis</th>
                <th>Rok</th>
                <th>Status</th>
                <th>Dodeljeno</th>
                <th>Akcija</th>
            </tr>
            </thead>
            <tbody>
            @foreach($tasks as $task)
                <tr>
                    <td>{{ $task->description }}</td>
                    <td>{{ $task->due_date?->format('d.m.Y') ?: '-' }}</td>
                    <td>{{ $task->status }}</td>
                    <td>{{ $task->assignedTo?->fullName() ?? '-' }}</td>
                    <td>
                        @if($task->status === 'open')
                            <form method="POST" action="{{ route('patients.tasks.complete', [$patient->id, $task->id]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-secondary">Oznaci zavrseno</button>
                            </form>
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</article>
