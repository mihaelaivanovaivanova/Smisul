@extends('emails.layout')

@php
    $frontendUrl = rtrim(config('app.frontend_url'), '/');
    $isCashOnDelivery = $order->payments->first()?->payment_method === \App\Enums\PaymentMethod::CashOnDelivery;
    $isOfficeDelivery = $order->shipping_delivery_type?->requiresOfficeSelection() ?? false;

    $trackingUrl = "{$frontendUrl}/orders/{$order->id}/tracking"
        .($order->guest_access_token ? '?token='.$order->guest_access_token : '');

    $deliverySummary = $isOfficeDelivery
        ? "{$order->shipping_method_label} — {$order->shipping_office_name}, {$order->shipping_city}"
        : "{$order->shipping_method_label}, {$order->shipping_address_line}, {$order->shipping_city}";
@endphp

@section('title', "Потвърждение на поръчка {$order->order_number}")

@section('content')
    {{-- Hero --}}
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Благодарим ти за поръчката, {{ $order->customer_first_name }}!</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">Продуктите ще стигнат до теб чрез {{ $deliverySummary }}.</p>
        </td>
    </tr>

    {{-- Info box --}}
    <tr>
        <td style="padding: 0 24px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; border-radius: 8px;">
                <tr>
                    <td style="padding: 16px 20px; font-size: 13px; color: #2b2822; line-height: 1.5;">
                        @if ($isCashOnDelivery)
                            Плащаш в брой на куриера при получаване.
                        @else
                            Обработваме плащането с карта.
                        @endif
                        Очаквана доставка: 1-2 работни дни.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $trackingUrl, 'label' => 'Детайли за поръчката'])

    @include('emails.partials.items-table', ['order' => $order])

    {{-- Order details --}}
    <tr>
        <td style="padding: 24px;">
            <h2 style="margin: 0 0 12px; font-size: 15px; color: #24362c;">Детайли за поръчката:</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px;">
                <tr>
                    <td style="color: #71695c; padding: 4px 0; width: 90px; vertical-align: top;">Име:</td>
                    <td style="color: #2b2822; padding: 4px 0;">{{ $order->customerFullName() }}</td>
                </tr>
                <tr>
                    <td style="color: #71695c; padding: 4px 0; vertical-align: top;">Дата:</td>
                    <td style="color: #2b2822; padding: 4px 0;">{{ $order->created_at->format('d.m.Y') }}</td>
                </tr>
                <tr>
                    <td style="color: #71695c; padding: 4px 0; vertical-align: top;">Плащане:</td>
                    <td style="color: #2b2822; padding: 4px 0;">{{ $isCashOnDelivery ? 'Наложен платеж' : 'Плащане с карта' }}</td>
                </tr>
                <tr>
                    <td style="color: #71695c; padding: 4px 0; vertical-align: top;">Доставка:</td>
                    <td style="color: #2b2822; padding: 4px 0;">
                        {{ $deliverySummary }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    @include('emails.partials.legal-disclosures', ['frontendUrl' => $frontendUrl])
@endsection
