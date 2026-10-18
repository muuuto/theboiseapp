{{-- An album in an email: cover (JPEG, works in Outlook too), title, dates, button.
     @include('mails.partials.album', ['listing' => $listing, 'label' => 'View album', 'note' => '...']) --}}
@php
    $cover = \App\Support\MailCover::url($listing);
    $from = \Carbon\Carbon::parse($listing->dateFrom);
    $to = \Carbon\Carbon::parse($listing->dateTo);
    $dates = $from->isSameDay($to) ? $from->format('j F Y') : $from->format('j F Y') . ' – ' . $to->format('j F Y');
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 4px 0 24px; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;">
    @if ($cover)
        <tr>
            <td>
                <a href="{{ url('/listings/' . $listing->id) }}">
                    <img src="{{ $cover }}" width="600" alt="{{ $listing->title }}" style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">
                </a>
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding: 16px 18px 4px;">
            @isset($note)
                <p class="muted" style="margin: 0 0 4px; font-size: 13px; font-weight: bold; color: #0d9488; text-transform: uppercase; letter-spacing: 0.5px;">{{ $note }}</p>
            @endisset
            <p style="margin: 0; font-size: 19px; font-weight: bold; color: inherit;">{{ $listing->title }}</p>
            <p class="muted" style="margin: 2px 0 12px; font-size: 14px; color: #64748b;">{{ $dates }} · {{ $listing->location }}</p>
            @include('mails.partials.button', ['url' => url('/listings/' . $listing->id), 'label' => $label ?? 'View album'])
        </td>
    </tr>
</table>
