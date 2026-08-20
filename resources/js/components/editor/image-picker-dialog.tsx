import { Check } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import type { EditorLibraryImage } from '@/components/editor/media-library';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    images: EditorLibraryImage[];
    maxBytes: number;
    /** Registers the new image with the editor's library. */
    onUploaded: (record: UploadedRecord) => void;
    onSelect: (ulids: string[]) => void;
    onClose: () => void;
};

/**
 * Images are chosen by eye, so this is a grid — and multi-select, because the
 * block is a gallery: one block can carry several pictures.
 *
 * Mounted only while open (see page-editor.tsx), so its state starts fresh
 * every time without an effect to reset it.
 *
 * Uploading here does not insert anything: the gallery is a batch, so a new
 * image is ticked and joins whatever else is already selected. Nothing reaches
 * the page until "Invoegen".
 */
export function ImagePickerDialog({
    images,
    maxBytes,
    onUploaded,
    onSelect,
    onClose,
}: Props) {
    const [query, setQuery] = React.useState('');
    const [selected, setSelected] = React.useState<string[]>([]);

    const needle = query.trim().toLowerCase();

    const matches =
        needle === ''
            ? images
            : images.filter(
                  (image) =>
                      image.original_filename.toLowerCase().includes(needle) ||
                      image.alt_text.toLowerCase().includes(needle),
              );

    const toggle = (ulid: string) => {
        setSelected((current) =>
            current.includes(ulid)
                ? current.filter((entry) => entry !== ulid)
                : [...current, ulid],
        );
    };

    return (
        <Dialog
            open
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Afbeeldingen invoegen</DialogTitle>
                    <DialogDescription>
                        Kies één of meer afbeeldingen. Ze worden als één
                        galerijblok ingevoegd, in de volgorde waarin je ze
                        aanklikt.
                    </DialogDescription>
                </DialogHeader>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    title="Nieuwe afbeelding uploaden"
                    description="Komt in de mediabibliotheek en wordt hier meteen aangevinkt."
                    onUploaded={(record) => {
                        onUploaded(record);

                        if (record.type === 'image') {
                            setSelected((current) => [...current, record.ulid]);

                            return;
                        }

                        toast.info(
                            `"${record.original_filename}" is geen afbeelding. Voeg hem in met de knop "Bestand invoegen".`,
                        );
                    }}
                />

                <div className="grid gap-2">
                    <Label htmlFor="image-picker-search">Zoeken</Label>
                    <Input
                        id="image-picker-search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Bestandsnaam of alt-tekst"
                        autoComplete="off"
                    />
                </div>

                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Er zijn nog geen afbeeldingen. Upload er hierboven een,
                        of bij Media.
                    </p>
                ) : (
                    <ul className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                        {matches.map((image) => {
                            const position = selected.indexOf(image.ulid);
                            const isSelected = position !== -1;

                            return (
                                <li key={image.ulid}>
                                    <button
                                        type="button"
                                        aria-pressed={isSelected}
                                        onClick={() => toggle(image.ulid)}
                                        className={cn(
                                            'relative w-full overflow-hidden rounded-lg border border-border bg-card text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            isSelected && 'ring-2 ring-ring',
                                        )}
                                    >
                                        <span className="flex aspect-video items-center justify-center overflow-hidden bg-muted">
                                            <img
                                                src={image.url}
                                                alt={image.alt_text}
                                                loading="lazy"
                                                className="max-h-full max-w-full object-contain"
                                            />
                                        </span>

                                        {isSelected && (
                                            <span className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                                {position + 1}
                                            </span>
                                        )}

                                        <span
                                            className="block truncate p-2 text-xs text-muted-foreground"
                                            title={image.original_filename}
                                        >
                                            {image.original_filename}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}

                        {matches.length === 0 && (
                            <li className="text-sm text-muted-foreground">
                                Geen afbeeldingen gevonden.
                            </li>
                        )}
                    </ul>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Annuleren
                    </Button>
                    <Button
                        type="button"
                        disabled={selected.length === 0}
                        onClick={() => onSelect(selected)}
                    >
                        <Check aria-hidden="true" />
                        {selected.length <= 1
                            ? 'Invoegen'
                            : `${selected.length} invoegen`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
