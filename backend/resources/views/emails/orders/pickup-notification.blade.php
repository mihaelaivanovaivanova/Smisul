@extends('emails.layout')

@php
    $officeLabel = $shipment->office_name ?? 'офиса на Speedy';
@endphp

@section('title', 'Пратката ти те очаква')

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 8px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $order->customer_first_name }}</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c; line-height: 1.6;">
                Пратка {{ $order->order_number }} те очаква за получаване в {{ $officeLabel }}.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">
                <strong>Товарителница:</strong> {{ $shipment->tracking_number }}
            </p>
            <p style="margin: 0;">Ще се радваме да я вземеш, когато ти е удобно.</p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding: 24px 24px 0; font-size: 13px; color: #71695c;">
            Ако вече си взел/а пратката, игнорирай този имейл.
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 32px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 4px;">Благодарим ти, че избра С | МИСЪЛ. 🤍</p>
            <p style="margin: 0; color: #71695c;">Екипът на С | МИСЪЛ<br>smisul.bg</p>
        </td>
    </tr>
@endsection
