<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Потвърждение на поръчка {{ $order->order_number }}</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Благодарим ти за поръчката, {{ $order->customer_first_name }}!</h1>

    <p>Поръчка <strong>{{ $order->order_number }}</strong> е приета и очаква обработка.</p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin: 16px 0;">
        <thead>
            <tr style="border-bottom: 1px solid #ddd; text-align: left;">
                <th>Продукт</th>
                <th>Количество</th>
                <th>Цена</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="border-bottom: 1px solid #eee;">
                    <td>{{ $item->product_name }}{{ $item->variant_name ? " ({$item->variant_name})" : '' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }} {{ $order->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>
        Междинна сума: {{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}<br>
        Доставка ({{ $order->shipping_method_label }}): {{ number_format((float) $order->shipping_price, 2) }} {{ $order->currency }}<br>
        <strong>Общо: {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</strong>
    </p>

    <h2 style="font-size: 16px;">Адрес за доставка</h2>
    <p>
        {{ $order->shipping_address_line }}{{ $order->shipping_apartment ? ', '.$order->shipping_apartment : '' }}<br>
        {{ $order->shipping_city }}, {{ $order->shipping_postal_code }}<br>
        {{ $order->shipping_country }}
    </p>

    <p style="color: #666; font-size: 13px;">
        Плащането ще бъде добавено в следваща стъпка — засега поръчката е регистрирана и очаква потвърждение.
    </p>
</body>
</html>
