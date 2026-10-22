@extends('mails.layouts.base')

@section('preheader', $text)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; border: 1px solid #e2e8f0; border-radius: 16px;">
        <tr>
            <td style="padding: 16px 18px;">
                <p class="muted" style="margin: 0 0 4px; font-size: 13px; font-weight: bold; color: #0d9488; text-transform: uppercase; letter-spacing: 0.5px;">✈️ {{ __('Trip') }}</p>
                <p style="margin: 0; font-size: 19px; font-weight: bold;">{{ $heading }}</p>
                <p style="margin: 6px 0 0;">{{ $text }}</p>
                <p class="muted" style="margin: 8px 0 0; font-size: 14px; color: #64748b;">
                    {{ $trip->title }}@if ($trip->destination) · {{ $trip->destination }}@endif @if ($dates) · {{ $dates }}@endif
                </p>
            </td>
        </tr>
    </table>

    @include('mails.partials.button', ['url' => $link, 'label' => $button])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
