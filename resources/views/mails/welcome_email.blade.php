@extends('mails.layouts.base')

@section('preheader', 'You have passed the tests. Welcome home new member.')

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p>U have been added to the most prestigious circle. You have passed the tests. All we need now is a sacrifice.</p>

    <p style="font-size: 19px; font-weight: bold;">Welcome home new member.</p>

    @include('mails.partials.button', ['url' => url('/'), 'label' => 'Enter THEBOISE'])

    <p style="margin-bottom: 0;">Thank You for joining,<br><strong>theboise.it team</strong></p>
@endsection
