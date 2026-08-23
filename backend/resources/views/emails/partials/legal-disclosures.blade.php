{{--
    Durable-medium disclosures per чл. 49, ал. 8 ЗЗП: merchant identity,
    withdrawal right and links to the full pre-contract information.
    Expects $frontendUrl and $seller (SettingService::sellerIdentity() —
    single source of truth shared with invoices/order.blade.php, so
    editing the admin Settings screen's company fields actually changes
    what's printed here instead of this partial hardcoding a stale copy).
--}}
<tr>
    <td style="padding: 0 24px 24px;">
        <hr style="border: none; border-top: 1px solid #e6dcc7; margin: 0 0 16px;">
        <p style="color: #71695c; font-size: 11px; margin: 0 0 8px; line-height: 1.5;">
            Продавач: @if ($seller['name_en']){{ $seller['name_en'] }} / @endif{{ $seller['name'] }}, ЕИК {{ $seller['company_id'] }}@if ($seller['manager']), управител {{ $seller['manager'] }}@endif,<br>
            {{ $seller['address'] }} · {{ $seller['email'] }}
        </p>
        <p style="color: #71695c; font-size: 11px; margin: 0 0 8px; line-height: 1.5;">
            Имаш право да се откажеш от договора в 14-дневен срок от получаването на стоката, без да посочваш
            причина (за разпечатани хигиенни продукти правото на отказ не се прилага — чл. 57, т. 5 ЗЗП).
            За продукти с ненарушена опаковка предлагаме и доброволно връщане до 30 дни. Пълните условия:
        </p>
        <p style="color: #71695c; font-size: 11px; margin: 0;">
            <a href="{{ $frontendUrl }}/legal/terms-of-service" style="color: #24362c;">Общи условия</a> ·
            <a href="{{ $frontendUrl }}/legal/right-of-withdrawal" style="color: #24362c;">Право на отказ, връщане и рекламации (със стандартен формуляр)</a> ·
            <a href="{{ $frontendUrl }}/legal/privacy-policy" style="color: #24362c;">Поверителност</a>
        </p>
    </td>
</tr>
