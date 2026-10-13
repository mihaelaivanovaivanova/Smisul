@extends('emails.layout')

@section('title', 'Твоята -10% отстъпка е готова')

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 8px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $firstName }}</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c; line-height: 1.6;">
                Благодарим ти за отзива! Както обещахме, ето и твоята лична отстъпка.
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding: 24px 24px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; border-radius: 8px; border: 1px dashed #24362c;">
                <tr>
                    <td align="center" style="padding: 20px;">
                        <p style="margin: 0 0 6px; font-size: 13px; color: #71695c;">Твоят код за -{{ number_format($discountPercent, 0) }}% отстъпка</p>
                        <p style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 1px; color: #24362c;">{{ $code }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0;">Въведи го при плащане за -{{ number_format($discountPercent, 0) }}% от стойността на поръчката. Валиден до <strong>{{ $expiresAt->format('d.m.Y') }}</strong>, за еднократна употреба.</p>
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $shopUrl, 'label' => 'Пазарувай сега →'])

    <tr>
        <td style="padding: 0 24px 32px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0; color: #71695c;">Екипът на С | МИСЪЛ<br>smisul.bg</p>
        </td>
    </tr>
@endsection
