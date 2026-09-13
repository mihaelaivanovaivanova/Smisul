@php
    $assetUrl = rtrim(config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Without these, Gmail's mobile app applies its own automatic
         dark-mode color/contrast processing to this email (it renders fine
         as-is on desktop Gmail, which doesn't do this) - that processing is
         what was causing overlapping/duplicated text on Android/iOS Gmail.
         This tells every major client (Gmail, Outlook, Apple Mail) the
         email already has a deliberate light color scheme and shouldn't be
         reprocessed. --}}
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1e8d8; font-family: -apple-system, Arial, sans-serif; color: #2b2822;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #fffdf9;">

                    {{-- Header: logo + a right-side slot, same on every
                         lifecycle email. The order-lifecycle emails never
                         define a header_right section, so they keep getting
                         the order-number display below unchanged; a
                         non-order email (e.g. the funnel welcome email) can
                         either leave the slot blank or define its own
                         header_right section with a short label. --}}
                    <tr>
                        <td style="padding: 20px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <img src="{{ $message->embed(public_path('mail/smisul-logo.png')) }}" alt="Смисъл" width="200" style="display: block; width: 200px; max-width: 100%; height: auto;">
                                    </td>
                                    <td align="right" style="vertical-align: middle; color: #71695c; font-size: 13px;">
                                        @hasSection('header_right')
                                            @yield('header_right')
                                        @elseif (isset($order))
                                            Поръчка<br>
                                            <strong style="color: #24362c; font-size: 15px;">#{{ $order->order_number }}</strong>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 3px; background-color: #24362c; line-height: 0; font-size: 0;">&nbsp;</td>
                    </tr>

                    @yield('content')

                    <tr>
                        <td align="center" style="background-color: #24362c; padding: 20px 24px; color: #fffdf9; font-size: 13px;">
                            ПАЗАРУВАЙ С | МИСЪЛ
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
