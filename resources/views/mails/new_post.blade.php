@extends('mails.layouts.base')

@section('preheader', 'New post: ' . $name)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there admin 👋</p>

    <p>There is a new post: <strong>{{ $name }}</strong>.</p>

    @isset($postLink)
        @include('mails.partials.button', ['url' => $postLink, 'label' => 'Read the post'])
    @endisset

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
