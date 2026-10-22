@extends('mails.layouts.base')

@section('preheader', __('You should check out this post: :title', ['title' => $postTitle]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{{ __('You should check out this new post inside the forum:') }}</p>

    <p style="font-size: 19px; font-weight: bold;">{{ $postTitle }}</p>

    @include('mails.partials.button', ['url' => $postLink, 'label' => 'Read the post'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
