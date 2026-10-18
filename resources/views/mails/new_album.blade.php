@extends('mails.layouts.base')

@section('preheader', 'New album: ' . $listing->title)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p>A new album is available inside THEBOISE. Have a look and leave a comment!</p>

    @include('mails.partials.album', ['listing' => $listing, 'note' => 'New album', 'label' => 'Visit the album'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
