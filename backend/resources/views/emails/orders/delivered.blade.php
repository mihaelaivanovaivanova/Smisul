@extends('emails.layout')

@php
    $frontendUrl = rtrim(config('app.frontend_url'), '/');
    $trackingUrl = "{$frontendUrl}/orders/{$order->id}/tracking"
        .($order->guest_access_token ? '?token='.$order->guest_access_token : '');
@endphp

@section('title', "Поръчка {$order->order_number} е доставена")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Поръчката ти пристигна, {{ $order->customer_first_name }}!</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">Поръчка {{ $order->order_number }} беше доставена. Надяваме се да ти хареса!</p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding: 0 24px 32px; font-size: 13px; color: #71695c;">
            Ако нещо не е наред, свържи се с нас — с удоволствие ще помогнем.
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $trackingUrl, 'label' => 'Детайли за поръчката'])
@endsection
