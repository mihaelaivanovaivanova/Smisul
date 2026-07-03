<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Поръчка {{ $order->order_number }} е потвърдена</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">Поръчката ти е потвърдена, {{ $order->customer_first_name }}!</h1>

    <p>Плащането по поръчка <strong>{{ $order->order_number }}</strong> е получено и вече подготвяме пратката ти.</p>

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

    <p><strong>Общо: {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</strong></p>
</body>
</html>
