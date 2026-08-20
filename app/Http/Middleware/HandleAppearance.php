<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
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
        View::share('appearance', $request->cookie('appearance') ?? 'dark');

        return $next($request);
    }
}
