<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Запитване от сайта от {{ $name }}</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Ново запитване от сайта</h1>

    <p>
        От: {{ $name }} &lt;{{ $senderEmail }}&gt;
    </p>

    <p style="white-space: pre-wrap; border-left: 3px solid #ddd; padding-left: 12px;">{{ $body }}</p>

    <p style="color: #666; font-size: 13px;">Можеш да отговориш директно на този имейл — той ще стигне до подателя.</p>
</body>
</html>
