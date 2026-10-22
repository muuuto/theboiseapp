@extends('mails.layouts.base')

@section('preheader', __(':name commented on :title', ['name' => $commentOfUser, 'title' => $postTitle]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there :name 👋', ['name' => $name]) }}</p>

    <p>{!! __(':name added a comment to the post :post:', ['name' => '<strong>' . e($commentOfUser) . '</strong>', 'post' => '<strong>' . e($postTitle) . '</strong>']) !!}</p>

    @include('mails.partials.quote', ['html' => nl2br(e($comment), false)])

    @include('mails.partials.button', ['url' => $postLink, 'label' => 'Answer the comment'])

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
