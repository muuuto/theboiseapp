@extends('mails.layouts.base')

@section('preheader', __(':what :amount in :group', ['what' => $sentence, 'amount' => $amount, 'group' => $groupName]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{!! __(':name recorded a payment in :group:', ['name' => '<strong>' . e($recordedBy) . '</strong>', 'group' => '<strong>' . e($groupName) . '</strong>']) !!}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border: 1px solid #e2e8f0; border-radius: 16px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p style="margin: 0; font-size: 19px; font-weight: bold;">{{ $sentence }}</p>
                <p style="margin: 4px 0 2px; font-size: 28px; font-weight: bold; color: #0d9488;">{{ $amount }}</p>
                <p class="muted" style="margin: 0; font-size: 14px; color: #64748b;">{{ $date }}</p>
            </td>
        </tr>
    </table>

    <p>{{ __("If it's not right, you can delete it in the group.") }}</p>

    @include('mails.partials.button', ['url' => $link, 'label' => 'See the balances'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
