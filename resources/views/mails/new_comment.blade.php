@extends('mails.layouts.base')

@section('preheader', __(':name commented on :title', ['name' => $commentOfUser, 'title' => $albumTitle]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{!! __(':name added a comment to the :album album:', ['name' => '<strong>' . e($commentOfUser) . '</strong>', 'album' => '<strong>' . e($albumTitle) . '</strong>']) !!}</p>

    @include('mails.partials.quote', ['html' => \App\Support\SafeHtml::clean($comment)])

    @include('mails.partials.button', ['url' => $albumLink, 'label' => 'Answer the comment'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
