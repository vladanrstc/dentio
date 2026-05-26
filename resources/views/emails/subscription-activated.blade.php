<p>Postovani,</p>

<p>Vaša Dentio pretplata je aktivirana.</p>

@if($company->subscription_current_period_end)
    <p>Pretplata traje do: {{ $company->subscription_current_period_end->timezone(config('app.timezone'))->format('d.m.Y. H:i') }}</p>
@endif

<p>Hvala sto koristite Dentio.</p>
