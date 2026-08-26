<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Which language the interface is drawn in — interface only. Owner-written
 * content (page titles, bodies, download labels, level names) is never
 * translated or duplicated; a visitor switching to English gets English
 * chrome around Dutch lessons. The choice is a cookie only, like theme and
 * "mijn niveau" — no server-side record, no URL language segment.
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
     * Cookie, then browser preference, then Dutch. Validated against
     * SUPPORTED rather than trusted — the value lands in a JS string literal
     * in app.blade.php, where Blade's `{{ }}` is the wrong escaping context.
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
     * The only groups the browser is given — an allow-list, so a group added
     * for the server doesn't silently ride along on every document.
     * `validation`/`auth` arrive already rendered in the error bag instead.
     */
    private const CLIENT_GROUPS = ['common', 'ui'];

    /**
     * The interface dictionary for one locale, flattened to dotted keys. A
     * Blade global rather than an Inertia shared prop — switching language
     * already forces a full reload (cookie + Blade-rendered `<html lang>`),
     * so re-sending it on every visit would be pure waste. Only the active
     * locale ships.
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
