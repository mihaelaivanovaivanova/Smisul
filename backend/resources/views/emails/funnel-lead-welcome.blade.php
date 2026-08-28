@extends('emails.layout')

@section('title', 'Благодарим ти! Ето и нашето ръководство за Miswak')

@section('content')
    {{-- Hero --}}
    <tr>
        <td align="center" style="padding: 32px 24px 24px;">
            <h1 style="margin: 0 0 8px; font-size: 20px; color: #24362c;">Благодарим, че се записа!</h1>
            <p style="margin: 0; font-size: 14px; color: #24362c;">Ето нещо полезно за начало, докато решиш дали Miswak е за теб.</p>
        </td>
    </tr>

    {{-- Info box - same cream callout treatment as the order emails'
         shipping/payment note. --}}
    <tr>
        <td style="padding: 0 24px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1e8d8; border-radius: 8px;">
                <tr>
                    <td style="padding: 16px 20px; font-size: 13px; color: #2b2822; line-height: 1.5;">
                        Обещахме да ти пишем само когато има нещо смислено - промоция или специално предложение.
                        Дотогава, изтегли ръководството за употреба по-долу.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    @include('emails.partials.cta-button', ['url' => $manualUrl, 'label' => 'Изтегли ръководството (PDF)'])

    {{-- Body --}}
    <tr>
        <td style="padding: 0 24px 24px;">
            <p style="margin: 0 0 12px; font-size: 13px; color: #2b2822; line-height: 1.5;">
                В него ще намериш как се подготвя клонката, колко издържа една и как да я съхраняваш, за да е винаги свежа.
            </p>
            <p style="margin: 0; font-size: 13px; color: #2b2822; line-height: 1.5;">
                А когато решиш да опиташ: <a href="{{ $shopUrl }}" style="color: #24362c;">smisul.bg</a>.
            </p>
        </td>
    </tr>

    {{-- Unsubscribe note - same muted footnote treatment as the legal
         disclosures partial on order emails. --}}
    <tr>
        <td style="padding: 0 24px 24px;">
            <hr style="border: none; border-top: 1px solid #e6dcc7; margin: 0 0 16px;">
            <p style="color: #71695c; font-size: 11px; margin: 0; line-height: 1.5;">
                Ако не искаш да получаваш имейли от нас, просто отговори на този имейл с „Отпиши ме“ и ще те премахнем от списъка.
            </p>
        </td>
    </tr>
@endsection
