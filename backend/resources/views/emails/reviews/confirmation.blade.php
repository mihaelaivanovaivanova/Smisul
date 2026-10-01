@extends('emails.layout')

@section('title', "Потвърди отзива си за {$product->name}")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $review->display_name }}!</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">
                Получихме отзива ти за „{{ $product->name }}“. За да бъде публикуван, моля потвърди го с бутона по-долу.
            </p>
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $confirmUrl, 'label' => 'Потвърди отзива'])

    <tr>
        <td style="padding: 0 24px 24px;">
            <hr style="border: none; border-top: 1px solid #e6dcc7; margin: 0 0 16px;">
            <p style="color: #71695c; font-size: 11px; margin: 0; line-height: 1.5;">
                Ако не си оставял/а отзив за този продукт, просто игнорирай това съобщение.
            </p>
        </td>
    </tr>
@endsection
