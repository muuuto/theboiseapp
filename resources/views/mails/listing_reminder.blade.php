@extends('mails.layouts.base')

@section('preheader', $listings->count() == 1 ? __(':title is waiting for you', ['title' => $listings->first()->title]) : __(':count albums are waiting for you', ['count' => $listings->count()]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello :name 👋', ['name' => $user->name]) }}</p>

    <p>{{ $listings->count() == 1 ? __("You still haven't opened this album:") : __("You still haven't opened these albums:") }}</p>

    @foreach ($listings as $listing)
        @include('mails.partials.album', ['listing' => $listing, 'label' => 'View album'])
    @endforeach

    <p style="margin-bottom: 0;">{{ __('Thanks,') }}<br><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
