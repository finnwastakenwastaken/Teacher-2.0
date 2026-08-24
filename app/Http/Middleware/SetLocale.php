<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide the interface language for this request, before anything renders.
 *
 * The same arrangement as HandleAppearance, and for the same reason: the
 * answer has to be in the very first byte of the document. <html lang> and
 * the document title are written by Blade, so a language resolved in the
 * browser would mean a visible flash of the wrong one on every first load,
 * and a wrong `lang` attribute in between — which is what a screen reader
 * chooses its pronunciation from.
 *
 * Registered before HandleInertiaRequests in bootstrap/app.php, so anything
 * shared with Inertia is already translated.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::resolve($request);

        app()->setLocale($locale);

        // Carbon reads its own locale, not the application's, and this is
        // what makes diffForHumans() say "2 hours ago" rather than
        // "2 uur geleden" on the backups and passkey screens.
        CarbonImmutable::setLocale($locale);

        View::share('locale', $locale);

        // Handed to the browser as one JSON blob in app.blade.php rather than
        // as an Inertia shared prop — see Locale::dictionary() for why.
        View::share('translations', Locale::dictionary($locale));

        return $next($request);
    }
}
