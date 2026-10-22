{{-- A button that looks like a button in every email app, Outlook too: @include('mails.partials.button', ['url' => ..., 'label' => ...]) --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 8px 0 20px;">
    <tr>
        <td bgcolor="{{ $color ?? '#ef3b2d' }}" style="border-radius: 12px; background: {{ $color ?? '#ef3b2d' }};">
            <a href="{{ $url }}" style="display: inline-block; padding: 13px 26px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight: bold; color: #ffffff; text-decoration: none; border-radius: 12px;">
                {{ __($label) }}
            </a>
        </td>
    </tr>
</table>
