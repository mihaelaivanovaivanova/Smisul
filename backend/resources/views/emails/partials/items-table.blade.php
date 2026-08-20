{{--
    Expects $order (with items eager-loaded; items.productVariant.product.primaryMedia
    optional — thumbnails are skipped per row when not loaded/available).
--}}
<tr>
    <td style="background-color: #f1e8d8; padding: 20px 24px;">
        <h2 style="margin: 0 0 12px; font-size: 15px; color: #24362c;">Продукти</h2>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #fffdf9; border-radius: 8px; border: 1px solid #e6dcc7;">
            @foreach ($order->items as $item)
                @php
                    $imageUrl = $item->productVariant?->product?->primaryMedia?->url();
                @endphp
                <tr>
                    <td style="padding: 14px 16px; @if (!$loop->last) border-bottom: 1px solid #e6dcc7; @endif">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                @if ($imageUrl)
                                    <td width="48" style="vertical-align: top; padding-right: 12px;">
                                        <img src="{{ $imageUrl }}" alt="" width="48" height="48" style="display: block; width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #e6dcc7;">
                                    </td>
                                @endif
                                <td style="vertical-align: middle; font-size: 13px; color: #2b2822;">
                                    {{ $item->product_name }}{{ $item->variant_name ? " ({$item->variant_name})" : '' }}<br>
                                    <span style="color: #71695c;">{{ $item->quantity }} бр.</span>
                                </td>
                                <td align="right" style="vertical-align: middle; font-size: 13px; color: #2b2822; white-space: nowrap;">
                                    {{ number_format((float) $item->line_total, 2) }} {{ $order->currency }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach

            <tr>
                <td style="padding: 14px 16px; border-top: 1px solid #e6dcc7;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px;">
                        <tr>
                            <td style="color: #71695c; padding: 2px 0;">Междинна сума</td>
                            <td align="right" style="color: #2b2822; padding: 2px 0;">{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</td>
                        </tr>
                        @if ((float) $order->discount_total > 0)
                            <tr>
                                <td style="color: #71695c; padding: 2px 0;">Отстъпка</td>
                                <td align="right" style="color: #2b2822; padding: 2px 0;">-{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td style="color: #71695c; padding: 2px 0;">Доставка ({{ $order->shipping_method_label }})</td>
                            <td align="right" style="color: #2b2822; padding: 2px 0;">{{ number_format((float) $order->shipping_price, 2) }} {{ $order->currency }}</td>
                        </tr>
                        <tr>
                            <td style="color: #24362c; font-weight: 700; padding: 8px 0 0;">Общо</td>
                            <td align="right" style="color: #24362c; font-weight: 700; padding: 8px 0 0;">{{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </td>
</tr>
