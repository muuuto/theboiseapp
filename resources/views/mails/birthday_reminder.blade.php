@extends('mails.layouts.base')

@section('preheader', 'Today is all about you, ' . $user->name)

@section('content')
    <p style="font-size: 44px; line-height: 1; margin: 0 0 12px;">🎂</p>
    <p style="font-size: 24px; font-weight: bold; margin: 0 0 16px;">Happy Birthday, {{ $user->name }}!</p>

    <p>Today is all about you, and we hope it’s filled with great moments and good memories.</p>

    <p>We’d like to celebrate your {{ $age }} years of life the way my grandfather always liked to say it in {{ $key }}:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
        <tr>
            <td class="quote" style="background: #f0fdfa; border-left: 4px solid #0d9488; border-radius: 12px; padding: 16px 20px; font-size: 20px; font-style: italic; font-weight: bold; color: #0f172a;">
                {{ $greeting }}!
            </td>
        </tr>
    </table>

    <p>It’s a special wish that we hope brings a smile to your face and warmth to your heart.</p>

    <p>Thanks for being part of the journey. We’re happy to celebrate another year with you.</p>

    <p style="margin-bottom: 0;">Enjoy your day,<br><strong>theboise.it team</strong></p>
@endsection
