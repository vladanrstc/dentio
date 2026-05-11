<article class="panel">
    <h3>Rucna promena statusa</h3>
    <div class="actions">
        <a href="{{ route('patients.status.edit', $patient->id) }}" class="btn">Promeni status</a>
    </div>

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 16px 0;">

    <h3>Dodaj aktivnu stavku</h3>
    <div class="actions">
        <a href="{{ route('patients.tasks.create', $patient->id) }}" class="btn">Dodaj aktivnu stavku</a>
    </div>
</article>
