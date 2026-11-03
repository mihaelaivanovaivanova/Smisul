@extends('emails.layout')

@section('title', 'Твоят наръчник за естествена грижа за устната кухина')

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Поръчка {{ $order->order_number }} е доставена!</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">
                Прикачихме наръчника за естествена грижа за устната кухина, който е част от твоя комплект - всичко, което
                трябва да знаеш, за да изкараш максимума от него.
            </p>
        </td>
    </tr>

    <tr>
        <td align="center" style="padding: 0 24px 32px; font-size: 13px; color: #71695c;">
            Ако имаш въпроси, свържи се с нас — с удоволствие ще помогнем.
        </td>
    </tr>
@endsection
