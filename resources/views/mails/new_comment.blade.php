@extends('mails.layouts.base')

@section('preheader', $commentOfUser . ' commented on ' . $albumTitle)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p><strong>{{ $commentOfUser }}</strong> added a comment to the <strong>{{ $albumTitle }}</strong> album:</p>

    @include('mails.partials.quote', ['html' => \App\Support\SafeHtml::clean($comment)])

    @include('mails.partials.button', ['url' => $albumLink, 'label' => 'Answer the comment'])

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
