@php
    $user = auth()->user();
    // null = follow the device; the demo account keeps its choice in the browser only
    $theme = $user && ! $user->is_demo && ! is_null($user->dark_mode) ? ($user->dark_mode ? 'dark' : 'light') : null;
@endphp
<!DOCTYPE html>
<html lang="en" @class(['dark' => $theme === 'dark'])>
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="description" content="{{ $user && ! $user->is_demo
            ? 'TheBoise: more than 100 albumses, find out if zecca is truly gay and visit the forumses.'
            : 'TheBoise: a private library of photo albums and memories.' }}" />
        <link rel="icon" href="{{ asset('images/logo.png') }}" />

        {{-- The app on the home screen: name, icon, colours (manifest for Android and computers, the rest for iPhone) --}}
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#0d9488" />
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="TheBoise" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />

        {{-- Before anything is drawn, so dark mode doesn't flash white --}}
        <script>
            (function () {
                var saved = @json($theme);
                var local = null;
                try { local = localStorage.getItem('theme'); } catch (e) {}
                var theme = saved || local || 'system';
                var dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
