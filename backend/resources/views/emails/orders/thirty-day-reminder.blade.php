@extends('emails.layout')

@php
    $frontendUrl = rtrim(config('app.frontend_url'), '/');
    // $reviewUrl itself is built by OrderThirtyDayReminderMail::reviewUrl()
    // - a signed link (see that method's own docblock) that lets the
    // review wizard resolve this order's real customer_email/
    // customer_first_name and skip asking for them again, without putting
    // them directly in the URL and without requiring the recipient to be
    // logged in (works for a registered customer's order too, unlike the
    // guest_access_token this used before).
@endphp

@section('title', "Мина месец… и имаме нещо ново")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 8px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $order->customer_first_name }}</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c; line-height: 1.6;">
                Мина около месец, откакто твоят Miswak пристигна при теб, и решихме просто да те попитаме:
            </p>
            <p style="margin: 12px 0 0; font-size: 16px; color: #24362c; font-weight: 700;">
                Как върви с пръчката? 🌿
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">Ако вече е станала част от ежедневието ти — много се радваме.</p>
            <p style="margin: 0;">А ако първата ти пръчка е към края си, това е добър момент да си подготвиш следващата.</p>
        </td>
    </tr>

    <tr>
        <td style="padding: 24px 24px 0; font-size: 14px; color: #2b2822;">
            Междувременно добавихме и няколко нови неща:
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; border-radius: 8px;">
                <tr>
                    <td style="padding: 18px 20px;">
                        <p style="margin: 0 0 4px; font-size: 14px; color: #24362c; font-weight: 700;">🌿 Калъф за Miswak</p>
                        <p style="margin: 0; font-size: 13px; color: #71695c;">За да можеш спокойно да го носиш в чанта, раница или по време на път.</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0 20px 18px;">
                        <hr style="border: none; border-top: 1px solid #e6dcc7; margin: 0 0 18px;">
                        <p style="margin: 0 0 4px; font-size: 14px; color: #24362c; font-weight: 700;">👅 Стъргалка за език</p>
                        <p style="margin: 0; font-size: 13px; color: #71695c;">Малко допълнение към ежедневната орална грижа и усещането за свежест.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 13px; color: #71695c;">
            И ако твоят Miswak още не е свършил - няма никаква нужда да бързаш. :)
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $frontendUrl, 'label' => 'Разгледай какво ново има →'])

    <tr>
        <td style="padding: 0 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">А ако вече си натрупал впечатления от Miswak-а, ще ни е много ценно да ги чуем.</p>
            <p style="margin: 0;">Не търсим непременно „5 звезди" - просто истинското ти мнение. То помага и на нас да ставаме по-добри, и на хората, които за първи път попадат на Miswak.</p>
        </td>
    </tr>

    {{-- write_review=1 auto-opens the guest-friendly review wizard on
         landing (see ProductPage/ReviewsSection) instead of just linking to
         the page and leaving the visitor to find the button themselves; the
         signed order_id/expires/signature params (see $reviewUrl's own
         docblock) let it skip asking for an email/name it can already
         resolve from this order. --}}
    @include('emails.partials.cta-button', ['url' => $reviewUrl, 'label' => 'Остави ревю →'])

    <tr>
        <td style="padding: 0 24px 32px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 4px;">Благодарим ти, че избра С | МИСЪЛ. 🤍</p>
            <p style="margin: 0; color: #71695c;">Екипът на С | МИСЪЛ<br>smisul.bg</p>
        </td>
    </tr>
@endsection
