import * as React from 'react';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MediaFileKind } from '@/types';
import { files as searchFiles } from '@/routes/admin/media/search';
import { t } from '@/lib/i18n';

/*
 * The searchable list of documents and videos both file pickers are built on.
 *
 * Shared because recognising a file by name behaves the same everywhere; what
 * happens once one is picked does not. The editor drops an embed at the caret
 * and the downloads section collects a label and creates a row, so each caller
 * keeps its own footer and only the list lives here.
 *
 * Documents and videos are chosen by name, so this is a list rather than the
 * grid the image picker uses — the opposite choice, deliberately.
 *
 * Searched on the server rather than filtered over a prop, the same way the
 * icon picker searches ~15,000 icons instead of holding them in the browser
 * (the technical reference): at a few hundred files the whole library is hundreds of
 * kilobytes before anyone has typed anything, so this asks
 * App\Http\Controllers\Admin\MediaSearchController for a page of matches
 * instead.
 */

/** All a row needs of a file. Both callers hand over rather more than this. */
export type PickableFile = {
    id: number;
    ulid: string;
    kind: MediaFileKind;
    mime: string;
    size_bytes: number;
    original_filename: string;
    url: string;
};

type SearchResponse = {
    files: PickableFile[];
    total: number;
    capped: boolean;
};

type Props = {
    /** Ties the search box to its label; two lists can share a screen. */
    idPrefix: string;
    /**
     * Shown in place of the list when nothing matches an empty search. The
     * caller supplies it because only the caller can tell an empty library
     * from one whose every file is already on this page — see `exclude`.
     */
    emptyMessage: string;
    /**
     * Marks one row as chosen. Left out entirely where activating a row acts
     * at once, so that nothing claims a selection that is never shown.
     */
    selectedUlid?: string | null;
    /**
     * Ids to leave out of the results — the downloads section asks for
     * everything except what is already attached to this page, without
     * shipping the whole library to work that out in the browser.
     */
    exclude?: number[];
    onSelect: (file: PickableFile) => void;
};

export function FilePickerList({
    idPrefix,
    emptyMessage,
    selectedUlid,
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

    const files = response?.files ?? [];
    const selectable = selectedUlid !== undefined;
    const searched = query.trim() !== '';

    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-search`}>
                {t('ui.library.search')}
            </Label>
            <Input
                id={`${idPrefix}-search`}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('ui.library.search_placeholder')}
                autoComplete="off"
            />

            <ul
                className="grid max-h-80 gap-2 overflow-y-auto"
                aria-busy={loading}
            >
                {files.map((file) => {
                    const isSelected = selectedUlid === file.ulid;

                    return (
                        <li key={file.ulid}>
                            <button
                                type="button"
                                aria-pressed={
                                    selectable ? isSelected : undefined
                                }
                                onClick={() => onSelect(file)}
                                className={cn(
                                    'flex w-full items-center gap-3 rounded-lg border border-border bg-card p-3 text-left hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    isSelected && 'ring-2 ring-ring',
                                )}
                            >
                                <FileTypeIcon
                                    mime={file.mime}
                                    kind={file.kind}
                                    className="size-5 shrink-0"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">
                                        {file.original_filename}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {file.kind === 'video'
                                            ? t('ui.library.kind_video')
                                            : t(
                                                  'ui.library.kind_document',
                                              )}{' '}
                                        · {formatBytes(file.size_bytes)}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}

                {response !== null && files.length === 0 && !loading && (
                    <li className="text-sm text-muted-foreground">
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
 * resources/js/components/icon-picker.tsx.
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
                searchFiles.url({
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
