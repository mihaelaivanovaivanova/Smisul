@extends('emails.layout')

@php
    // $reviewUrl itself is built by OrderThirtyDayReminderMail::reviewUrl()
    // - a signed link (see that method's own docblock) that lets the
    // review wizard resolve this order's real customer_email/
    // customer_first_name and skip asking for them again, without putting
    // them directly in the URL and without requiring the recipient to be
    // logged in (works for a registered customer's order too, unlike the
    // guest_access_token this used before).
    //
    // Deliberately still a single CTA (the review link) and no boxed
    // "what's new" callout - an earlier version bolted a full marketing
    // section onto this review ask (second button, a bordered product
    // box), which read as a promotional blast rather than a personal
    // request and was landing in Gmail's Promotions tab as a result. The
    // two accessory products are mentioned as a passing sentence rather
    // than that separate boxed section/CTA. Emoji were removed for the
    // same Promotions-tab reason and have since been restored by request -
    // accept that as one more (minor, on its own) signal in that
    // direction, on top of the sender-identity split (config('mail.
    // promotional_from')) that's since addressed the bigger lever.
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
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            Между другото, добавихме и 🌿 бамбуков калъф за Miswak, както и 👅 стъргалка за език — ако ти трябват, вече ги има в сайта.
        </td>
    </tr>

    <tr>
        <td style="padding: 16px 24px 0; font-size: 14px; color: #2b2822; line-height: 1.6;">
            {{-- The code itself only exists once the review is approved
                 (see IssueReviewRewardCode/ReviewRewardMail) - it isn't,
                 and can't be, included in this email. The -10% figure is
                 emphasized inline (bold + larger) rather than as a
                 separate boxed "deal" callout - that kind of graphical
                 offer block is one of the patterns that reads as a
                 marketing blast to Gmail's classifier; plain inline
                 emphasis inside a sentence doesn't carry the same signal. --}}
            Ако имаш минутка, ще се радваме да оставиш своето мнение. Като благодарност 🎁 ще получиш и личен код за
            <strong style="font-size: 17px; color: #24362c;">-10% отстъпка</strong>
            за следващата си поръчка, валиден 30 дни.
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
