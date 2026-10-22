@extends('mails.layouts.base')

@section('preheader', __('New post: :title', ['title' => $name]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there admin 👋') }}</p>

    <p>{!! __('There is a new post: :title.', ['title' => '<strong>' . e($name) . '</strong>']) !!}</p>

    @isset($postLink)
        @include('mails.partials.button', ['url' => $postLink, 'label' => 'Read the post'])
    @endisset

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
