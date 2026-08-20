@php
    $assetUrl = rtrim(config('app.url'), '/');
@endphp
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1e8d8; font-family: -apple-system, Arial, sans-serif; color: #2b2822;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%; background-color: #fffdf9;">

                    {{-- Header: logo + order number, same on every lifecycle email --}}
                    <tr>
                        <td style="padding: 20px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <img src="{{ $assetUrl }}/mail/smisul-logo.png" alt="Smisul" height="48" style="display: block; height: 48px; width: auto;">
                                    </td>
                                    <td align="right" style="vertical-align: middle; color: #71695c; font-size: 13px;">
                                        Поръчка<br>
                                        <strong style="color: #24362c; font-size: 15px;">#{{ $order->order_number }}</strong>
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
