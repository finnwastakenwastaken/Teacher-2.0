import { ImageOff } from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';
import { imageOptions as searchImageOptions } from '@/routes/admin/media/search';

export type ImageOption = {
    id: number;
    alt: string;
    filename: string;
    url: string;
};

type SearchResponse = {
    images: ImageOption[];
    total: number;
    capped: boolean;
};

type Props = {
    /** Submitted field name. An empty string means "none". */
    name: string;
    label: string;
    description?: string;
    /**
     * What the stored value points at, so the thumbnail draws without a round
     * trip. Only that one image is sent — everything else the dialog searches
     * for, which is why this is not the library.
     */
    selected: ImageOption | null;
    value: number | null;
    onChange: (value: number | null) => void;
};

/*
 * Pick an image from the library, by id.
 *
 * By id rather than by ULID because these are relational writes — a page's
 * hero column and the branding settings — while the page *editor* addresses
 * media by ULID and must never learn the id. Same split as downloads, and the
 * reason there are two image-search endpoints rather than one.
 *
 * Searched on the server, like every other picker in the admin panel (the
 * icon catalogue, the two media pickers): three of these render on the
 * settings screen alone, and shipping the library once per screen put a few
 * hundred images into the payload before anyone had opened a dialog.
 */
export function ImageField({
    name,
    label,
    description,
    selected,
    value,
    onChange,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [response, setResponse] = React.useState<SearchResponse | null>(null);
    const [loading, setLoading] = React.useState(false);
    // Whatever was picked in this dialog, so the thumbnail can change without
    // waiting for the form to be saved and the page re-rendered.
    const [picked, setPicked] = React.useState<ImageOption | null>(null);
    const labelId = React.useId();

    useDebouncedSearch(open, query, setResponse, setLoading);

    // Derived rather than stored, so a value the parent resets externally
    // cannot leave a thumbnail behind that nothing points at any more.
    const display = React.useMemo(() => {
        if (value === null) {
            return null;
        }

        if (picked?.id === value) {
            return picked;
        }

        return selected?.id === value ? selected : null;
    }, [value, picked, selected]);

    const images = response?.images ?? [];
    const searched = query.trim() !== '';

    const choose = (image: ImageOption) => {
        setPicked(image);
        onChange(image.id);
        setOpen(false);
    };

    return (
        // A group rather than a labelled control: what carries the value is a
        // hidden input, and what the user operates is a pair of buttons. A
        // <label> would have nothing to point at, so the name is attached to
        // the group and repeated on the buttons — three "Choose" buttons on
        // the settings screen are indistinguishable otherwise.
        <div className="grid gap-2" role="group" aria-labelledby={labelId}>
            <span id={labelId} className="text-sm leading-none font-medium">
                {label}
            </span>

            {/* The value travels in a hidden input so the surrounding
                Inertia <Form> submits it like any other field — an empty
                string, which the Form Request turns back into null. */}
            <input type="hidden" name={name} value={value ?? ''} />

            <div className="flex items-center gap-3">
                <div className="flex h-16 w-24 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted">
                    {display ? (
                        <img
                            src={display.url}
                            alt={display.alt}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <ImageOff
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    )}
                </div>

                <div className="grid gap-1">
                    <p className="text-sm">
                        {display ? display.filename : t('ui.image_field.none')}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setOpen(true)}
                            aria-label={t(
                                display
                                    ? 'ui.image_field.replace_label'
                                    : 'ui.image_field.choose_label',
                                { field: label },
                            )}
                        >
                            {display
                                ? t('ui.image_field.replace')
                                : t('ui.image_field.choose')}
                        </Button>
                        {display && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setPicked(null);
                                    onChange(null);
                                }}
                                aria-label={t('ui.image_field.remove_label', {
                                    field: label,
                                })}
                            >
                                {t('ui.actions.delete')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[80vh] overflow-hidden sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{label}</DialogTitle>
                        <DialogDescription>
                            {t('ui.image_field.dialog_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t('ui.image_field.search_placeholder')}
                        aria-label={t('ui.image_field.search_label')}
                    />

                    <div
                        className="grid max-h-[45vh] grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-3"
                        aria-busy={loading}
                    >
                        {images.map((image) => (
                            <button
                                key={image.id}
                                type="button"
                                onClick={() => choose(image)}
                                className={cn(
                                    'group overflow-hidden rounded-md border text-left transition-colors hover:bg-accent',
                                    image.id === value
                                        ? 'border-primary'
                                        : 'border-border',
                                )}
                            >
                                <img
                                    src={image.url}
                                    alt={image.alt}
                                    className="h-24 w-full object-cover"
                                />
                                <span className="block truncate p-2 text-xs text-muted-foreground">
                                    {image.filename}
                                </span>
                            </button>
                        ))}

                        {response !== null &&
                            images.length === 0 &&
                            !loading && (
                                <p className="col-span-full py-8 text-center text-sm text-muted-foreground">
                                    {searched
                                        ? t('ui.image_field.no_results')
                                        : t('ui.image_field.empty')}
                                </p>
                            )}
                    </div>

                    {response?.capped && (
                        <p className="text-xs text-muted-foreground">
                            {t('ui.library.capped', { count: response.total })}
                        </p>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

/**
 * Debounced, and it does not run until the dialog has been opened — three of
 * these live on the settings screen, and searching on mount would be three
 * requests for a library nobody has asked to see.
 *
 * Same shape as resources/js/components/admin/file-picker-list.tsx.
 */
function useDebouncedSearch(
    open: boolean,
    query: string,
    setResponse: (response: SearchResponse) => void,
    setLoading: (loading: boolean) => void,
) {
    React.useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);

            fetch(searchImageOptions.url({ query: { q: query } }), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
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
    }, [open, query, setResponse, setLoading]);
}
