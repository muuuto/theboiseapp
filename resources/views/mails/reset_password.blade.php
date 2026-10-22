@extends('mails.layouts.base')

@section('preheader', __('Someone asked to reset your password'))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{{ __('Someone (hopefully you) asked to reset the password of your theboise.it account. Choose a new one here:') }}</p>

    @include('mails.partials.button', ['url' => $link, 'label' => 'Choose a new password'])

    <p class="muted" style="font-size: 14px; color: #64748b;">{{ __("The link works for :minutes minutes. If you didn't ask for it, ignore this email: your password stays the same.", ['minutes' => $minutes]) }}</p>

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
