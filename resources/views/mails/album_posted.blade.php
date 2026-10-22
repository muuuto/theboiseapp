@extends('mails.layouts.base')

@section('preheader', $postedBy . ' posted ' . $listing->title)

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello there {{ $name }} 👋</p>

    <p><strong>{{ $postedBy }}</strong> posted a new album with {{ $peopleCount }} {{ Str::plural('person', $peopleCount) }} in it. You're not in it, so this is just to let you know.</p>

    @include('mails.partials.album', ['listing' => $listing, 'note' => 'Posted by ' . $postedBy, 'label' => 'Have a look'])

    <p class="muted" style="font-size: 14px; color: #64748b;">Every change to it is in its history (album page, admins only). Who can post albums: maintenance page.</p>

    <p style="margin-bottom: 0;"><strong>theboise.it team</strong></p>
@endsection
