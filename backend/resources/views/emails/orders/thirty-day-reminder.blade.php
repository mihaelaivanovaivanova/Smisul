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

@section('title', "Мина месец… И ще оценим твоят коментар")

@section('content')
    <tr>
        <td align="center" style="padding: 32px 24px 8px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Здравей, {{ $order->customer_first_name }}</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c; line-height: 1.6;">
                Мина около месец, откакто получи своя Miswak, и вече вероятно имаш достатъчно впечатления, за да ни кажеш как се разбирате. 🌿
            </p>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">За нас обратната връзка е наистина ценна — не само хубавата.</p>
            <p style="margin: 0;">Искаме да знаем какво ти хареса, какво не ти хареса и дали Miswak намери място в ежедневието ти.</p>
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822;">
            Затова, ако имаш минутка, ще се радваме да оставиш своето мнение:
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
        <td style="padding: 8px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 4px; font-weight: 700;">🎁 А ние ще ти благодарим с -10% за следващата поръчка.</p>
            {{-- The actual code only exists once the review is approved
                 (see IssueReviewRewardCode/ReviewRewardMail) - it isn't,
                 and can't be, included in this email itself. --}}
            <p style="margin: 0;">След като оставиш ревю, ще получиш персонален промокод за еднократна отстъпка -10% върху цялата поръчка, валиден 30 дни.</p>
        </td>
    </tr>

    <tr>
        <td style="padding: 24px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 8px;">Междувременно в Смисъл се появиха и няколко нови неща. 👀</p>
            <p style="margin: 0 0 8px;">Вече можеш да комбинираш Miswak с бамбуков калъф, медна стъргалка за език, както и да избереш някой от новите ни пакети, ако е време да заредиш отново.</p>
            <p style="margin: 0;">Така че, ако решиш да използваш своите -10%, вече има малко повече неща, от които да избираш. 🌿</p>
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $frontendUrl, 'label' => 'Виж какво ново има →'])

    <tr>
        <td style="padding: 0 24px 32px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0 0 4px;">Благодарим ти, че избра С | МИСЪЛ. 🤍</p>
            <p style="margin: 0; color: #71695c;">Екипът на С | МИСЪЛ<br>smisul.bg</p>
        </td>
    </tr>
@endsection
