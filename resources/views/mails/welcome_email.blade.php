@extends('mails.layouts.base')

@section('preheader', __('You have passed the tests. Welcome home new member.'))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{{ __('U have been added to the most prestigious circle. You have passed the tests. All we need now is a sacrifice.') }}</p>

    <p style="font-size: 19px; font-weight: bold;">{{ __('Welcome home new member.') }}</p>

    @include('mails.partials.button', ['url' => url('/'), 'label' => 'Enter THEBOISE'])

    <p style="margin-bottom: 0;">{{ __('Thank You for joining,') }}<br><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
