@extends('mails.layouts.base')

@section('preheader', __(':name added :what: your share is :amount', ['name' => $addedBy, 'what' => $description, 'amount' => $yourShare]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{!! __(':name added an expense to :group:', ['name' => '<strong>' . e($addedBy) . '</strong>', 'group' => '<strong>' . e($groupName) . '</strong>']) !!}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border: 1px solid #e2e8f0; border-radius: 16px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0; font-size: 19px; font-weight: bold;">{{ $description }}</p>
                <p class="muted" style="margin: 2px 0 14px; font-size: 14px; color: #64748b;">{{ $date }} · {{ __(':amount paid by :name', ['amount' => $total, 'name' => $paidBy]) }}</p>
                <p class="muted" style="margin: 0; font-size: 13px; font-weight: bold; color: #0d9488; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('Your share') }}</p>
                <p style="margin: 0; font-size: 28px; font-weight: bold;">{{ $yourShare }}</p>
            </td>
        </tr>
    </table>

    @include('mails.partials.button', ['url' => $link, 'label' => 'See the balances'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
