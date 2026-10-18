@extends('mails.layouts.base')

@section('preheader', $name . ' joined theboise.it')

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there admin 👋</p>

    <p>There is a new user: <strong>{{ $name }}</strong>, {{ $email }}.</p>

    @if ($note)
        <p>Invite: {{ $note }}</p>
    @endif

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
