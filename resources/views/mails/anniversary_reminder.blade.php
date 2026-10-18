@extends('mails.layouts.base')

@section('preheader', 'A memory from ' . $listings->first()->title . ', ' . $listings->first()->diffInYears . ' ' . Str::plural('year', $listings->first()->diffInYears) . ' ago')

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello {{ $user->name }} 👋</p>

    <p>On this day, a while ago, you were here:</p>

    @foreach ($listings as $listing)
        @include('mails.partials.album', [
            'listing' => $listing,
            'note' => $listing->diffInYears . ' ' . Str::plural('year', $listing->diffInYears) . ' ago',
            'label' => 'Relive it',
        ])
    @endforeach

    <p style="margin-bottom: 0;">Thanks,<br><strong>theboise.it team</strong></p>
@endsection
