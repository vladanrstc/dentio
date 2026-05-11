<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Pozivnica</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
<p>Zdravo,</p>
<p>Dobili ste pozivnicu da pristupite Dentio platformi.</p>
<p>
    Kliknite na link za aktivaciju naloga:<br>
    <a href="{{ route('invites.accept.show', $invite->token) }}">{{ route('invites.accept.show', $invite->token) }}</a>
</p>
<p>Pozivnica vazi do: {{ $invite->expires_at?->format('d.m.Y H:i') }}</p>
</body>
</html>

