<article class="panel">
    <h3>Istorija intervencija i finansije</h3>
    @if($interventions->isEmpty())
        <p class="muted">Nema zabelezenih intervencija.</p>
    @else
        <table>
            <thead>
            <tr>
                <th>Datum</th>
                <th>Intervencija</th>
                <th>Lekar</th>
                <th>Cena</th>
                <th>Placeno</th>
                <th>Razlika</th>
            </tr>
            </thead>
            <tbody>
            @foreach($interventions as $intervention)
                <tr>
                    <td>{{ $intervention->intervention_date?->format('d.m.Y') }}</td>
                    <td>
                        <strong>{{ $intervention->title }}</strong><br>
                        <span class="muted">{{ $intervention->next_step ?: 'bez sledeceg koraka' }}</span>
                    </td>
                    <td>{{ $intervention->performedBy?->fullName() ?? '-' }}</td>
                    <td>{{ number_format((float) $intervention->total_cost, 2) }}</td>
                    <td>{{ number_format((float) $intervention->paid_amount, 2) }}</td>
                    <td>{{ number_format(max(0, (float) $intervention->total_cost - (float) $intervention->paid_amount), 2) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <th colspan="3">Ukupno</th>
                <th>{{ number_format($totalCost, 2) }}</th>
                <th>{{ number_format($paidAmount, 2) }}</th>
                <th>{{ number_format($outstandingAmount, 2) }}</th>
            </tr>
            </tfoot>
        </table>
    @endif
</article>
