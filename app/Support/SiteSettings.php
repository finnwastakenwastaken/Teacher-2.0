<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Setting;

/**
 * The site's branding and homepage copy. This class owns the defaults;
 * `settings` rows are overrides, so resetting a setting is a delete rather
 * than a guess at what it used to say. Deliberately uncached — a stale
 * answer here is the owner changing the site title and not seeing it
 * change. If this ever shows up in a profile, cache per request, never
 * statically (would leak between requests in the test suite).
 */
class SiteSettings
{
    /**
     * Every setting the owner can edit, with the value used when unset.
     *
     * Image settings hold an `images.id`. They are ids rather than paths
     * because the bytes are on the private disk and are served through
     * App\Support\MediaAccess like everything else — see brandingImageIds().
     */
    /*
     * The Dutch strings below are deliberately not translated: they are
     * homepage content, stored once in whatever language the owner writes
     * it in. Running them through __() would make an unedited heading
     * change language as the visitor switches interface locale.
     */
    public const DEFAULTS = [
        'site_title' => null,          // null => config('app.name')
        'site_logo_image_id' => null,
        'site_favicon_image_id' => null,
        'home_heading' => 'Lesmateriaal',
        'home_subheading' => 'Bekijk en download lesmateriaal per onderwerp.',
        'home_banner_image_id' => null,
        'home_content' => null,        // A TipTap document, or null.
        // An optional addition to the privacy page — a contact address, a
        // school's own policy. The page itself is the application's words and
        // is translated; this is the owner's and is not. Null means the
        // section is simply absent, so a fresh install reads correctly with
        // nothing configured.
        'privacy_content' => null,     // A TipTap document, or null.
        // A PostgreSQL text-search configuration name, not a locale code —
        // the language the owner writes in, which is a different question
        // from the language a visitor reads the interface in. See
        // App\Support\ContentLanguage.
        'content_language' => ContentLanguage::DEFAULT,
        // The owner's overrides of the raw palette in resources/css/app.css,
        // as a map of entry name to hex colour. Empty means "the shipped
        // palette", which is why an unset entry has nothing to fall back
        // *to* — it simply is not written, and the stylesheet decides. See
        // App\Support\ThemePalette.
        ThemePalette::SETTING => [],
    ];

    /**
     * The image settings, which is what makes an image reachable without
     * being on any page. Kept as one list so MediaAccess and the delete
     * guard on Image cannot drift apart from this class.
     */
    public const IMAGE_KEYS = [
        'site_logo_image_id',
        'site_favicon_image_id',
        'home_banner_image_id',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $stored = Setting::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->pluck('value', 'key')
            ->all();

        $values = [...self::DEFAULTS, ...$stored];

        $values['site_title'] = filled($values['site_title'])
            ? $values['site_title']
            : config('app.name');

        // Image ids arrive from an HTML form, so they are stored as the
        // strings "7" rather than the number 7. Normalising here rather than
        // at every reader is what keeps `===` comparisons honest — both the
        // in_array() check that publishes a branding image and the picker's
        // id match in React would silently miss otherwise.
        foreach (self::IMAGE_KEYS as $key) {
            $values[$key] = self::asId($values[$key] ?? null);
        }

        // Same reasoning one type further along: the palette is the only
        // setting that is not a scalar, and every byte of it ends up inside a
        // <style> block. Normalising here means no reader has to remember —
        // an unknown entry or a value that is not a hex colour is gone before
        // anything can render it, whether it came from the form, from a
        // restored archive, or from somebody with psql.
        $values[ThemePalette::SETTING] = ThemePalette::normalise(
            $values[ThemePalette::SETTING] ?? []
        );

        return $values;
    }

    private static function asId(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : null;
    }

    public static function get(string $key): mixed
    {
        return static::all()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Drop an override entirely, so the default decides again.
     *
     * A row holding the default value would render identically today and
     * silently pin the setting: a later version that changes the default
     * would leave this site behind on a value its owner never chose. Deleting
     * is the only way to say "I have no opinion about this".
     */
    public static function forget(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        Setting::query()->whereKey($key)->delete();
    }

    /**
     * Image ids currently used as branding.
     *
     * @return array<int, int>
     */
    public static function brandingImageIds(): array
    {
        $values = static::all();

        return collect(self::IMAGE_KEYS)
            ->map(fn (string $key) => $values[$key] ?? null)
            ->filter(fn (?int $id) => $id !== null)
            ->values()
            ->all();
    }

    /**
     * The shape the frontend needs: branding resolved to renderable images.
     *
     * @return array<string, mixed>
     */
    public static function forInertia(): array
    {
        $values = static::all();
        $images = static::resolveImages([
            $values['site_logo_image_id'] ?? null,
            $values['site_favicon_image_id'] ?? null,
        ]);

        return [
            'title' => $values['site_title'],
            'logo' => $images[$values['site_logo_image_id'] ?? null] ?? null,
            'favicon' => $images[$values['site_favicon_image_id'] ?? null] ?? null,
        ];
    }

    /**
     * @param  list<int|null>  $ids
     * @return array<int, array{ulid: string, alt: string, url: string}>
     */
    public static function resolveImages(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Image::query()
            ->whereIn('id', $ids)
            ->get(['id', 'ulid', 'alt_text'])
            ->mapWithKeys(fn (Image $image) => [$image->id => [
                'ulid' => $image->ulid,
                'alt' => $image->alt_text,
                'url' => route('images.show', $image),
            ]])
            ->all();
    }
}
