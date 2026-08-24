<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /** The only values the front end ever writes. */
    private const APPEARANCES = ['light', 'dark', 'system'];

    private const DEFAULT_APPEARANCE = 'dark';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Dark is the site default, so a visitor with no stored preference is
        // served dark markup from the very first byte. Keep this in sync with
        // DEFAULT_APPEARANCE in resources/js/hooks/use-appearance.tsx —
        // a mismatch shows up as a visible flash on first paint.
        //
        // Validated here rather than trusted at the point of use. This cookie
        // is set by JavaScript, is in the encryptCookies exception list, and
        // is therefore neither signed nor encrypted: it is whatever the
        // browser says it is. It then lands inside a JavaScript string
        // literal in app.blade.php, where Blade's {{ }} applies *HTML*
        // escaping — the wrong context. Entities are not decoded inside
        // <script>, so quote-breakout was blocked, but htmlspecialchars does
        // not touch a backslash: a value ending in one escaped the closing
        // quote and turned the whole first-paint script into a parse error.
        // It is an enum of three values, so treat it as one.
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, self::APPEARANCES, true)
            ? $appearance
            : self::DEFAULT_APPEARANCE);

        return $next($request);
    }
}
