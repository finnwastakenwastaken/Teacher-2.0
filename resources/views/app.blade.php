<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Only `system` needs resolving in the browser; `dark` and `light`
             are already applied on the html element above. --}}
        <script>
            (function() {
                {{-- @json, not {{ }}. Blade's default escaping is for HTML,
                     and this is a JavaScript context: entities are not
                     decoded inside <script>, and htmlspecialchars leaves a
                     backslash alone, so a cookie ending in one used to escape
                     the closing quote and break this whole block. --}}
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

        {{-- The interface dictionary, active locale only.

             Here rather than as an Inertia shared prop for two reasons: a
             shared prop is re-sent on every visit, and this cannot change
             without a full page load anyway — switching language sets a
             cookie and reloads, because the lang attribute above and the
             title below are rendered by Blade.

             @json for the same reason as the appearance block: this is a
             JavaScript context, and Blade's {{ }} escapes for HTML. --}}
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
