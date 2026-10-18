@extends('mails.layouts.base')

@section('preheader', $commentOfUser . ' commented on ' . $postTitle)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p><strong>{{ $commentOfUser }}</strong> added a comment to the post <strong>{{ $postTitle }}</strong>:</p>

    @include('mails.partials.quote', ['html' => nl2br(e($comment), false)])

    @include('mails.partials.button', ['url' => $postLink, 'label' => 'Answer the comment'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
