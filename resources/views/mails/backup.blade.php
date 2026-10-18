@extends('mails.layouts.base')

@section('preheader', 'Weekly database backup ' . basename($file))

@section('content')
    <p style="font-size: 22px; font-weight: bold; margin: 0 0 12px;">Hello 👋</p>

    <p>Attached is the weekly backup of the theboise.it database (<strong>{{ basename($file) }}</strong>).</p>

    <p>Keep it somewhere safe: it contains every album, post and comment, plus users' emails.</p>

    <p style="margin-bottom: 0;">Thanks,<br><strong>theboise.it team</strong></p>
@endsection
