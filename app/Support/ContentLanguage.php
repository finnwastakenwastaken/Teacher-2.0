<?php

namespace App\Support;

/**
 * Which language the teacher *writes* in — never to be conflated with the
 * visitor's interface locale (App\Support\Locale). `pages.search_vector` is
 * stemmed once at save time using this setting; a Dutch worksheet stays
 * Dutch regardless of who searches for it. The value is a PostgreSQL
 * configuration name (`dutch`), not a locale code (`nl`) — the migration
 * looks it up rather than casting it, so an unknown value falls back
 * instead of throwing on every page save.
 */
class ContentLanguage
{
    public const SETTING = 'content_language';

    public const DEFAULT = 'dutch';

    /**
     * The configurations offered on the settings screen — a short list, not
     * everything `pg_ts_config` holds. `simple` (no stemming) would look
     * reasonable and quietly make search worse in both languages.
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
