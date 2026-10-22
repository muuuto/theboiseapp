@extends('mails.layouts.base')

@section('preheader', $wrapped['albumCount'] . ' albums, ' . $wrapped['daysAway'] . ' days away, ' . number_format($wrapped['km']) . ' km. Your year is ready.')

@section('content')
    <p style="font-size: 44px; line-height: 1; margin: 0 0 12px;">🎁</p>
    <p style="font-size: 24px; font-weight: bold; margin: 0 0 12px;">Your {{ $wrapped['year'] }} on TheBoise is ready, {{ $name }}!</p>

    <p>Albums, places, friends, your most liked comment: here's a little taste.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">
        <tr>
            @foreach ([[$wrapped['albumCount'], 'albums'], [$wrapped['daysAway'], 'days away'], [number_format($wrapped['km']), 'km travelled']] as [$value, $label])
                <td width="33%" class="quote" style="background: #f0fdfa; border-radius: 14px; padding: 14px 8px; text-align: center;">
                    <span style="display: block; font-size: 28px; font-weight: bold; color: #0d9488;">{{ $value }}</span>
                    <span class="muted" style="font-size: 13px; color: #64748b;">{{ $label }}</span>
                </td>
                @if (! $loop->last)<td width="8">&nbsp;</td>@endif
            @endforeach
        </tr>
    </table>

    @if ($wrapped['countries']->isNotEmpty())
        <p style="font-size: 30px; margin: 0 0 16px;">{{ $wrapped['countries']->map(fn ($country) => $country['flag'] ?? '🏳️')->join(' ') }}</p>
    @endif

    @include('mails.partials.button', ['url' => $link, 'label' => 'See your Wrapped'])

    <p style="margin-bottom: 0;">Happy holidays,<br><strong>theboise.it team</strong></p>
@endsection
