@extends('emails.layout')

@section('title', "Фактура за поръчка {$order->order_number}")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Фактурата за поръчка {{ $order->order_number }} е готова</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">
                Здравей, {{ $order->customer_first_name }}! Поръчката беше доставена — прилагаме фактурата за поръчката като прикачен файл.
            </p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding: 0 24px 32px; font-size: 13px; color: #71695c;">
            При въпроси около фактурата пиши ни на contact@smisul.bg.
        </td>
    </tr>
@endsection
