<?php

namespace App\Support;

use App\Models\Icon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The single lookup point for icon geometry.
 *
 * Everything that renders an icon goes through here, so there is one place
 * that knows how a stored icon value maps to a catalogue row — and one place
 * to change if the catalogue ever moves.
 */
class IconCatalogue
{
    /**
     * The library used when a stored value carries no prefix. Icons chosen
     * before the catalogue existed are bare names ("atom"); those rows are
     * never migrated, so unprefixed permanently means lucide.
     */
    public const DEFAULT_LIBRARY = 'lucide';

    /**
     * Libraries in the order the picker offers them, with the licence each
     * one ships under. Shown in the picker so the owner can see where an
     * icon came from.
     *
     * @var array<string, array{label: string, licence: string}>
     */
    public const LIBRARIES = [
        'lucide' => ['label' => 'Lucide', 'licence' => 'ISC'],
        'tabler' => ['label' => 'Tabler', 'licence' => 'MIT'],
        // The only label that is a word rather than a name, so the only one
        // translated — see libraries(). "Lucide", "Tabler" and "Material
        // Design Icons" are what those projects call themselves.
        'tabler-filled' => ['label' => 'admin.icons.tabler_filled', 'licence' => 'MIT'],
        'mdi' => ['label' => 'Material Design Icons', 'licence' => 'Apache-2.0'],
    ];

    /**
     * Libraries whose icons are drawn as filled shapes rather than strokes.
     *
     * This decides the root SVG attributes, not the geometry, which is why it
     * is a property of the library and not of the icon.
     *
     * @var list<string>
     */
    public const FILLED_LIBRARIES = ['mdi', 'tabler-filled'];

    /**
     * Turn a stored icon value into a catalogue key.
     */
    public static function normalise(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return str_contains($value, ':') ? $value : self::DEFAULT_LIBRARY.':'.$value;
    }

    /**
     * Geometry for the given stored icon values, keyed by the value as it was
     * passed in — so a caller can look up what it already holds without
     * having to normalise first.
     *
     * Unknown icons are simply absent from the result. A renamed or removed
     * icon then draws nothing, which is the right failure: a missing glyph is
     * a cosmetic problem, an exception on a public page is not.
     *
     * @param  iterable<string|null>  $values
     * @return array<string, array{library: string, nodes: array<int, mixed>}>
     */
    public static function resolve(iterable $values): array
    {
        $wanted = collect($values)
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $value) => [$value => self::normalise($value)])
            ->filter();

        if ($wanted->isEmpty()) {
            return [];
        }

        $rows = Icon::query()
            ->whereIn('key', $wanted->values()->unique()->all())
            ->get(['key', 'library', 'nodes'])
            ->keyBy('key');

        return $wanted
            ->map(fn (string $key) => $rows->get($key))
            ->filter()
            ->map(fn (Icon $icon) => [
                'library' => $icon->library,
                'nodes' => $icon->nodes,
            ])
            ->all();
    }

    /**
     * Search the catalogue for the picker.
     *
     * Matching is a plain substring on the name, which is what someone typing
     * "circuit" or "flask" expects. Results are capped because the catalogue
     * holds around fifteen thousand icons and nobody scrolls that.
     *
     * @return array<int, array{key: string, name: string, library: string, nodes: array<int, mixed>}>
     */
    public static function search(?string $query, ?string $library, int $limit): array
    {
        return self::matching($query, $library)
            // Shorter names first: someone searching "atom" wants `atom`
            // before `atom-2-filled`.
            ->orderByRaw('length(name)')
            ->orderBy('name')
            ->limit($limit)
            ->get(['key', 'name', 'library', 'nodes'])
            ->map(fn (Icon $icon) => [
                'key' => $icon->key,
                'name' => $icon->name,
                'library' => $icon->library,
                'nodes' => $icon->nodes,
            ])
            ->all();
    }

    /**
     * How many icons a search matches, for the "refine your search" hint.
     */
    public static function count(?string $query, ?string $library): int
    {
        return self::matching($query, $library)->count();
    }

    /**
     * @return Builder<Icon>
     */
    private static function matching(?string $query, ?string $library)
    {
        $builder = Icon::query();

        if ($library !== null && array_key_exists($library, self::LIBRARIES)) {
            $builder->where('library', $library);
        }

        $query = trim((string) $query);

        if ($query !== '') {
            // Escape the LIKE wildcards: an owner typing "%" should search
            // for a percent sign, not match the whole catalogue.
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($query));

            $builder->where('name', 'like', '%'.$escaped.'%');
        }

        return $builder;
    }

    /**
     * The libraries, for the picker's filter.
     *
     * @return array<int, array{value: string, label: string, count: int}>
     */
    public static function libraries(): array
    {
        $counts = Icon::query()
            ->selectRaw('library, count(*) as total')
            ->groupBy('library')
            ->pluck('total', 'library');

        return collect(self::LIBRARIES)
            ->map(fn (array $meta, string $value) => [
                'value' => $value,
                // __() returns the key unchanged when it is not a key, which
                // is exactly what the three project names need.
                'label' => __($meta['label']),
                'count' => (int) $counts->get($value, 0),
            ])
            ->filter(fn (array $library) => $library['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public static function filledLibraries(): Collection
    {
        return collect(self::FILLED_LIBRARIES);
    }
}
