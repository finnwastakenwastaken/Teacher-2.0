import * as React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';
import type { ImageOption } from '@/components/admin/image-field';
import { imageOptions as searchImageOptions } from '@/routes/admin/media/search';

/*
 * The searchable grid of images the downloads section attaches from.
 *
 * The sibling of resources/js/components/admin/file-picker-list.tsx, and a
 * grid rather than a list for the reason the two libraries are separate in
 * the first place: a document is recognised by its name and a picture is
 * recognised by looking at it.
 *
 * Addressed by id, not by ULID — attaching a download is a relational write,
 * the same one a banner and the branding settings make, which is why it reads
 * App\Http\Controllers\Admin\MediaSearchController::imageOptions and shares
 * `ImageOption` with the banner field. The page *editor* addresses media by
 * ULID and must never learn an id; keeping the two endpoints apart is what
 * keeps that true.
 *
 * Searched on the server rather than filtered over a prop, like every other
 * picker in the admin panel.
 */

type SearchResponse = {
    images: ImageOption[];
    total: number;
    capped: boolean;
};

type Props = {
    /** Ties the search box to its label; two lists share this dialog. */
    idPrefix: string;
    /**
     * Shown when a search with no query still comes back empty. The caller
     * supplies it because only the caller can tell an empty library from one
     * whose every image is already on this page — see `exclude`.
     */
    emptyMessage: string;
    /** Marks one tile as chosen. */
    selectedId: number | null;
    /** Ids already attached to this page, excluded server-side. */
    exclude?: number[];
    onSelect: (image: ImageOption) => void;
};

export function ImagePickerList({
    idPrefix,
    emptyMessage,
    selectedId,
    exclude,
    onSelect,
}: Props) {
    const [query, setQuery] = React.useState('');
    const [response, setResponse] = React.useState<SearchResponse | null>(null);
    const [loading, setLoading] = React.useState(false);

    // A stable string so the effect below does not refire on a new array
    // identity carrying the same ids.
    const excludeKey = (exclude ?? []).join(',');

    useDebouncedSearch(query, excludeKey, setResponse, setLoading);

    const images = response?.images ?? [];
    const searched = query.trim() !== '';

    return (
        <div className="grid gap-2">
            {/* Not "search by name": an image matches on its alt text too,
                and that is often the only thing the owner remembers. */}
            <Label htmlFor={`${idPrefix}-search`}>
                {t('ui.image_field.search_label')}
            </Label>
            <Input
                id={`${idPrefix}-search`}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('ui.image_field.search_placeholder')}
                autoComplete="off"
            />

            <ul
                className="grid max-h-80 grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-3"
                aria-busy={loading}
            >
                {images.map((image) => (
                    <li key={image.id}>
                        <button
                            type="button"
                            aria-pressed={selectedId === image.id}
                            onClick={() => onSelect(image)}
                            className={cn(
                                'w-full overflow-hidden rounded-md border border-border text-left hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                selectedId === image.id && 'ring-2 ring-ring',
                            )}
                        >
                            <img
                                src={image.url}
                                alt={image.alt}
                                loading="lazy"
                                className="h-24 w-full object-cover"
                            />
                            <span className="block truncate p-2 text-xs text-muted-foreground">
                                {image.filename}
                            </span>
                        </button>
                    </li>
                ))}

                {response !== null && images.length === 0 && !loading && (
                    <li className="col-span-full text-sm text-muted-foreground">
                        {searched ? t('ui.library.no_results') : emptyMessage}
                    </li>
                )}
            </ul>

            {response?.capped && (
                <p className="text-xs text-muted-foreground">
                    {t('ui.library.capped', { count: response.total })}
                </p>
            )}
        </div>
    );
}

/**
 * Debounced, and every response carries the request that produced it so a
 * slow early search cannot overwrite a later one — same pattern as
 * resources/js/components/admin/file-picker-list.tsx.
 */
function useDebouncedSearch(
    query: string,
    excludeKey: string,
    setResponse: (response: SearchResponse) => void,
    setLoading: (loading: boolean) => void,
) {
    React.useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);

            fetch(
                searchImageOptions.url({
                    query: {
                        q: query,
                        exclude: excludeKey === '' ? undefined : excludeKey,
                    },
                }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((res) => (res.ok ? res.json() : null))
                .then((data: SearchResponse | null) => {
                    if (data) {
                        setResponse(data);
                    }
                })
                .catch(() => {
                    /* aborted or offline; the previous results stay put */
                })
                .finally(() => setLoading(false));
        }, 150);

        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [query, excludeKey, setResponse, setLoading]);
}
