@php
    /** Dual EUR/BGN labelling per Закона за въвеждане на еврото (until 8 Aug 2026). */
    $inBgn = fn ($amount) => number_format((float) $amount * 1.95583, 2);
    $frontendUrl = rtrim(config('app.frontend_url'), '/');
@endphp
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
        Междинна сума: {{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }} ({{ $inBgn($order->subtotal) }} лв.)<br>
        Доставка ({{ $order->shipping_method_label }}): {{ number_format((float) $order->shipping_price, 2) }} {{ $order->currency }} ({{ $inBgn($order->shipping_price) }} лв.)<br>
        <strong>Общо: {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }} ({{ $inBgn($order->grand_total) }} лв.)</strong><br>
        <span style="color: #666; font-size: 12px;">Официален фиксиран курс: 1 EUR = 1.95583 лв.</span>
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

    {{-- Durable-medium disclosures per чл. 49, ал. 8 ЗЗП: merchant identity,
         withdrawal right and links to the full pre-contract information. --}}
    <hr style="border: none; border-top: 1px solid #ddd; margin: 24px 0 12px;">
    <p style="color: #666; font-size: 12px;">
        Продавач: „ФИЛЧЕВ УЕБ“ ЕООД, ЕИК 208699419, България, гр. Варна 9000, р-н „Одесос“,
        ул. „Баба Тонка“ № 7, ет. 2, ап. 4 · contact@smisul.bg
    </p>
    <p style="color: #666; font-size: 12px;">
        Имаш право да се откажеш от договора в 14-дневен срок от получаването на стоката, без да посочваш
        причина (за разпечатани хигиенни продукти правото на отказ не се прилага — чл. 57, т. 5 ЗЗП).
        За продукти с ненарушена опаковка предлагаме и доброволно връщане до 30 дни. Пълните условия:
    </p>
    <p style="color: #666; font-size: 12px;">
        <a href="{{ $frontendUrl }}/legal/terms-of-service" style="color: #24362c;">Общи условия</a> ·
        <a href="{{ $frontendUrl }}/legal/right-of-withdrawal" style="color: #24362c;">Право на отказ (със стандартен формуляр)</a> ·
        <a href="{{ $frontendUrl }}/legal/returns-policy" style="color: #24362c;">Връщане и рекламации</a> ·
        <a href="{{ $frontendUrl }}/legal/privacy-policy" style="color: #24362c;">Поверителност</a>
    </p>
</body>
</html>
