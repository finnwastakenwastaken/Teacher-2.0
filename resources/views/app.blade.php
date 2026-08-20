<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Only `system` needs resolving in the browser; `dark` and `light`
             are already applied on the html element above. --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "dark" }}';

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
