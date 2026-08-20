import * as React from 'react';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import type { EditorLibraryFile } from '@/components/editor/media-library';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/format';

type Props = {
    files: EditorLibraryFile[];
    maxBytes: number;
    /** How many uploads this dialog has already put on the page. */
    uploadedCount: number;
    /** Registers the file with the library and inserts it. */
    onUploaded: (record: UploadedRecord) => void;
    onSelect: (file: EditorLibraryFile) => void;
    onClose: () => void;
};

/**
 * Documents and videos are chosen by name, so this is a searchable list
 * rather than a grid — the opposite of the image picker, deliberately.
 *
 * Mounted only while open (see page-editor.tsx), so its state starts fresh
 * every time without an effect to reset it.
 *
 * Unlike the image picker, an upload here goes straight into the document:
 * a file embed is one block, so there is no batch to assemble and nothing to
 * decide. The dialog stays open so several can be added in a row.
 */
export function FilePickerDialog({
    files,
    maxBytes,
    uploadedCount,
    onUploaded,
    onSelect,
    onClose,
}: Props) {
    const [query, setQuery] = React.useState('');

    const needle = query.trim().toLowerCase();

    const matches =
        needle === ''
            ? files
            : files.filter((file) =>
                  file.original_filename.toLowerCase().includes(needle),
              );

    return (
        <Dialog
            open
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Bestand invoegen</DialogTitle>
                    <DialogDescription>
                        Kies een document of video uit de mediabibliotheek. Het
                        bestand wordt pas openbaar zodra deze pagina is
                        opgeslagen.
                    </DialogDescription>
                </DialogHeader>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    title="Nieuw bestand uploaden"
                    description="Wordt meteen op deze plek in de pagina gezet."
                    onUploaded={onUploaded}
                />

                {uploadedCount > 0 && (
                    <p className="text-sm text-muted-foreground" role="status">
                        {uploadedCount === 1
                            ? '1 bestand toegevoegd aan de pagina.'
                            : `${uploadedCount} bestanden toegevoegd aan de pagina.`}{' '}
                        Vergeet niet op &quot;Inhoud opslaan&quot; te klikken.
                    </p>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="file-picker-search">Zoeken op naam</Label>
                    <Input
                        id="file-picker-search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Bijvoorbeeld: werkblad"
                        autoComplete="off"
                    />
                </div>

                {files.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Er zijn nog geen documenten of video&apos;s. Upload er
                        hierboven een, of bij Media.
                    </p>
                ) : (
                    <ul className="grid max-h-80 gap-2 overflow-y-auto">
                        {matches.map((file) => (
                            <li key={file.ulid}>
                                <button
                                    type="button"
                                    onClick={() => onSelect(file)}
                                    className="flex w-full items-center gap-3 rounded-lg border border-border bg-card p-3 text-left hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
                                                ? 'Video'
                                                : 'Document'}{' '}
                                            · {formatBytes(file.size_bytes)}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}

                        {matches.length === 0 && (
                            <li className="text-sm text-muted-foreground">
                                Geen bestanden gevonden.
                            </li>
                        )}
                    </ul>
                )}

                <div className="flex justify-end">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Annuleren
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
