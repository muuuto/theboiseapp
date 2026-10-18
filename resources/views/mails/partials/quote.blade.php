{{-- A comment in an email, already safe HTML: @include('mails.partials.quote', ['html' => ...]) --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">
    <tr>
        <td class="quote" style="background: #f1f5f9; border-left: 4px solid #0d9488; border-radius: 12px; padding: 14px 18px; font-size: 15px; line-height: 1.55; color: #1e293b;">
            {!! $html !!}
        </td>
    </tr>
</table>
