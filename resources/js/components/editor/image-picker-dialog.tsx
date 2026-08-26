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
import { images as searchImages } from '@/routes/admin/media/search';
import type { AcceptedFormats } from '@/types';
import { t } from '@/lib/i18n';

type SearchResponse = {
    images: EditorLibraryImage[];
    total: number;
    capped: boolean;
};

type Props = {
    maxBytes: number;
    acceptedFormats: AcceptedFormats;
    /**
     * How many pictures the block being built can hold. A gallery is a batch;
     * an image beside text is one picture, so ticking a second one replaces
     * the first rather than adding to it.
     */
    multiple?: boolean;
    /** Registers the new image with the editor's library. */
    onUploaded: (record: UploadedRecord) => void;
    /**
     * Called for an already-existing image the owner ticks (uploads use
     * `onUploaded` instead). Must register it in GrowingEditorLibrary, which
     * only holds what the body already showed — otherwise the inserted block
     * renders as missing until the page reloads.
     */
    onPicked: (image: EditorLibraryImage) => void;
    onSelect: (ulids: string[]) => void;
    onClose: () => void;
};

/**
 * A grid, chosen by eye. Mounted only while open, so state starts fresh
 * without needing a reset effect. Uploading here only ticks the image;
 * nothing reaches the page until "Invoegen". `multiple` toggles tile-count
 * only, since the grid/uploader/insert flow is otherwise identical. Searched
 * server-side like file-picker-list.tsx; a fresh upload is prepended locally
 * since a search made before it exists can't know about it yet.
 */
export function ImagePickerDialog({
    maxBytes,
    acceptedFormats,
    multiple = true,
    onUploaded,
    onPicked,
    onSelect,
    onClose,
}: Props) {
    const [query, setQuery] = React.useState('');
    const [selected, setSelected] = React.useState<EditorLibraryImage[]>([]);
    const [response, setResponse] = React.useState<SearchResponse | null>(null);
    const [loading, setLoading] = React.useState(false);
    // This session's own uploads, kept visible regardless of the current
    // query — the owner just created them and expects to see them, the same
    // way the gallery is a batch they are still assembling.
    const [uploaded, setUploaded] = React.useState<EditorLibraryImage[]>([]);

    React.useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);

            fetch(searchImages.url({ query: { q: query } }), {
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
    }, [query]);

    const seen = new Set<string>();
    const matches = [...uploaded, ...(response?.images ?? [])].filter(
        (image) => {
            if (seen.has(image.ulid)) {
                return false;
            }

            seen.add(image.ulid);

            return true;
        },
    );

    const selectedUlids = new Set(selected.map((image) => image.ulid));

    const toggle = (image: EditorLibraryImage) => {
        if (selectedUlids.has(image.ulid)) {
            setSelected((current) =>
                current.filter((entry) => entry.ulid !== image.ulid),
            );

            return;
        }

        onPicked(image);
        setSelected((current) => (multiple ? [...current, image] : [image]));
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
                    <DialogTitle>
                        {multiple
                            ? t('ui.editor.insert_images')
                            : t('ui.editor.insert_image_aside')}
                    </DialogTitle>
                    <DialogDescription>
                        {/* The single-image description is where the phone
                            behaviour is stated: the owner arranges this on a
                            desktop and would otherwise never see it stack. */}
                        {multiple
                            ? t('ui.editor.image_dialog.description')
                            : t('ui.editor.image_dialog.description_aside')}
                    </DialogDescription>
                </DialogHeader>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    title={t('ui.editor.image_dialog.upload_title')}
                    description={t('ui.editor.image_dialog.upload_description')}
                    onUploaded={(record) => {
                        onUploaded(record);

                        if (record.type === 'image') {
                            const image: EditorLibraryImage = {
                                ulid: record.ulid,
                                alt_text: record.alt_text,
                                original_filename: record.original_filename,
                                url: record.url,
                            };

                            setUploaded((current) => [image, ...current]);
                            setSelected((current) =>
                                multiple ? [...current, image] : [image],
                            );

                            return;
                        }

                        toast.info(
                            t('ui.editor.image_dialog.not_an_image', {
                                name: record.original_filename,
                            }),
                        );
                    }}
                />

                <div className="grid gap-2">
                    <Label htmlFor="image-picker-search">
                        {t('ui.editor.image_dialog.search')}
                    </Label>
                    <Input
                        id="image-picker-search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={t(
                            'ui.editor.image_dialog.search_placeholder',
                        )}
                        autoComplete="off"
                    />
                </div>

                {response !== null &&
                matches.length === 0 &&
                !loading &&
                query.trim() === '' ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.image_dialog.empty')}
                    </p>
                ) : (
                    <ul
                        className="grid max-h-96 grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3"
                        aria-busy={loading}
                    >
                        {matches.map((image) => {
                            const position = selected.findIndex(
                                (entry) => entry.ulid === image.ulid,
                            );
                            const isSelected = position !== -1;

                            return (
                                <li key={image.ulid}>
                                    <button
                                        type="button"
                                        aria-pressed={isSelected}
                                        onClick={() => toggle(image)}
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
                                                {/* The number is the order the
                                                    gallery will show them in;
                                                    with one image there is no
                                                    order to state. */}
                                                {multiple ? (
                                                    position + 1
                                                ) : (
                                                    <Check
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                )}
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

                        {matches.length === 0 &&
                            !loading &&
                            query.trim() !== '' && (
                                <li className="text-sm text-muted-foreground">
                                    {t('ui.editor.image_dialog.no_results')}
                                </li>
                            )}
                    </ul>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('ui.actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={selected.length === 0}
                        onClick={() =>
                            onSelect(selected.map((image) => image.ulid))
                        }
                    >
                        <Check aria-hidden="true" />
                        {selected.length <= 1
                            ? t('ui.editor.image_dialog.insert')
                            : t('ui.editor.image_dialog.insert_count', {
                                  count: selected.length,
                              })}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
