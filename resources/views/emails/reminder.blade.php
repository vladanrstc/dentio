<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>{{ $reminder->subject }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5;">
<p>{{ $reminder->subject }}</p>
@if(!empty($reminder->body))
    <p>{{ $reminder->body }}</p>
@endif
<p>Vreme podsetnika: {{ $reminder->remind_at?->format('d.m.Y H:i') }}</p>
</body>
</html>

