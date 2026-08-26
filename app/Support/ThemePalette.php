<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The raw palette, and the owner's overrides of it.
 *
 * What the owner edits here is the `--p-*` block at the top of
 * resources/css/app.css — twenty-one literal colours — and nothing else. The
 * twenty semantic roles (primary, accent, link, success, …) keep pointing at
 * those entries exactly as they do today, several of them through a
 * color-mix() toward or away from the surface. That split is deliberate and is
 * the whole reason this class exists in the shape it does: the derivations are
 * the part of the design system that was hardest to get right, and handing
 * them over one at a time would hand over the ability to put a mid-luminance
 * fill behind a mid-luminance label.
 *
 * Two consequences worth stating plainly:
 *
 * - **The defaults are read out of app.css, never restated here.** A second
 *   copy of twenty hex values is a second thing that can drift, and the one
 *   that drifts is always the copy nobody looks at. Only the *names* live in
 *   PHP, because the labels and the validation need them; ThemePaletteTest
 *   asserts the two agree, so a renamed entry fails the build rather than
 *   quietly disappearing from the screen.
 * - **Contrast is not checked here.** It cannot be: half the roles are
 *   color-mix() in a colour space this process has no renderer for, and the
 *   project's own rule (see the technical reference) is that contrast is measured
 *   in the browser against the real rendered stack, because the pass that
 *   estimated it from the palette was wrong about five roles at once. The gate
 *   lives in resources/js/lib/palette-contrast.ts, which measures every pair
 *   in both themes before the form will submit. What this class enforces is
 *   the other half, and that half is not optional: the value is emitted into a
 *   <style> block, so it is validated as an anchored hex colour on the way in,
 *   again on the way out of the database, and once more at the point of
 *   emission.
 */
class ThemePalette
{
    /**
     * The settings key. One row holding a map of overrides, rather than
     * twenty-one rows: an entry the owner has never touched must fall through
     * to the shipped colour, and "not in the map" says that with nothing to
     * interpret.
     */
    public const SETTING = 'theme_palette';

    /** The prefix every palette custom property carries in app.css. */
    public const PREFIX = '--p-';

    /**
     * Every palette entry, in the order app.css declares them, mapped to the
     * key of its label.
     *
     * The label key is a literal so that both dictionaries can be checked;
     * see ContentLanguage::SUPPORTED for the same arrangement. The *values*
     * are deliberately absent — they are read from the stylesheet.
     *
     * @var array<string, string>
     */
    public const ENTRIES = [
        'blue' => 'ui.theme.colours.blue',
        'blue-deep' => 'ui.theme.colours.blue-deep',
        'purple' => 'ui.theme.colours.purple',
        'purple-deep' => 'ui.theme.colours.purple-deep',
        'yellow' => 'ui.theme.colours.yellow',
        'yellow-deep' => 'ui.theme.colours.yellow-deep',
        'green' => 'ui.theme.colours.green',
        'green-deep' => 'ui.theme.colours.green-deep',
        'steel' => 'ui.theme.colours.steel',
        'steel-deep' => 'ui.theme.colours.steel-deep',
        'red' => 'ui.theme.colours.red',
        'red-deep' => 'ui.theme.colours.red-deep',
        'grey-50' => 'ui.theme.colours.grey-50',
        'grey-100' => 'ui.theme.colours.grey-100',
        'grey-400' => 'ui.theme.colours.grey-400',
        'grey-500' => 'ui.theme.colours.grey-500',
        'navy' => 'ui.theme.colours.navy',
        'navy-deep' => 'ui.theme.colours.navy-deep',
        'slate' => 'ui.theme.colours.slate',
        'slate-deep' => 'ui.theme.colours.slate-deep',
        'white' => 'ui.theme.colours.white',
    ];

    /**
     * Which palette entries app.blade.php paints the page with before app.css
     * has loaded. Overriding one of them has to move that block too, or a
     * rebranded site flashes the shipped background on every first paint —
     * which is the exact failure the block exists to prevent.
     *
     * @var array<string, string>
     */
    public const PAGE_BACKGROUND = [
        'light' => 'grey-50',
        'dark' => 'slate-deep',
    ];

    /**
     * A colour, and only a colour.
     *
     * Anchored at both ends, hex only: this value is written into a <style>
     * block, where a stray brace or a `url(` is not a rendering bug. Rejected
     * rather than sanitised — a value this pattern does not match is not a
     * colour somebody meant, it is a value somebody typed at the wrong screen.
     * Three, four, six or eight digits, which is every hex form CSS has.
     *
     * The `D` modifier is not decoration. Without it PCRE lets `$` match
     * before a trailing newline, so `#00a8ff\n` would pass as a colour — and a
     * newline is the one character that turns a declaration into two.
     */
    public const PATTERN = '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/D';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::ENTRIES);
    }

    public static function isColour(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * The shipped palette, read out of the stylesheet that defines it.
     *
     * Memoised in a static rather than through the cache: app.css ships inside
     * the image and cannot change while the process lives, so this is not the
     * kind of staleness the settings deliberately avoid — it is a constant
     * that happens to live in a file.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        static $defaults = null;

        if (is_array($defaults)) {
            return $defaults;
        }

        $stylesheet = @file_get_contents(resource_path('css/app.css'));
        $declared = [];

        if (is_string($stylesheet)) {
            preg_match_all(
                '/'.preg_quote(self::PREFIX, '/').'([a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/',
                $stylesheet,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                // First declaration wins. A palette entry is declared once, and
                // reading a later re-declaration would be reading a theme
                // rather than the palette.
                $declared[$match[1]] ??= strtolower($match[2]);
            }
        }

        $defaults = [];

        foreach (self::keys() as $key) {
            if (isset($declared[$key])) {
                $defaults[$key] = $declared[$key];
            }
        }

        return $defaults;
    }

    /**
     * Drop anything that is not a known entry holding a valid colour.
     *
     * Run on the way out of the database as well as on the way in, because a
     * settings row can also arrive from a restored archive or from somebody
     * with psql — and the value's next stop is a <style> block.
     *
     * @return array<string, string>
     */
    public static function normalise(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $clean = [];

        foreach (self::keys() as $key) {
            $candidate = $value[$key] ?? null;

            if (self::isColour($candidate)) {
                /** @var string $candidate */
                $clean[$key] = strtolower($candidate);
            }
        }

        return $clean;
    }

    /**
     * Only the entries that actually differ from the shipped palette.
     *
     * This is what makes an override an override. Storing an entry that equals
     * the shipped colour would look harmless and would quietly pin it: a later
     * version that improves the palette would leave that one entry behind, on
     * a site whose owner never chose it.
     *
     * @param  array<string, string>  $palette
     * @return array<string, string>
     */
    public static function onlyOverrides(array $palette): array
    {
        $defaults = self::defaults();

        return array_filter(
            self::normalise($palette),
            fn (string $value, string $key): bool => ($defaults[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The overrides in force.
     *
     * @return array<string, string>
     */
    public static function overrides(): array
    {
        return self::normalise(SiteSettings::get(self::SETTING));
    }

    /**
     * The palette as it actually renders: shipped, with the overrides applied.
     *
     * @return array<string, string>
     */
    public static function effective(): array
    {
        return [...self::defaults(), ...self::overrides()];
    }

    /**
     * The overriding <style> block for app.blade.php, or null when the owner
     * has changed nothing.
     *
     * `:root:root` rather than `:root`, and that doubled selector is
     * load-bearing. app.css declares the palette on `:root`; whether this
     * block or that stylesheet lands later in the document depends on how the
     * assets are delivered — the Vite dev server injects its CSS from
     * JavaScript, after everything Blade wrote. Equal specificity would make
     * the winner depend on that, which is a bug that only shows up in one of
     * the two setups. Doubling the selector settles it in both.
     *
     * Every value is checked against the pattern once more here. It has been
     * checked by the form request and again by normalise(); this is the layer
     * that sits at the point where a bad byte would actually matter, and it is
     * cheap.
     */
    public static function style(): ?HtmlString
    {
        $overrides = self::overrides();

        if ($overrides === []) {
            return null;
        }

        $declarations = [];

        foreach ($overrides as $key => $value) {
            if (! self::isColour($value)) {
                continue;
            }

            $declarations[] = '    '.self::PREFIX.$key.': '.$value.';';
        }

        if ($declarations === []) {
            return null;
        }

        $css = ":root:root {\n".implode("\n", $declarations)."\n}\n";

        // app.blade.php paints the page background before app.css is parsed.
        // A rebranded background has to move with it, or the shipped colour
        // flashes on every cold load.
        // isColour() again, exactly as the loop above: both write into the same
        // <style> block, so both need the same guard. Nothing malformed can
        // reach here today — normalise() already dropped it — but that is one
        // caller's behaviour, and this is the line that would become an
        // injection point the day it changes.
        foreach (self::PAGE_BACKGROUND as $theme => $key) {
            if (! isset($overrides[$key]) || ! self::isColour($overrides[$key])) {
                continue;
            }

            $selector = $theme === 'dark' ? 'html.dark' : 'html';

            $css .= $selector." {\n    background-color: ".$overrides[$key].";\n}\n";
        }

        return new HtmlString($css);
    }

    /**
     * The screen's own data: every entry, its shipped colour, the override if
     * there is one, and a translated label.
     *
     * @return list<array{key: string, label: string, default: string, value: string, overridden: bool}>
     */
    public static function forInertia(): array
    {
        $defaults = self::defaults();
        $overrides = self::overrides();

        $entries = [];

        foreach (self::ENTRIES as $key => $label) {
            if (! isset($defaults[$key])) {
                continue;
            }

            $entries[] = [
                'key' => $key,
                // A literal from a fixed list, the way ContentLanguage does
                // it: a key built from a variable cannot be checked, so the
                // variable holds the key rather than a fragment of one.
                'label' => (string) __($label),
                'default' => $defaults[$key],
                'value' => $overrides[$key] ?? $defaults[$key],
                'overridden' => isset($overrides[$key]),
            ];
        }

        return $entries;
    }
}
