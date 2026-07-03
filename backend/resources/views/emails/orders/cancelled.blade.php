<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Поръчка {{ $order->order_number }} е отказана</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Поръчка {{ $order->order_number }} е отказана</h1>

    <p>Здравей, {{ $order->customer_first_name }},</p>

    <p>Поръчка <strong>{{ $order->order_number }}</strong> на стойност {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }} беше отказана.</p>

    <p>Ако не си очаквал/а това или имаш въпроси, свържи се с нас.</p>
</body>
</html>
