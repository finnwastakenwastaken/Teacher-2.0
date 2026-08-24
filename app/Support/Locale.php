<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Which language the interface is drawn in.
 *
 * Only the interface. What the owner writes — page titles, bodies, download
 * labels, education-level names, the site's own name — is stored once, in
 * whatever language it was written in, and is never translated or duplicated.
 * A visitor switching to English gets English chrome around Dutch lessons,
 * which is the honest thing to show: the alternative is an empty site or a
 * machine translation of somebody's teaching material.
 *
 * The choice is a cookie and nothing else, like the theme and the "mijn
 * niveau" preference. There is no per-visitor record on the server, and no
 * language segment in any URL — content is single-language, so /en/… and /…
 * would serve identical pages under two addresses, at the cost of teaching
 * the catch-all route, the slug redirects, robots.txt and the sitemap about a
 * prefix that carries no information.
 */
class Locale
{
    /**
     * Every locale with a lang/ directory. Adding one here without the
     * directory fails LocalisationTest immediately, which is the right way
     * round — a half-added language renders raw key paths on screen.
     */
    public const SUPPORTED = ['nl', 'en'];

    /**
     * Used when the visitor has expressed no preference and their browser
     * asks for nothing we speak. Dutch, because that is who the site is for.
     */
    public const DEFAULT = 'nl';

    public const COOKIE = 'locale';

    /**
     * A year. The preference is not sensitive and re-asking a returning
     * visitor every month would be worse than remembering.
     */
    public const COOKIE_LIFETIME = 60 * 24 * 365;

    /**
     * An explicit choice wins; a browser's advertised preference decides for
     * anyone who has not made one; Dutch decides for anyone left.
     *
     * Validated against the supported list rather than trusted, for the same
     * reason HandleAppearance validates its cookie: the value ends up inside
     * a JavaScript string literal in app.blade.php, where Blade's {{ }} is
     * HTML escaping and therefore the wrong context.
     */
    public static function resolve(Request $request): string
    {
        $chosen = $request->cookie(self::COOKIE);

        if (self::isSupported($chosen)) {
            /** @var string $chosen */
            return $chosen;
        }

        return $request->getPreferredLanguage(self::SUPPORTED) ?? self::DEFAULT;
    }

    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::SUPPORTED, true);
    }

    /**
     * The only groups the browser is given.
     *
     * An allow-list rather than a list of exclusions, because the cost of
     * getting it wrong runs one way: a group added for the server would
     * otherwise start riding along on every document silently. `validation`
     * alone is about 170 lines, and none of it is ever read there —
     * validation and authentication messages arrive already rendered, as
     * strings in the error bag.
     */
    private const CLIENT_GROUPS = ['common', 'ui'];

    /**
     * The interface dictionary for one locale, flattened to dotted keys.
     *
     * Sent to the browser once per document rather than as an Inertia shared
     * prop, because a shared prop is re-sent on every visit and this cannot
     * change without a full page load anyway — switching language sets a
     * cookie and reloads, since the <html lang> attribute and the document
     * title are rendered by Blade.
     *
     * Only the active locale is included. Shipping both would double it for
     * nobody's benefit.
     *
     * @return array<string, string>
     */
    public static function dictionary(string $locale): array
    {
        $messages = [];

        foreach (File::glob(lang_path($locale.'/*.php')) as $path) {
            $group = basename($path, '.php');

            if (! in_array($group, self::CLIENT_GROUPS, true)) {
                continue;
            }

            $lines = require $path;

            if (! is_array($lines)) {
                continue;
            }

            $messages = [...$messages, ...self::flatten($lines, $group)];
        }

        return $messages;
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<string, string>
     */
    private static function flatten(array $lines, string $prefix): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $path = $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = [...$flat, ...self::flatten($value, $path)];

                continue;
            }

            if (is_string($value)) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
