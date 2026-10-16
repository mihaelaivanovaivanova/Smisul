@extends('emails.layout')

@php
    $frontendUrl = rtrim(config('app.frontend_url'), '/');
    $trackingUrl = "{$frontendUrl}/orders/{$order->id}/tracking"
        .($order->guest_access_token ? '?token='.$order->guest_access_token : '');
    $officeLabel = $shipment->office_name ?? ($shipment->carrier->value === 'box_now' ? 'автомата на BOX NOW' : 'офиса на Speedy');

    $title = match ($stage) {
        1 => 'Пратката ти пристигна!',
        2 => 'Все още те очаква пратка',
        3 => 'Последно напомняне за пратка',
    };

    $intro = match ($stage) {
        1 => "Пратка {$order->order_number} вече е в {$officeLabel} и те очаква.",
        2 => "Пратка {$order->order_number} вече няколко дни те чака в {$officeLabel}.",
        3 => "Пратка {$order->order_number} все още не е потърсена от {$officeLabel}.",
    };
@endphp

@section('title', $title)

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 8px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $order->customer_first_name }}</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c; line-height: 1.6;">{{ $intro }}</p>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">
                <strong>Товарителница:</strong> {{ $shipment->tracking_number }}
            </p>
            @if ($stage === 3)
                <p style="margin: 0;">Моля, вземи пратката си възможно най-скоро.</p>
            @else
                <p style="margin: 0;">Ще се радваме да я вземеш, когато ти е удобно.</p>
            @endif
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $trackingUrl, 'label' => 'Проследи пратката'])

    <tr>
        <td align="center" style="padding: 0 24px 32px; font-size: 13px; color: #71695c;">
            Ако вече си взел/а пратката, игнорирай този имейл.
        </td>
    </tr>
@endsection
