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

export type ImageOption = {
    id: number;
    alt: string;
    filename: string;
    url: string;
};

type Props = {
    /** Submitted field name. An empty string means "none". */
    name: string;
    label: string;
    description?: string;
    images: ImageOption[];
    value: number | null;
    onChange: (value: number | null) => void;
};

/*
 * Pick an image from the library, by id.
 *
 * By id rather than by ULID because these are relational writes — a page's
 * hero column and the branding settings — while the page *editor* addresses
 * media by ULID and must never learn the id. Same split as downloads.
 */
export function ImageField({
    name,
    label,
    description,
    images,
    value,
    onChange,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const labelId = React.useId();

    const selected = images.find((image) => image.id === value) ?? null;

    const matches = React.useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return images;
        }

        return images.filter(
            (image) =>
                image.filename.toLowerCase().includes(needle) ||
                image.alt.toLowerCase().includes(needle),
        );
    }, [images, query]);

    const choose = (id: number | null) => {
        onChange(id);
        setOpen(false);
    };

    return (
        // A group rather than a labelled control: what carries the value is a
        // hidden input, and what the user operates is a pair of buttons. A
        // <label> would have nothing to point at, so the name is attached to
        // the group and repeated on the buttons — three "Kiezen" buttons on
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
                    {selected ? (
                        <img
                            src={selected.url}
                            alt={selected.alt}
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
                        {selected ? selected.filename : 'Geen afbeelding'}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setOpen(true)}
                            aria-label={`${label}: ${selected ? 'vervangen' : 'kiezen'}`}
                        >
                            {selected ? 'Vervangen' : 'Kiezen'}
                        </Button>
                        {selected && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => onChange(null)}
                                aria-label={`${label}: verwijderen`}
                            >
                                Verwijderen
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
                            Kies een afbeelding uit de mediabibliotheek.
                        </DialogDescription>
                    </DialogHeader>

                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Zoek op bestandsnaam of alt-tekst"
                        aria-label="Zoek een afbeelding"
                    />

                    {images.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            De mediabibliotheek bevat nog geen afbeeldingen.
                        </p>
                    ) : (
                        <div className="grid max-h-[45vh] grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-3">
                            {matches.map((image) => (
                                <button
                                    key={image.id}
                                    type="button"
                                    onClick={() => choose(image.id)}
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
                            {matches.length === 0 && (
                                <p className="col-span-full py-8 text-center text-sm text-muted-foreground">
                                    Niets gevonden.
                                </p>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
