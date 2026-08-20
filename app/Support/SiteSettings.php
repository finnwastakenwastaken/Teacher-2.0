<?php

namespace App\Support;

use App\Models\Image;
use App\Models\Setting;

/**
 * The site's branding and homepage copy.
 *
 * This class owns the defaults. `settings` rows are overrides, so a fresh
 * install renders correctly with an empty table and nothing has to be
 * seeded — which also means resetting a setting is a delete, not a guess at
 * what it used to say.
 *
 * Deliberately uncached. Every request reads a handful of rows in one query,
 * and a cache here would buy a millisecond in exchange for the owner
 * changing the site title and not seeing it change. If this ever shows up in
 * a profile, cache it in the container per request — never statically, which
 * would leak between requests in the test suite.
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
    public const DEFAULTS = [
        'site_title' => null,          // null => config('app.name')
        'site_logo_image_id' => null,
        'site_favicon_image_id' => null,
        'home_heading' => 'Lesmateriaal',
        'home_subheading' => 'Bekijk en download lesmateriaal per onderwerp.',
        'home_banner_image_id' => null,
        'home_content' => null,        // A TipTap document, or null.
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
            ->whereIn('key', array_keys(static::DEFAULTS))
            ->pluck('value', 'key')
            ->all();

        $values = [...static::DEFAULTS, ...$stored];

        $values['site_title'] = filled($values['site_title'])
            ? $values['site_title']
            : config('app.name');

        // Image ids arrive from an HTML form, so they are stored as the
        // strings "7" rather than the number 7. Normalising here rather than
        // at every reader is what keeps `===` comparisons honest — both the
        // in_array() check that publishes a branding image and the picker's
        // id match in React would silently miss otherwise.
        foreach (static::IMAGE_KEYS as $key) {
            $values[$key] = static::asId($values[$key] ?? null);
        }

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
            if (! array_key_exists($key, static::DEFAULTS)) {
                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Image ids currently used as branding.
     *
     * @return list<int>
     */
    public static function brandingImageIds(): array
    {
        $values = static::all();

        return collect(static::IMAGE_KEYS)
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
