<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Switching the interface language. A POST set here rather than by
 * JavaScript, so the value is validated once and the cookie stays encrypted
 * like every other one. The redirect forces a full page load — <html lang>
 * and the document title come from Blade, and an Inertia visit would leave
 * both showing the previous language.
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
