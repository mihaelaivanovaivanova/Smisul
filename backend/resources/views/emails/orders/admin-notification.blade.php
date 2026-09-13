@extends('emails.layout')

@section('title', 'Нова поръчка '.$order->order_number)

@section('content')
<tr><td style="padding: 24px;">
    <h1 style="font-size: 20px;">Нова поръчка: {{ $order->order_number }}</h1>

    <p>
        Клиент: {{ $order->customerFullName() }} &lt;{{ $order->customer_email }}&gt;<br>
        Телефон: {{ $order->customer_phone }}<br>
        @if ($order->customer_company)
            Фирма: {{ $order->customer_company }} (ДДС номер: {{ $order->customer_vat_number ?? 'няма' }})<br>
        @endif
        Тип: {{ $order->isGuestOrder() ? 'Гост' : 'Регистриран клиент' }}
    </p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin: 16px 0;">
        <thead>
            <tr style="border-bottom: 1px solid #ddd; text-align: left;">
                <th>SKU</th>
                <th>Продукт</th>
                <th>Брой</th>
                <th>Сума</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="border-bottom: 1px solid #eee;">
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->product_name }}{{ $item->variant_name ? " ({$item->variant_name})" : '' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->line_total, 2) }} {{ $order->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Общо: {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</strong></p>

    <p>
        Доставка: {{ $order->shipping_method_label }}<br>
        {{ $order->shipping_address_line }}{{ $order->shipping_apartment ? ', '.$order->shipping_apartment : '' }},
        {{ $order->shipping_city }} {{ $order->shipping_postal_code }}, {{ $order->shipping_country }}
    </p>

    @if ($order->delivery_notes)
        <p>Бележки за доставка: {{ $order->delivery_notes }}</p>
    @endif
</td></tr>
@endsection
