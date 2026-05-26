@php
    $acceptUrl = rtrim(config('app.frontend_url'), '/') . '/client/setup-password?token=' . urlencode($token);
@endphp

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Pozivnica za portal</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
<p>Zdravo,</p>
<p>Dobili ste pozivnicu da aktivirate Dentio portal za pacijente.</p>
<p>
    Kliknite na link i postavite lozinku:<br>
    <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
</p>
<p>Pozivnica vazi do: {{ $invite->expires_at?->format('d.m.Y H:i') }}</p>
</body>
</html>
