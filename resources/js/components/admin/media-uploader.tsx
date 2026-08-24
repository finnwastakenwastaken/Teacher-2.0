import { router } from '@inertiajs/react';
import { CloudUpload, FileUp, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { jsonRequestHeaders } from '@/lib/csrf';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MediaFileKind } from '@/types';
import {
    chunk as chunkRoute,
    complete as completeRoute,
    destroy as abortRoute,
    store as beginRoute,
} from '@/routes/admin/uploads';
import { t } from '@/lib/i18n';

/*
 * Chunked upload, by hand.
 *
 * Cloudflare Free/Pro rejects any request body over 100 MB, so a lecture
 * video can never be one POST. The server hands back a chunk size and a chunk
 * count, and this walks the file with Blob.slice(). No upload library is
 * involved on purpose (the technical reference: the stack stays small).
 *
 * Two things the server is strict about and this must match exactly:
 *
 *   - every chunk but the last is *exactly* chunkBytes. Slicing at
 *     i * chunkBytes .. (i + 1) * chunkBytes gives that for free, including
 *     the short remainder at the end.
 *   - alt_text is mandatory once the assembled file turns out to be an image.
 *     The server sniffs content and only finds out at complete() — far too
 *     late to prompt. So the prompt happens up front for anything the browser
 *     calls an image, and the answer is carried through the whole upload.
 *
 * Uploads run one at a time. Parallelism would buy little against a home-lab
 * uplink and makes progress, cancellation and error reporting much harder to
 * reason about.
 */

type UploadStatus = 'waiting' | 'uploading' | 'done' | 'failed' | 'cancelled';

/**
 * What the server made of the assembled bytes, from the complete endpoint.
 *
 * The type is decided by sniffing, not by what the browser claimed, so the
 * caller finds out here and nowhere earlier. The keys match the shapes the
 * page editor is already given, so a record can be used as-is.
 */
export type UploadedRecord =
    | {
          type: 'image';
          id: number;
          ulid: string;
          alt_text: string;
          original_filename: string;
          url: string;
      }
    | {
          type: 'file';
          id: number;
          ulid: string;
          kind: MediaFileKind;
          mime: string;
          size_bytes: number;
          original_filename: string;
          url: string;
      };

type QueueItem = {
    id: string;
    file: File;
    altText: string | null;
    status: UploadStatus;
    chunksSent: number;
    totalChunks: number;
    message: string | null;
};

type PendingSelection = {
    id: string;
    file: File;
    altText: string;
};

// Read through t() at render rather than at module scope: the
// dictionary is a Blade global, and a module-level constant would freeze
// whichever language happened to load first.
const STATUS_KEYS: Record<UploadStatus, string> = {
    waiting: 'ui.uploader.status.waiting',
    uploading: 'ui.uploader.status.uploading',
    done: 'ui.uploader.status.done',
    failed: 'ui.uploader.status.failed',
    cancelled: 'ui.uploader.status.cancelled',
};

const STATUS_CLASSES: Record<UploadStatus, string> = {
    waiting: 'bg-muted text-muted-foreground',
    uploading: 'bg-info text-info-foreground',
    done: 'bg-success text-success-foreground',
    failed: 'bg-destructive text-destructive-foreground',
    cancelled: 'bg-muted text-muted-foreground',
};

/*
 * Whether to ask for alt text before this file is sent.
 *
 * The server decides what a file really is by sniffing the assembled bytes,
 * far too late to prompt for anything — so this has to guess, and guessing low
 * costs the owner a rejected upload after the bytes have already gone up.
 *
 * The extension list is why: Windows has no MIME registration for HEIC, so a
 * photo dragged off an iPhone arrives with an empty `type` there and would
 * sail past a check on `type` alone.
 */
const IMAGE_EXTENSIONS = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'avif',
    'svg',
    'heic',
    'heif',
];

function isImageFile(file: File): boolean {
    if (file.type.startsWith('image/')) {
        return true;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    return IMAGE_EXTENSIONS.includes(extension);
}

/** Reads the `message` a 4xx JSON response carries, or a fallback. */
async function readErrorMessage(response: Response): Promise<string> {
    try {
        const body = (await response.json()) as { message?: string };

        if (typeof body.message === 'string' && body.message !== '') {
            return body.message;
        }
    } catch {
        // Not JSON — nginx or PHP failed before Laravel could answer.
    }

    return t('ui.uploader.server_error', { status: response.status });
}

class UploadCancelled extends Error {}

function ProgressBar({ value }: { value: number }) {
    const percentage = Math.round(Math.min(Math.max(value, 0), 1) * 100);

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percentage}
            className="h-2 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                className="h-full rounded-full bg-primary transition-[width] duration-200"
                style={{ width: `${percentage}%` }}
            />
        </div>
    );
}

type MediaUploaderProps = {
    maxBytes: number;
    /**
     * Called once per finished file, awaited before the next one starts.
     *
     * Providing it also takes over what happens afterwards: the media screen
     * reloads to show the new rows, but the page editor links the file to the
     * page instead, and a reload there would throw away an unsaved body. So
     * `router.reload()` only runs when nobody is listening.
     *
     * Awaiting matters: linking is an Inertia visit, and Inertia cancels the
     * visit in flight when a new one starts. Firing these off in parallel
     * would silently drop all but the last file.
     */
    onUploaded?: (record: UploadedRecord) => void | Promise<void>;
    /** Drops the drop-zone to a single row, for use inside a section. */
    compact?: boolean;
    /** Overrides the drop-zone heading. */
    title?: string;
    /** Overrides the sentence under the heading. */
    description?: string;
};

export function MediaUploader({
    maxBytes,
    onUploaded,
    compact = false,
    title,
    description,
}: MediaUploaderProps) {
    const [items, setItems] = React.useState<QueueItem[]>([]);
    const [isDragging, setIsDragging] = React.useState(false);
    const [isRunning, setIsRunning] = React.useState(false);
    const [pending, setPending] = React.useState<PendingSelection[] | null>(
        null,
    );
    const [altErrors, setAltErrors] = React.useState<Record<string, string>>(
        {},
    );

    const inputRef = React.useRef<HTMLInputElement>(null);
    // Cancellation crosses an async boundary that never re-reads React state,
    // so the flag lives in a ref the running loop can see immediately.
    const cancelledRef = React.useRef<Set<string>>(new Set());
    const abortControllerRef = React.useRef<AbortController | null>(null);

    const patchItem = React.useCallback(
        (id: string, patch: Partial<QueueItem>) => {
            setItems((current) =>
                current.map((item) =>
                    item.id === id ? { ...item, ...patch } : item,
                ),
            );
        },
        [],
    );

    const uploadOne = React.useCallback(
        async (item: QueueItem) => {
            const controller = new AbortController();

            abortControllerRef.current = controller;

            const throwIfCancelled = () => {
                if (cancelledRef.current.has(item.id)) {
                    throw new UploadCancelled();
                }
            };

            let uploadUlid: string | null = null;

            try {
                throwIfCancelled();
                patchItem(item.id, {
                    status: 'uploading',
                    message: null,
                    chunksSent: 0,
                });

                const begun = await fetch(beginRoute.url(), {
                    method: 'POST',
                    headers: {
                        ...jsonRequestHeaders(),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        filename: item.file.name,
                        size: item.file.size,
                        mime: item.file.type === '' ? null : item.file.type,
                    }),
                    signal: controller.signal,
                });

                if (!begun.ok) {
                    throw new Error(await readErrorMessage(begun));
                }

                const session = (await begun.json()) as {
                    ulid: string;
                    chunkBytes: number;
                    totalChunks: number;
                };

                uploadUlid = session.ulid;
                patchItem(item.id, { totalChunks: session.totalChunks });

                for (let index = 0; index < session.totalChunks; index++) {
                    throwIfCancelled();

                    const slice = item.file.slice(
                        index * session.chunkBytes,
                        (index + 1) * session.chunkBytes,
                    );

                    const body = new FormData();

                    body.append('chunk', slice, `${index}`);

                    const sent = await fetch(
                        chunkRoute.url({ upload: session.ulid, index }),
                        {
                            method: 'POST',
                            headers: jsonRequestHeaders(),
                            body,
                            signal: controller.signal,
                        },
                    );

                    if (!sent.ok) {
                        throw new Error(await readErrorMessage(sent));
                    }

                    patchItem(item.id, { chunksSent: index + 1 });
                }

                throwIfCancelled();

                const finished = await fetch(completeRoute.url(session.ulid), {
                    method: 'POST',
                    headers: {
                        ...jsonRequestHeaders(),
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(
                        item.altText === null ? {} : { alt_text: item.altText },
                    ),
                    signal: controller.signal,
                });

                if (!finished.ok) {
                    throw new Error(await readErrorMessage(finished));
                }

                const record = (await finished.json()) as UploadedRecord;

                patchItem(item.id, { status: 'done', message: null });

                return record;
            } catch (error) {
                const cancelled =
                    error instanceof UploadCancelled ||
                    (error instanceof DOMException &&
                        error.name === 'AbortError');

                if (uploadUlid !== null && cancelled) {
                    // Best effort: leaving the chunk directory behind is not
                    // fatal (media:prune-uploads sweeps it), so a failing
                    // abort must not mask the cancellation itself.
                    void fetch(abortRoute.url(uploadUlid), {
                        method: 'DELETE',
                        headers: jsonRequestHeaders(),
                    }).catch(() => undefined);
                }

                patchItem(item.id, {
                    status: cancelled ? 'cancelled' : 'failed',
                    message: cancelled
                        ? t('ui.uploader.cancelled')
                        : error instanceof Error
                          ? error.message
                          : t('ui.uploader.failed'),
                });

                return null;
            } finally {
                abortControllerRef.current = null;
            }
        },
        [patchItem],
    );

    const runQueue = React.useCallback(
        async (queued: QueueItem[]) => {
            setIsRunning(true);

            let succeeded = 0;

            for (const item of queued) {
                // Sequential on purpose — see the file header.
                const record = await uploadOne(item);

                if (record === null) {
                    continue;
                }

                if (onUploaded) {
                    try {
                        // Awaited, not fired off: see the prop's doc comment.
                        await onUploaded(record);
                    } catch (error) {
                        patchItem(item.id, {
                            status: 'failed',
                            message:
                                error instanceof Error
                                    ? error.message
                                    : t('ui.downloads.attach_failed'),
                        });

                        continue;
                    }
                }

                succeeded += 1;
            }

            setIsRunning(false);

            if (succeeded > 0) {
                toast.success(t('ui.uploader.uploaded', { count: succeeded }));

                if (!onUploaded) {
                    // A full reload, not `only: ['images', 'files']`: a
                    // partial reload merges props, so the shared `status`
                    // flash from an earlier action would survive and its
                    // toast would fire a second time.
                    router.reload();
                }
            }
        },
        [onUploaded, patchItem, uploadOne],
    );

    const start = React.useCallback(
        (selections: PendingSelection[]) => {
            const queued: QueueItem[] = selections.map((selection) => ({
                id: selection.id,
                file: selection.file,
                altText: isImageFile(selection.file)
                    ? selection.altText.trim()
                    : null,
                status: 'waiting',
                chunksSent: 0,
                totalChunks: 0,
                message: null,
            }));

            setItems((current) => [...current, ...queued]);
            void runQueue(queued);
        },
        [runQueue],
    );

    const accept = React.useCallback(
        (fileList: FileList | null) => {
            if (!fileList || fileList.length === 0) {
                return;
            }

            const selections: PendingSelection[] = [];

            for (const file of Array.from(fileList)) {
                if (file.size > maxBytes) {
                    toast.error(
                        t('ui.uploader.too_large', {
                            name: file.name,
                            size: formatBytes(file.size),
                            max: formatBytes(maxBytes),
                        }),
                    );
                    continue;
                }

                if (file.size === 0) {
                    toast.error(
                        t('ui.uploader.empty_file', { name: file.name }),
                    );
                    continue;
                }

                selections.push({
                    id: `${Date.now()}-${selections.length}-${file.name}`,
                    file,
                    altText: '',
                });
            }

            if (selections.length === 0) {
                return;
            }

            if (selections.some((selection) => isImageFile(selection.file))) {
                setAltErrors({});
                setPending(selections);

                return;
            }

            start(selections);
        },
        [maxBytes, start],
    );

    const confirmAltText = React.useCallback(() => {
        if (pending === null) {
            return;
        }

        const errors: Record<string, string> = {};

        for (const selection of pending) {
            if (
                isImageFile(selection.file) &&
                selection.altText.trim() === ''
            ) {
                errors[selection.id] = t('ui.uploader.alt_required');
            }
        }

        if (Object.keys(errors).length > 0) {
            setAltErrors(errors);

            return;
        }

        setPending(null);
        setAltErrors({});
        start(pending);
    }, [pending, start]);

    const cancelPending = React.useCallback(() => {
        setPending(null);
        setAltErrors({});
    }, []);

    const cancelItem = React.useCallback(
        (item: QueueItem) => {
            cancelledRef.current.add(item.id);

            if (item.status === 'uploading') {
                abortControllerRef.current?.abort();

                return;
            }

            patchItem(item.id, {
                status: 'cancelled',
                message: t('ui.uploader.cancelled'),
            });
        },
        [patchItem],
    );

    const clearFinished = React.useCallback(() => {
        setItems((current) =>
            current.filter(
                (item) =>
                    item.status === 'waiting' || item.status === 'uploading',
            ),
        );
    }, []);

    const hasFinished = items.some(
        (item) => item.status !== 'waiting' && item.status !== 'uploading',
    );

    return (
        <div className="grid gap-4">
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setIsDragging(false);
                    accept(event.dataTransfer.files);
                }}
                className={cn(
                    'rounded-lg border-2 border-dashed border-border bg-card transition-colors',
                    compact
                        ? 'flex flex-wrap items-center gap-3 p-4'
                        : 'flex flex-col items-center justify-center gap-3 p-8 text-center',
                    isDragging && 'border-primary bg-accent/10',
                )}
            >
                <CloudUpload
                    className={cn(
                        'text-muted-foreground',
                        compact ? 'size-5 shrink-0' : 'size-8',
                    )}
                    aria-hidden="true"
                />
                <div className={cn('grid gap-1', compact && 'min-w-0 flex-1')}>
                    <p className="font-medium">
                        {title ?? t('ui.uploader.drop_here')}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {description ??
                            t('ui.uploader.drop_hint', {
                                size: formatBytes(maxBytes),
                            })}
                    </p>
                </div>

                {/* The visible button below is the control; this input only
                    carries the picker. Left in the tab order it would be a
                    second, unnamed stop for exactly the same action, so it
                    is removed from it and hidden — in that order, since
                    aria-hidden on a focusable element is invalid. */}
                <input
                    ref={inputRef}
                    type="file"
                    multiple
                    tabIndex={-1}
                    aria-hidden="true"
                    className="sr-only"
                    onChange={(event) => {
                        accept(event.target.files);
                        // Let the same file be picked again after a failure.
                        event.target.value = '';
                    }}
                />

                <Button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    disabled={isRunning}
                >
                    <FileUp aria-hidden="true" />
                    {t('ui.uploader.choose_files')}
                </Button>
            </div>

            {items.length > 0 && (
                <div className="grid gap-2 rounded-lg border border-border bg-card p-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-semibold">
                            {t('ui.uploader.queue_heading')}
                        </h3>
                        {hasFinished && !isRunning && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFinished}
                            >
                                {t('ui.uploader.clear_list')}
                            </Button>
                        )}
                    </div>

                    <ul className="grid gap-3">
                        {items.map((item) => {
                            const progress =
                                item.totalChunks > 0
                                    ? item.chunksSent / item.totalChunks
                                    : item.status === 'done'
                                      ? 1
                                      : 0;

                            return (
                                <li key={item.id} className="grid gap-1.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        {item.status === 'uploading' && (
                                            <Spinner className="text-muted-foreground" />
                                        )}
                                        <span className="truncate text-sm font-medium">
                                            {item.file.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatBytes(item.file.size)}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                STATUS_CLASSES[item.status],
                                            )}
                                        >
                                            {t(STATUS_KEYS[item.status])}
                                        </span>

                                        <div className="ml-auto flex items-center gap-2">
                                            {item.totalChunks > 1 && (
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        'ui.uploader.chunk_progress',
                                                        {
                                                            index: Math.min(
                                                                item.chunksSent +
                                                                    (item.status ===
                                                                    'uploading'
                                                                        ? 1
                                                                        : 0),
                                                                item.totalChunks,
                                                            ),
                                                            total: item.totalChunks,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                            {(item.status === 'waiting' ||
                                                item.status ===
                                                    'uploading') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={t(
                                                        'ui.uploader.cancel_item',
                                                        {
                                                            name: item.file
                                                                .name,
                                                        },
                                                    )}
                                                    onClick={() =>
                                                        cancelItem(item)
                                                    }
                                                >
                                                    <X aria-hidden="true" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    <ProgressBar value={progress} />

                                    {item.message && (
                                        <p
                                            className={cn(
                                                'text-xs',
                                                item.status === 'failed'
                                                    ? 'text-error'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {item.message}
                                        </p>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        cancelPending();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.uploader.alt_dialog_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.uploader.alt_dialog_description')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid max-h-[50vh] gap-4 overflow-y-auto">
                        {(pending ?? [])
                            .filter((selection) => isImageFile(selection.file))
                            .map((selection) => (
                                <div key={selection.id} className="grid gap-2">
                                    <Label htmlFor={`alt-${selection.id}`}>
                                        {selection.file.name}
                                    </Label>
                                    <Textarea
                                        id={`alt-${selection.id}`}
                                        rows={2}
                                        value={selection.altText}
                                        onChange={(event) => {
                                            const { value } = event.target;

                                            setPending((current) =>
                                                (current ?? []).map(
                                                    (candidate) =>
                                                        candidate.id ===
                                                        selection.id
                                                            ? {
                                                                  ...candidate,
                                                                  altText:
                                                                      value,
                                                              }
                                                            : candidate,
                                                ),
                                            );
                                        }}
                                    />
                                    {altErrors[selection.id] && (
                                        <p className="text-sm text-error">
                                            {altErrors[selection.id]}
                                        </p>
                                    )}
                                </div>
                            ))}

                        {(pending ?? []).some(
                            (selection) => !isImageFile(selection.file),
                        ) && (
                            <p className="text-sm text-muted-foreground">
                                {t('ui.uploader.alt_others')}
                            </p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={cancelPending}>
                            {t('ui.actions.cancel')}
                        </Button>
                        <Button onClick={confirmAltText}>
                            {t('ui.uploader.start')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
