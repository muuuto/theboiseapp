@extends('mails.layouts.base')

@section('preheader', 'You should check out this post: ' . $postTitle)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p>You should check out this new post inside the forum:</p>

    <p style="font-size: 19px; font-weight: bold;">{{ $postTitle }}</p>

    @include('mails.partials.button', ['url' => $postLink, 'label' => 'Read the post'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
