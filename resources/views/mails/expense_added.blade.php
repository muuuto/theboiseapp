@extends('mails.layouts.base')

@section('preheader', $addedBy . ' added ' . $description . ': your share is ' . $yourShare)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p><strong>{{ $addedBy }}</strong> added an expense to <strong>{{ $groupName }}</strong>:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border: 1px solid #e2e8f0; border-radius: 16px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0; font-size: 19px; font-weight: bold;">{{ $description }}</p>
                <p class="muted" style="margin: 2px 0 14px; font-size: 14px; color: #64748b;">{{ $date }} · {{ $total }} paid by {{ $paidBy }}</p>
                <p class="muted" style="margin: 0; font-size: 13px; font-weight: bold; color: #0d9488; text-transform: uppercase; letter-spacing: 0.5px;">Your share</p>
                <p style="margin: 0; font-size: 28px; font-weight: bold;">{{ $yourShare }}</p>
            </td>
        </tr>
    </table>

    @include('mails.partials.button', ['url' => $link, 'label' => 'See the balances'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
