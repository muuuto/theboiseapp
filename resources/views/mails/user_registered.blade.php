@extends('mails.layouts.base')

@section('preheader', __(':name joined theboise.it', ['name' => $name]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello there admin 👋') }}</p>

    <p>{!! __('There is a new user: :name, :email.', ['name' => '<strong>' . e($name) . '</strong>', 'email' => e($email)]) !!}</p>

    @if ($note)
        <p>{{ __('Invite: :note', ['note' => $note]) }}</p>
    @endif

    <p style="margin-bottom: 0;"><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
