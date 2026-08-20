<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>Фактура — {{ $order->order_number }}</title>
    <style>
        body { font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 700px; margin: 40px auto; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 6px; border-bottom: 1px solid #eee; }
        .totals td { border: none; }
        .placeholder-notice { color: #a1a1aa; font-size: 12px; margin-top: 32px; }
    </style>
</head>
<body>
    <img src="{{ rtrim(config('app.url'), '/') }}/mail/smisul-logo.png" alt="Smisul" height="48" style="display: block; height: 48px; width: auto; margin-bottom: 16px;">

    <h1>Фактура (placeholder)</h1>
    <p>
        Поръчка: <strong>{{ $order->order_number }}</strong><br>
        Дата: {{ $order->created_at->format('d.m.Y') }}
    </p>

    <h2>Продавач</h2>
    <p>
        Filchev Web LTD / „ФИЛЧЕВ УЕБ“ ЕООД<br>
        Управител: Владимир Стоянов Филчев<br>
        ЕИК: 208699419<br>
        contact@smisul.bg
    </p>

    <h2>Клиент</h2>
    <p>
        {{ $order->customerFullName() }}<br>
        {{ $order->customer_email }}<br>
        {{ $order->customer_phone }}
        @if ($order->customer_company)
            <br>{{ $order->customer_company }}
            @if ($order->customer_vat_number)
                (ДДС №: {{ $order->customer_vat_number }})
            @endif
        @endif
    </p>

    <h2>Адрес за фактуриране</h2>
    <p>
        {{ $order->billing_address_line }}{{ $order->billing_apartment ? ', '.$order->billing_apartment : '' }}<br>
        {{ $order->billing_city }}, {{ $order->billing_postal_code }}<br>
        {{ $order->billing_country }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Продукт</th>
                <th>SKU</th>
                <th>Количество</th>
                <th>Ед. цена</th>
                <th>Общо</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}{{ $item->variant_name ? " ({$item->variant_name})" : '' }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->unit_price, 2) }} {{ $order->currency }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }} {{ $order->currency }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr><td colspan="4">Междинна сума</td><td>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</td></tr>
            @if ((float) $order->discount_total > 0)
                <tr><td colspan="4">Отстъпка</td><td>-{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency }}</td></tr>
            @endif
            <tr><td colspan="4">Доставка ({{ $order->shipping_method_label }})</td><td>{{ number_format((float) $order->shipping_price, 2) }} {{ $order->currency }}</td></tr>
            <tr><td colspan="4"><strong>Общо</strong></td><td><strong>{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</strong></td></tr>
        </tfoot>
    </table>

    <p class="placeholder-notice">
        Това е временен, опростен документ и не представлява данъчна фактура. Реалното фактуриране предстои да бъде внедрено.
    </p>
</body>
</html>
