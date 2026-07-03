<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Поръчка {{ $order->order_number }} е изпратена</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Поръчката ти е на път, {{ $order->customer_first_name }}!</h1>

    <p>Поръчка <strong>{{ $order->order_number }}</strong> беше предадена на {{ $order->shipping_method_label }} и е на път към теб.</p>

    <p>
        Адрес за доставка:<br>
        {{ $order->shipping_address_line }}{{ $order->shipping_apartment ? ', '.$order->shipping_apartment : '' }}<br>
        {{ $order->shipping_city }}, {{ $order->shipping_postal_code }}<br>
        {{ $order->shipping_country }}
    </p>
</body>
</html>
