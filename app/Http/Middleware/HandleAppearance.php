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
        // Dark is the site default; keep this in sync with
        // DEFAULT_APPEARANCE in resources/js/hooks/use-appearance.tsx or a
        // mismatch flashes on first paint.
        //
        // Validated rather than trusted: this cookie is JS-set, unsigned and
        // unencrypted, then lands inside a JS string literal in
        // app.blade.php where Blade's {{ }} applies HTML escaping — the
        // wrong context. htmlspecialchars doesn't touch a backslash, so a
        // value ending in one broke out of the quote and threw a parse
        // error in the first-paint script. Treat it as the enum of three
        // values it is.
        $appearance = $request->cookie('appearance');

        View::share('appearance', in_array($appearance, self::APPEARANCES, true)
            ? $appearance
            : self::DEFAULT_APPEARANCE);

        return $next($request);
    }
}
