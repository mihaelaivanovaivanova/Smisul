@extends('emails.layout')

@section('title', $subjectLine)

@section('content')
    <tr>
        <td style="padding: 32px 24px 8px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            @foreach ($paragraphs as $paragraph)
                <p style="margin: 0 0 14px;">{{ $paragraph }}</p>
            @endforeach
        </td>
    </tr>

    @isset($ctaUrl)
        @include('emails.partials.cta-button', ['url' => $ctaUrl, 'label' => $ctaLabel ?? 'Разгледай →'])
    @endisset

    <tr>
        <td style="padding: 0 24px 32px; font-size: 14px; color: #2b2822; line-height: 1.6;">
            <p style="margin: 0; color: #71695c;">Екипът на С | МИСЪЛ<br>smisul.bg</p>
        </td>
    </tr>
@endsection
