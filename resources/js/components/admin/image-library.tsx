import { router } from '@inertiajs/react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatBytes } from '@/lib/format';
import {
    update as updateImage,
    destroy as destroyImage,
} from '@/routes/admin/media/images';
import type { MediaImage } from '@/types';

function ImageCard({
    image,
    onEdit,
}: {
    image: MediaImage;
    onEdit: (image: MediaImage) => void;
}) {
    const remove = () => {
        if (
            !confirm(
                `Weet je zeker dat je "${image.original_filename}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`,
            )
        ) {
            return;
        }

        router.delete(destroyImage(image.ulid).url, { preserveScroll: true });
    };

    return (
        <li className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
            <div className="flex aspect-video items-center justify-center overflow-hidden bg-muted">
                <img
                    src={image.url}
                    alt={image.alt_text}
                    loading="lazy"
                    className="max-h-full max-w-full object-contain"
                />
            </div>

            <div className="grid flex-1 gap-1 p-3">
                <p
                    className="truncate text-sm font-medium"
                    title={image.original_filename}
                >
                    {image.original_filename}
                </p>
                <p className="text-xs text-muted-foreground">
                    {image.width !== null && image.height !== null
                        ? `${image.width} × ${image.height} px · `
                        : ''}
                    {formatBytes(image.size_bytes)}
                </p>
                <p
                    className="line-clamp-2 text-xs text-muted-foreground italic"
                    title={image.alt_text}
                >
                    {image.alt_text}
                </p>
            </div>

            <div className="flex flex-wrap gap-2 border-t border-border p-3">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onEdit(image)}
                >
                    Alt-tekst bewerken
                </Button>
                <Button variant="destructive" size="sm" onClick={remove}>
                    Verwijderen
                </Button>
            </div>
        </li>
    );
}

export function ImageLibrary({ images }: { images: MediaImage[] }) {
    const [editing, setEditing] = React.useState<MediaImage | null>(null);
    const [altText, setAltText] = React.useState('');
    const [error, setError] = React.useState<string | null>(null);
    const [saving, setSaving] = React.useState(false);

    const openEditor = React.useCallback((image: MediaImage) => {
        setEditing(image);
        setAltText(image.alt_text);
        setError(null);
    }, []);

    const save = () => {
        if (editing === null) {
            return;
        }

        const value = altText.trim();

        if (value === '') {
            setError('Alt-tekst is verplicht bij elke afbeelding.');

            return;
        }

        setSaving(true);

        router.patch(
            updateImage(editing.ulid).url,
            { alt_text: value },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(null),
                onError: (errors) =>
                    setError(
                        errors.alt_text ??
                            'De alt-tekst kon niet worden opgeslagen.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    if (images.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Er zijn nog geen afbeeldingen geüpload.
            </p>
        );
    }

    return (
        <>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {images.map((image) => (
                    <ImageCard
                        key={image.ulid}
                        image={image}
                        onEdit={openEditor}
                    />
                ))}
            </ul>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Alt-tekst bewerken</DialogTitle>
                        <DialogDescription>
                            Beschrijf kort wat er op de afbeelding te zien is.
                            Deze tekst wordt voorgelezen door schermlezers en
                            getoond als de afbeelding niet laadt.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="alt-text">Alt-tekst</Label>
                        <Textarea
                            id="alt-text"
                            rows={3}
                            value={altText}
                            onChange={(event) => setAltText(event.target.value)}
                        />
                        {error && <p className="text-sm text-error">{error}</p>}
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditing(null)}
                        >
                            Annuleren
                        </Button>
                        <Button onClick={save} disabled={saving}>
                            Opslaan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
