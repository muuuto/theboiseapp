@extends('mails.layouts.base')

@section('preheader', $sentence . ' ' . $amount . ' in ' . $groupName)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p><strong>{{ $recordedBy }}</strong> recorded a payment in <strong>{{ $groupName }}</strong>:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border: 1px solid #e2e8f0; border-radius: 16px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0; font-size: 19px; font-weight: bold;">{{ $sentence }}</p>
                <p style="margin: 4px 0 2px; font-size: 28px; font-weight: bold; color: #0d9488;">{{ $amount }}</p>
                <p class="muted" style="margin: 0; font-size: 14px; color: #64748b;">{{ $date }}</p>
            </td>
        </tr>
    </table>

    <p>If it's not right, you can delete it in the group.</p>

    @include('mails.partials.button', ['url' => $link, 'label' => 'See the balances'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
