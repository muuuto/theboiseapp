@extends('mails.layouts.base')

@section('preheader', __('New album: :title', ['title' => $listing->title]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{{ isset($postedBy) ? __(':name posted a new album inside THEBOISE. Have a look and leave a comment!', ['name' => $postedBy]) : __('There is a new album inside THEBOISE. Have a look and leave a comment!') }}</p>

    @include('mails.partials.album', ['listing' => $listing, 'note' => 'New album', 'label' => 'Visit the album'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
