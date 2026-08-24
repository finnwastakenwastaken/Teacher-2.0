<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Switching the interface language.
 *
 * A POST rather than a link, and set here rather than by JavaScript, so the
 * value is validated once on the way in and the cookie can stay encrypted
 * like every other cookie in the application. The `appearance` cookie is in
 * the encryptCookies exception list only because the front end writes it;
 * nothing needs to read this one from JavaScript, because the dictionary
 * already arrives with the document.
 *
 * The redirect is what makes the switch a full page load, which it has to be:
 * <html lang> and the document title come from Blade, and an Inertia visit
 * would leave both saying the previous language.
 */
class LocaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locale::SUPPORTED)],
        ]);

        Cookie::queue(
            Locale::COOKIE,
            $validated['locale'],
            Locale::COOKIE_LIFETIME,
        );

        return back();
    }
}
