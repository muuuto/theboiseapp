@extends('mails.layouts.base')

@section('preheader', __('Weekly database backup :file', ['file' => basename($file)]))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">{{ __('Hello 👋') }}</p>

    <p>{!! __('Attached is the weekly backup of the theboise.it database (:file).', ['file' => '<strong>' . e(basename($file)) . '</strong>']) !!}</p>

    <p>{{ __("Keep it somewhere safe: it contains every album, post and comment, plus users' emails.") }}</p>

    <p style="margin-bottom: 0;">{{ __('Thanks,') }}<br><strong>{{ __('theboise.it team') }}</strong></p>
@endsection
