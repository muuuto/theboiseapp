@extends('mails.layouts.base')

@section('preheader', 'Someone asked to reset your password')

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p>Someone (hopefully you) asked to reset the password of your theboise.it account. Choose a new one here:</p>

    @include('mails.partials.button', ['url' => $link, 'label' => 'Choose a new password'])

    <p class="muted" style="font-size: 14px; color: #64748b;">The link works for {{ $minutes }} minutes. If you didn't ask for it, ignore this email: your password stays the same.</p>

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
