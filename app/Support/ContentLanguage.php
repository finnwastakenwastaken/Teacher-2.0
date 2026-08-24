<?php

namespace App\Support;

/**
 * Which language the teacher *writes* in.
 *
 * Deliberately not the visitor's interface locale, and the two must never be
 * conflated. `pages.search_vector` is one stored column: it is stemmed once,
 * when the page is saved, by whichever PostgreSQL text-search configuration
 * the trigger picks. Stemming it per visitor is not a thing Postgres can do,
 * and it would be the wrong answer anyway — a Dutch worksheet is still Dutch
 * when an English-reading visitor searches for it.
 *
 * So: the interface follows the visitor (App\Support\Locale), and the corpus
 * follows the owner (this).
 *
 * The value is a PostgreSQL configuration name, not a locale code. They are
 * different vocabularies — `nl` is a locale, `dutch` is a `pg_ts_config` row —
 * and the migration looks the stored value up in that catalogue rather than
 * casting it, so an unknown value falls back instead of throwing on every
 * page save.
 */
class ContentLanguage
{
    public const SETTING = 'content_language';

    public const DEFAULT = 'dutch';

    /**
     * The configurations offered on the settings screen.
     *
     * A short list rather than everything `pg_ts_config` holds: these are the
     * two languages the interface ships, and offering `simple` — which does no
     * stemming at all — would look like a reasonable choice and quietly make
     * search worse in both.
     *
     * Each maps to the label key describing it. Widening this list means
     * adding the label to both dictionaries; the configuration itself already
     * exists in any standard PostgreSQL build.
     *
     * @var array<string, string>
     */
    public const SUPPORTED = [
        'dutch' => 'ui.site.content_language_dutch',
        'english' => 'ui.site.content_language_english',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::SUPPORTED);
    }

    /**
     * The configuration in force, guaranteed to be one this application
     * offers. A row written by hand — or left behind by a version that
     * offered more — resolves to the default rather than reaching SQL.
     */
    public static function current(): string
    {
        $value = SiteSettings::get(self::SETTING);

        return is_string($value) && array_key_exists($value, self::SUPPORTED)
            ? $value
            : self::DEFAULT;
    }

    /**
     * The choices as the settings screen needs them.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, string $label): array => [
                'value' => $value,
                'label' => (string) __($label),
            ],
            self::names(),
            array_values(self::SUPPORTED),
        );
    }
}
