<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Only `system` needs resolving in the browser; `dark` and `light`
             are already applied on the html element above. --}}
        <script>
            (function() {
                {{-- @json, not {{ }} — this is a JS context, and Blade's HTML
                     escaping leaves a trailing backslash alone, letting it
                     escape the closing quote. --}}
                const appearance = @json($appearance ?? 'dark');

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    document.documentElement.classList.toggle('dark', prefersDark);
                }
            })();
        </script>

        {{-- Paints the page background before app.css loads, so there is no
             white flash on a dark-themed site. These two values must match
             --p-grey-50 and --p-slate-deep in resources/css/app.css. --}}
        <style>
            html {
                background-color: #f5f6fa;
            }

            html.dark {
                background-color: #2f3640;
            }
        </style>

        {{-- The owner's overrides of the raw palette, if there are any.

             Here, before anything else, for the same reason as the two rules
             above: these are the custom properties the whole document is
             styled from, so arriving with the document is the difference
             between a rebranded site and one that paints the shipped colours
             first and repaints. React cannot do this job at all.

             The value is an Htmlable built by App\Support\ThemePalette, so
             {{ }} passes it through rather than escaping it — which is right,
             because HTML entities are not decoded inside <style> and escaping
             would corrupt CSS rather than protect it. What makes that safe is
             upstream and threefold: the form request refuses anything that is
             not an anchored hex colour, SiteSettings::all() refuses it again
             on the way out of the database, and style() refuses it once more
             at the point of emission. --}}
        @if ($themePaletteStyle)
            <style>{{ $themePaletteStyle }}</style>
        @endif

        {{-- Branding comes from the settings the owner edits, falling back
             to the shipped files. Rendered here rather than from React so the
             tab has the right name and icon before hydration. --}}
        @if ($siteBranding['favicon'])
            <link rel="icon" href="{{ $siteBranding['favicon']['url'] }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        {{-- Read by resources/js/app.tsx for the document title template. --}}
        <meta name="app-name" content="{{ $siteBranding['title'] }}">

        {{-- Interface dictionary, active locale only. A Blade global rather
             than an Inertia shared prop because it can't change without a
             full page load anyway (switching sets a cookie and reloads). --}}
        <script>
            window.__translations = @json($translations ?? []);
            window.__locale = @json($locale ?? 'nl');
        </script>

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $siteBranding['title'] }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
