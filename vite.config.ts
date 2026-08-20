import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        // The dev container binds 0.0.0.0 (see compose.dev.yaml's `--host
        // 0.0.0.0`) so it's reachable from outside the container at all.
        // Without `origin` set explicitly, laravel-vite-plugin writes that
        // same bind address into public/hot and uses it to build every
        // asset/HMR script URL — the browser then tries to fetch
        // http://0.0.0.0:5173/... , which is not a connectable address from
        // outside the container it was bound in. Forcing origin to the
        // host-mapped, browser-reachable URL fixes both the initial asset
        // tags and the HMR websocket.
        origin: 'http://localhost:5173',
        // laravel-vite-plugin defaults its CORS allow-origin to whatever
        // `server.origin` is set to, on the assumption that's also the page
        // origin. Here they differ — the page loads from nginx on :8080,
        // fetching assets from Vite on :5173 — so without this, setting
        // `origin` above narrows CORS to itself and the browser refuses every
        // asset request. `true` restores permissive dev-only CORS.
        cors: true,
        watch: {
            // The project is bind-mounted from Windows into a Linux
            // container, and inotify events do not cross that boundary — the
            // container never learns a file changed. Vite then keeps serving
            // the module it transformed the first time, and because its
            // transform cache lives under node_modules (a named volume), even
            // restarting the service does not clear it. The symptom is nasty
            // to diagnose: edits simply have no effect, with no error
            // anywhere, while the file inside the container is demonstrably
            // correct and `tsc` sees the new code.
            //
            // Polling costs some CPU and is the only thing that works here.
            usePolling: true,
            interval: 300,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
