<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New order {{ $order->order_number }}</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto;">
    <h1 style="font-size: 20px;">New order: {{ $order->order_number }}</h1>

    <p>
        Customer: {{ $order->customerFullName() }} &lt;{{ $order->customer_email }}&gt;<br>
        Phone: {{ $order->customer_phone }}<br>
        @if ($order->customer_company)
            Company: {{ $order->customer_company }} (VAT: {{ $order->customer_vat_number ?? 'n/a' }})<br>
        @endif
        Type: {{ $order->isGuestOrder() ? 'Guest checkout' : 'Registered customer' }}
    </p>

    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse: collapse; margin: 16px 0;">
        <thead>
            <tr style="border-bottom: 1px solid #ddd; text-align: left;">
                <th>SKU</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Line total</th>
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

    <p><strong>Grand total: {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</strong></p>

    <p>
        Shipping: {{ $order->shipping_method_label }}<br>
        {{ $order->shipping_address_line }}{{ $order->shipping_apartment ? ', '.$order->shipping_apartment : '' }},
        {{ $order->shipping_city }} {{ $order->shipping_postal_code }}, {{ $order->shipping_country }}
    </p>

    @if ($order->delivery_notes)
        <p>Delivery notes: {{ $order->delivery_notes }}</p>
    @endif
</body>
</html>
