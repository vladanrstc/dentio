<p>Postovani,</p>

<p>Vasa ordinacija je poslala Dentio link za placanje.</p>

@if($payment->description)
    <p>{{ $payment->description }}</p>
@endif

<p>Iznos: {{ number_format($payment->amount / 100, 2) }} {{ strtoupper($payment->currency) }}</p>

<p>
    <a href="{{ $payment->payment_url }}">Plati online</a>
</p>

<p>Ako niste ocekivali ovu poruku, ignorisite email.</p>
