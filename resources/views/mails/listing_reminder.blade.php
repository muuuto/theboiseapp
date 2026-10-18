@extends('mails.layouts.base')

@section('preheader', $listings->count() == 1 ? $listings->first()->title . ' is waiting for you' : $listings->count() . ' albums are waiting for you')

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello {{ $user->name }} 👋</p>

    <p>You still haven't opened {{ $listings->count() == 1 ? 'this album' : 'these albums' }}:</p>

    @foreach ($listings as $listing)
        @include('mails.partials.album', ['listing' => $listing, 'label' => 'View album'])
    @endforeach

    <p style="margin-bottom: 0;">Thanks,<br><strong>theboise.it team</strong></p>
@endsection
