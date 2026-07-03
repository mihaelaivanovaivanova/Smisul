<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Поръчка {{ $order->order_number }} е доставена</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Поръчката ти пристигна, {{ $order->customer_first_name }}!</h1>

    <p>Поръчка <strong>{{ $order->order_number }}</strong> беше доставена. Надяваме се да ти хареса!</p>

    <p>Ако нещо не е наред, свържи се с нас — с удоволствие ще помогнем.</p>
</body>
</html>
