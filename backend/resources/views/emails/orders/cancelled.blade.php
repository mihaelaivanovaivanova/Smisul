@extends('emails.layout')

@section('title', "Поръчка {$order->order_number} е отказана")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Поръчка {{ $order->order_number }} е отказана</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">
                Здравей, {{ $order->customer_first_name }} — поръчка {{ $order->order_number }} на стойност {{ number_format((float) $order->grand_total, 2) }} {{ $order->currency }} беше отказана.
            </p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding: 0 24px 32px; font-size: 13px; color: #71695c;">
            Ако не си очаквал/а това или имаш въпроси, свържи се с нас.
        </td>
    </tr>
@endsection
