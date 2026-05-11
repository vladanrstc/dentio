<section class="panel">
    <div class="page-header">
        <div>
            <h2 style="margin: 0 0 4px;">{{ $patient->fullName() }}</h2>
            <div class="muted">{{ $patient->phone ?: '-' }} | {{ $patient->email ?: 'bez emaila' }}</div>
        </div>
        <div class="actions">
            <a href="{{ route('patients.index') }}" class="btn btn-secondary">Nazad na listu</a>
        </div>
    </div>

    <div class="actions" style="margin-top: 10px;">
        <span class="tag">Status: {{ $patient->manual_status }}</span>
        @if($patient->open_tasks_count > 0)
            <span class="tag warn">Aktivne stavke: {{ $patient->open_tasks_count }}</span>
        @else
            <span class="tag">Bez aktivnih stavki</span>
        @endif
        <span class="tag">Dug: {{ number_format($outstandingAmount, 2) }} RSD</span>
    </div>
</section>
