<?php

use App\Http\Middleware\EnsureAdminNotClaimed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
        // Trusting all proxies is safe here specifically because nothing but
        // our own nginx can reach PHP-FPM; it is not exposed to the network.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'admin.unclaimed' => EnsureAdminNotClaimed::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
