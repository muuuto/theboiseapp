@extends('mails.layouts.base')

@section('preheader', __(':name posted :title', ['name' => $postedBy, 'title' => $listing->title]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{!! trans_choice(":name posted a new album with :count person in it. You're not in it, so this is just to let you know.|:name posted a new album with :count people in it. You're not in it, so this is just to let you know.", $peopleCount, ['name' => '<strong>' . e($postedBy) . '</strong>']) !!}</p>

    @include('mails.partials.album', ['listing' => $listing, 'note' => __('Posted by :name', ['name' => $postedBy]), 'label' => 'Have a look'])

    <p class="muted" style="font-size: 14px; color: #64748b;">{{ __('Every change to it is in its history (album page, admins only). Who can post albums: maintenance page.') }}</p>

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
