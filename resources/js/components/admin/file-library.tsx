import { router } from '@inertiajs/react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FileTypeIcon } from '@/components/file-type-icon';
import { formatBytes } from '@/lib/format';
import { destroy as destroyFile } from '@/routes/admin/media/files';
import type { MediaFile } from '@/types';

const KIND_LABELS: Record<MediaFile['kind'], string> = {
    document: 'Document',
    video: 'Video',
};

const ICON_CLASS = 'size-5 shrink-0 text-muted-foreground';

function FileRow({
    file,
    onPreview,
}: {
    file: MediaFile;
    onPreview: (file: MediaFile) => void;
}) {
    const remove = () => {
        if (
            !confirm(
                `Weet je zeker dat je "${file.original_filename}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`,
            )
        ) {
            return;
        }

        router.delete(destroyFile(file.ulid).url, { preserveScroll: true });
    };

    return (
        <li className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-3">
            <FileTypeIcon
                mime={file.mime}
                kind={file.kind}
                className={ICON_CLASS}
            />

            <div className="min-w-0 flex-1">
                <p
                    className="truncate font-medium"
                    title={file.original_filename}
                >
                    {file.original_filename}
                </p>
                <p className="text-xs text-muted-foreground">
                    {file.mime} · {formatBytes(file.size_bytes)}
                </p>
            </div>

            <Badge variant="secondary">{KIND_LABELS[file.kind]}</Badge>

            <div className="flex flex-wrap gap-2">
                {file.kind === 'video' && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onPreview(file)}
                    >
                        Voorbeeld
                    </Button>
                )}
                <Button variant="outline" size="sm" asChild>
                    {/* Documents come back as an attachment and videos inline,
                        so the same link reads as "download" or "open"
                        depending on the kind. */}
                    <a href={file.url} target="_blank" rel="noreferrer">
                        {file.kind === 'video' ? 'Openen' : 'Downloaden'}
                    </a>
                </Button>
                <Button variant="destructive" size="sm" onClick={remove}>
                    Verwijderen
                </Button>
            </div>
        </li>
    );
}

export function FileLibrary({ files }: { files: MediaFile[] }) {
    const [preview, setPreview] = React.useState<MediaFile | null>(null);

    if (files.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Er zijn nog geen documenten of video&apos;s geüpload.
            </p>
        );
    }

    return (
        <>
            <ul className="grid gap-2">
                {files.map((file) => (
                    <FileRow
                        key={file.ulid}
                        file={file}
                        onPreview={setPreview}
                    />
                ))}
            </ul>

            <Dialog
                open={preview !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreview(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="truncate">
                            {preview?.original_filename}
                        </DialogTitle>
                        <DialogDescription>
                            De video wordt gestreamd met ondersteuning voor
                            doorspoelen.
                        </DialogDescription>
                    </DialogHeader>

                    {preview && (
                        <video
                            controls
                            preload="metadata"
                            src={preview.url}
                            className="max-h-[70vh] w-full rounded-md bg-muted"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
