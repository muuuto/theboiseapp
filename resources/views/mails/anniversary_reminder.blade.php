@extends('mails.layouts.base')

@section('preheader', trans_choice('A memory from :title, :count year ago|A memory from :title, :count years ago', $listings->first()->diffInYears, ['title' => $listings->first()->title]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello :name 👋', ['name' => $user->name]) }}</p>

    <p>{{ __('On this day, a while ago, you were here:') }}</p>

    @foreach ($listings as $listing)
        @include('mails.partials.album', [
            'listing' => $listing,
            'note' => trans_choice(':count year ago|:count years ago', $listing->diffInYears),
            'label' => 'Relive it',
        ])
    @endforeach

    <p style="margin-bottom: 0;">{{ __('Thanks,') }}<br><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
