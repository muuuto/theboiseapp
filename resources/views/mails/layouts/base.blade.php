{{--
    Shared look of every email. Email apps ignore most modern CSS: tables, inline styles, max 600px.
    Sections: preheader (the grey preview line in the inbox) and content.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>TheBoise</title>
    <style>
        body { margin: 0; padding: 0; }
        a { color: #ef3b2d; }
        .content p { margin: 0 0 16px; }
        .content blockquote { margin: 0 0 16px; }
        .quote p:last-child { margin-bottom: 0; }
        @media (max-width: 620px) {
            .card { padding: 24px 18px !important; }
        }
        /* Apple Mail and others with dark mode support */
        @media (prefers-color-scheme: dark) {
            .page { background: #020617 !important; }
            .card { background: #0f172a !important; color: #e2e8f0 !important; }
            .muted { color: #94a3b8 !important; }
            .quote { background: #1e293b !important; color: #e2e8f0 !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background: #f1f5f9;">
    <span style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">@yield('preheader')</span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="page" style="background: #f1f5f9;">
        <tr>
            <td align="center" style="padding: 24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    {{-- Header --}}
                    <tr>
                        <td style="background: #0d9488; border-radius: 20px 20px 0 0; padding: 22px 28px;">
                            <a href="{{ url('/') }}" style="text-decoration: none;">
                                <img src="{{ url('/images/logo.png') }}" width="36" height="36" alt="" style="vertical-align: middle; border: 0; border-radius: 6px;">
                                <span style="vertical-align: middle; margin-left: 8px; font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; color: #ffffff;">THE<span style="color: #0f172a;">BOISE</span></span>
                            </a>
                        </td>
                    </tr>
                    {{-- Content --}}
                    <tr>
                        <td class="card content" style="background: #ffffff; border-radius: 0 0 20px 20px; padding: 32px 28px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.55; color: #1e293b;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td class="muted" style="padding: 20px 28px; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.5; color: #64748b;">
                            You get this email because you're part of
                            <a href="{{ url('/') }}" style="color: #64748b; font-weight: bold;">theboise.it</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
