import { useEffect, useState } from 'react';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as iconsIndex } from '@/routes/admin/icons';
import { t } from '@/lib/i18n';

/*
 * The catalogue is generated from the icon packages' own exported data by
 * scripts/build-icon-catalogue.mjs and searched on the server — never a
 * hand-written list (the technical reference; v1 rendered blank tiles from guessed
 * names). It holds roughly fifteen thousand icons across four libraries, far
 * too many to ship to the browser, so this dialog asks for a page at a time
 * and each result arrives with the geometry needed to draw it.
 */

type IconResult = IconData & {
    key: string;
    name: string;
};

type LibraryOption = {
    value: string;
    label: string;
    count: number;
};

type SearchResponse = {
    icons: IconResult[];
    total: number;
    capped: boolean;
    libraries: LibraryOption[];
};

type IconPickerProps = {
    value: string | null;
    /** Geometry for `value`, supplied by the server on edit screens. */
    valueIcon?: IconData | null;
    onChange: (key: string | null) => void;
    label?: string;
};

export function IconPicker({
    value,
    valueIcon,
    onChange,
    label,
}: IconPickerProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [library, setLibrary] = useState<string | null>(null);
    const [response, setResponse] = useState<SearchResponse | null>(null);
    const [loading, setLoading] = useState(false);

    // Keep the chosen icon drawable after the dialog closes. The server only
    // supplies geometry for the value the page loaded with, so a fresh pick
    // has to remember what it saw in the results — derived rather than mirrored
    // into state, so a later server value cannot be shadowed by a stale pick.
    const [picked, setPicked] = useState<{
        key: string | null;
        icon: IconData | null;
    } | null>(null);

    const chosen =
        picked && picked.key === value ? picked.icon : (valueIcon ?? null);

    useEffect(() => {
        if (!open) {
            return;
        }

        // Debounced, and every response carries the request that produced it
        // so a slow early search cannot overwrite a later one.
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);

            fetch(
                iconsIndex.url({
                    query: { q: query, library: library ?? undefined },
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
    }, [open, query, library]);

    function choose(icon: IconResult | null) {
        onChange(icon?.key ?? null);
        setPicked({ key: icon?.key ?? null, icon: icon ?? null });
        setOpen(false);
        setQuery('');
    }

    const libraries = response?.libraries ?? [];

    return (
        <div className="grid gap-2">
            {label && <Label>{label}</Label>}

            <Button
                type="button"
                variant="outline"
                className="w-fit justify-start"
                onClick={() => setOpen(true)}
            >
                {value ? (
                    <>
                        <Icon icon={chosen} className="size-4" />
                        {value}
                    </>
                ) : (
                    <span className="text-muted-foreground">
                        {t('ui.icons.none_chosen')}
                    </span>
                )}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('ui.icons.dialog_title')}</DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-3">
                        <Input
                            type="search"
                            placeholder={t('ui.icons.search_placeholder')}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            autoFocus
                        />

                        {libraries.length > 1 && (
                            <div
                                className="flex flex-wrap gap-1"
                                role="group"
                                aria-label={t('ui.icons.filter_label')}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        library === null ? 'secondary' : 'ghost'
                                    }
                                    onClick={() => setLibrary(null)}
                                >
                                    {t('ui.icons.all')}
                                </Button>
                                {libraries.map((option) => (
                                    <Button
                                        key={option.value}
                                        type="button"
                                        size="sm"
                                        variant={
                                            library === option.value
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        onClick={() => setLibrary(option.value)}
                                    >
                                        {option.label}
                                    </Button>
                                ))}
                            </div>
                        )}

                        <Button
                            type="button"
                            variant="ghost"
                            className="w-fit"
                            onClick={() => choose(null)}
                        >
                            {t('ui.icons.none')}
                        </Button>

                        <div
                            className="grid max-h-96 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-5"
                            aria-busy={loading}
                        >
                            {(response?.icons ?? []).map((icon) => (
                                <button
                                    key={icon.key}
                                    type="button"
                                    onClick={() => choose(icon)}
                                    title={icon.key}
                                    className="flex flex-col items-center gap-1 rounded-md border border-transparent p-2 text-center hover:border-border hover:bg-accent hover:text-accent-foreground"
                                >
                                    <Icon icon={icon} className="size-5" />
                                    <span className="w-full truncate text-xs text-muted-foreground">
                                        {icon.name}
                                    </span>
                                </button>
                            ))}
                        </div>

                        {response &&
                            response.icons.length === 0 &&
                            !loading && (
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.icons.no_results')}
                                </p>
                            )}

                        {response?.capped && (
                            <p className="text-xs text-muted-foreground">
                                {t('ui.icons.capped', {
                                    count: response.total,
                                })}
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
