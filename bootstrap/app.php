<?php

use App\Http\Middleware\EnsureAdminNotClaimed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app never faces the internet directly. Requests arrive through
        // nginx, and in production through a Cloudflare Tunnel before that.
        //
        // Without this, every request appears to originate from the proxy
        // container, which quietly breaks two things: HTTPS detection (so
        // generated URLs come out as http://), and every per-IP rate limit —
        // login attempts and page-password guesses would share a single
        // bucket, meaning one attacker could lock out the whole school while
        // brute-force protection stopped being per-visitor at all.
        //
        // `at: '*'` does NOT mean "trust every proxy in the chain": Laravel
        // maps it to the calling IP, so exactly one hop is trusted — our own
        // nginx, which is the only thing that can reach PHP-FPM. What makes
        // that safe is the other half of the arrangement, in
        // docker/nginx/app.conf: nginx overwrites the X-Forwarded-* headers
        // with what it actually observed, so the values arriving here are
        // never the visitor's own. Neither half works alone.
        //
        // X-Forwarded-Host is deliberately absent from this list. Laravel
        // trusts it by default, Symfony's getHost() takes the *first* value,
        // and there is no trustHosts() here to catch it — so with it enabled
        // a single request header rewrote every absolute URL the site
        // generates, the Sitemap: line in robots.txt included. Nothing needs
        // it: nginx passes the real Host, and behind the tunnel cloudflared
        // sets that to the public hostname already. Adding it back reopens
        // the hole even with the nginx side in place.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'admin.unclaimed' => EnsureAdminNotClaimed::class,
        ]);

        // SetLocale before HandleInertiaRequests, so everything shared with
        // Inertia — and everything Blade renders around it — is already in
        // the right language. Both of these have to be settled before the
        // first byte or the page flashes; see the comments in each class.
        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
