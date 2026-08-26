import { router } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';
import * as React from 'react';
import PageDownloadController from '@/actions/App/Http/Controllers/Admin/PageDownloadController';
import { DownloadPickerDialog } from '@/components/admin/download-picker-dialog';
import type { AttachableChoice } from '@/components/admin/download-picker-dialog';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { confirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/format';
import type { AcceptedFormats, DownloadKind } from '@/types';
import { t } from '@/lib/i18n';

/*
 * The downloads section of a page.
 *
 * Separate from the settings form and from the editor: these are relational
 * rows, each saved on its own, so that adding a worksheet does not require
 * re-submitting the page body. Attaching a file here also publishes it —
 * until something on a page points at a file, it is private.
 */

export type EducationLevelOption = {
    id: number;
    name: string;
    slug: string;
};

export type PageDownload = {
    ulid: string;
    label: string | null;
    sortOrder: number;
    downloadsCount: number;
    /** Which library this attachment names. Exactly one, always. */
    source: 'file' | 'image';
    /** The id in that library, which is what the picker excludes on. */
    mediaId: number;
    filename: string;
    kind: DownloadKind;
    mime: string;
    sizeBytes: number;
    /** A thumbnail, for an offered picture only. Gated like every media URL;
     *  the owner is authenticated, so it renders here and 403s for a student
     *  the page does not let in. */
    previewUrl: string | null;
    educationLevelIds: number[];
};

type Props = {
    pageId: number;
    downloads: PageDownload[];
    levels: EducationLevelOption[];
    /**
     * Whether anything remains to attach, in either library — a single
     * boolean sent by PageController::edit instead of the whole library,
     * which the dialog now searches for itself. Decides only whether the
     * button appears; the dialog finds out what that something is once
     * it's open.
     */
    attachableMediaAvailable: boolean;
    maxBytes: number;
    acceptedFormats: AcceptedFormats;
};

/**
 * The attach payload names exactly one library. Built here rather than in
 * the dialog so that the two ways of attaching — picking something that
 * already exists and uploading something new — reach the endpoint through
 * one shape.
 */
type AttachPayload = {
    media_file_id: number | null;
    image_id: number | null;
    label: string | null;
    education_levels: number[];
};

/** Exactly one key carries an id; the other is explicitly null. */
function payloadFor(
    choice: AttachableChoice,
    label: string | null,
    levels: number[],
): AttachPayload {
    return {
        media_file_id: choice.source === 'file' ? choice.file.id : null,
        image_id: choice.source === 'image' ? choice.image.id : null,
        label,
        education_levels: levels,
    };
}

/**
 * Attaching, as a promise the uploader awaits before starting the next file
 * — Inertia cancels an in-flight visit when a new one starts, so this must
 * settle only when the visit really finishes; a rejection marks that file
 * failed. `preserveState` matters: without it the page remounts on the
 * redirect back, taking the uploader — and the rest of its batch — with it.
 */
function attachDownload(pageId: number, payload: AttachPayload): Promise<void> {
    return new Promise((resolve, reject) => {
        let settled = false;

        router.post(PageDownloadController.store(pageId).url, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                settled = true;
                resolve();
            },
            onError: () => {
                settled = true;
                reject(new Error(t('ui.downloads.attach_failed')));
            },
            // A cancelled visit fires neither onSuccess nor onError, so
            // without this the promise never settles and the batch stalls.
            onFinish: () => {
                if (!settled) {
                    reject(new Error(t('ui.downloads.attach_cancelled')));
                }
            },
        });
    });
}

function LevelCheckboxes({
    levels,
    selected,
    onToggle,
    idPrefix,
}: {
    levels: EducationLevelOption[];
    selected: number[];
    onToggle: (id: number, checked: boolean) => void;
    idPrefix: string;
}) {
    if (levels.length === 0) {
        return (
            <p className="text-xs text-muted-foreground">
                {t('ui.downloads.no_levels')}
            </p>
        );
    }

    return (
        <div className="flex flex-wrap gap-4">
            {levels.map((level) => (
                <div key={level.id} className="flex items-center gap-2">
                    <Checkbox
                        id={`${idPrefix}-level-${level.id}`}
                        checked={selected.includes(level.id)}
                        onCheckedChange={(checked) =>
                            onToggle(level.id, checked === true)
                        }
                    />
                    <Label
                        htmlFor={`${idPrefix}-level-${level.id}`}
                        className="font-normal"
                    >
                        {level.name}
                    </Label>
                </div>
            ))}
        </div>
    );
}

function DownloadRow({
    download,
    levels,
}: {
    download: PageDownload;
    levels: EducationLevelOption[];
}) {
    const [editing, setEditing] = React.useState(false);
    const [label, setLabel] = React.useState(download.label ?? '');
    const [sortOrder, setSortOrder] = React.useState(download.sortOrder);
    const [selected, setSelected] = React.useState<number[]>(
        download.educationLevelIds,
    );

    function toggle(id: number, checked: boolean) {
        setSelected((current) =>
            checked ? [...current, id] : current.filter((one) => one !== id),
        );
    }

    function save() {
        router.patch(
            PageDownloadController.update(download.ulid).url,
            {
                label: label === '' ? null : label,
                sort_order: sortOrder,
                education_levels: selected,
            },
            { preserveScroll: true, onSuccess: () => setEditing(false) },
        );
    }

    async function remove() {
        const confirmed = await confirm({
            title: t('ui.downloads.confirm_remove_title'),
            description: t('ui.downloads.confirm_remove', {
                name: download.label ?? download.filename,
            }),
            confirmLabel: t('ui.actions.remove'),
            destructive: true,
        });

        if (!confirmed) {
            return;
        }

        router.delete(PageDownloadController.destroy(download.ulid).url, {
            preserveScroll: true,
        });
    }

    const levelNames = levels
        .filter((level) => download.educationLevelIds.includes(level.id))
        .map((level) => level.name);

    return (
        <li className="space-y-3 p-4">
            <div className="flex flex-wrap items-center gap-2">
                {download.previewUrl === null ? (
                    <FileTypeIcon
                        mime={download.mime}
                        kind={download.kind}
                        className="size-5 shrink-0 text-muted-foreground"
                    />
                ) : (
                    // A page handing out three posters is unreadable as three
                    // identical icons, and telling them apart is the whole
                    // reason images are chosen visually elsewhere.
                    <img
                        src={download.previewUrl}
                        alt=""
                        loading="lazy"
                        className="size-8 shrink-0 rounded-sm border border-border object-cover"
                    />
                )}
                <span className="font-medium">
                    {download.label ?? download.filename}
                </span>
                {levelNames.length === 0 ? (
                    <Badge variant="secondary">
                        {t('ui.downloads.everyone')}
                    </Badge>
                ) : (
                    levelNames.map((name) => (
                        <Badge key={name} variant="secondary">
                            {name}
                        </Badge>
                    ))
                )}
                <span className="text-xs text-muted-foreground">
                    {download.filename} · {formatBytes(download.sizeBytes)} ·{' '}
                    {t('ui.downloads.fetched', {
                        count: download.downloadsCount,
                    })}
                </span>

                <div className="ml-auto flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing((open) => !open)}
                    >
                        {editing
                            ? t('ui.downloads.close')
                            : t('ui.actions.edit')}
                    </Button>
                    <Button variant="destructive" size="sm" onClick={remove}>
                        {t('ui.actions.delete')}
                    </Button>
                </div>
            </div>

            {editing && (
                <div className="grid gap-4 rounded-lg border border-border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`label-${download.ulid}`}>
                                {t('ui.downloads.label_field')}
                            </Label>
                            <Input
                                id={`label-${download.ulid}`}
                                value={label}
                                placeholder={download.filename}
                                onChange={(event) =>
                                    setLabel(event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`order-${download.ulid}`}>
                                {t('ui.downloads.order')}
                            </Label>
                            <Input
                                id={`order-${download.ulid}`}
                                type="number"
                                min={0}
                                className="w-24"
                                value={sortOrder}
                                onChange={(event) =>
                                    setSortOrder(Number(event.target.value))
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('ui.downloads.levels')}</Label>
                        <LevelCheckboxes
                            levels={levels}
                            selected={selected}
                            onToggle={toggle}
                            idPrefix={download.ulid}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('ui.downloads.levels_hint')}
                        </p>
                    </div>

                    <Button size="sm" className="w-fit" onClick={save}>
                        {t('ui.actions.save')}
                    </Button>
                </div>
            )}
        </li>
    );
}

export function PageDownloads({
    pageId,
    downloads,
    levels,
    attachableMediaAvailable,
    maxBytes,
    acceptedFormats,
}: Props) {
    const [picking, setPicking] = React.useState(false);
    const [selected, setSelected] = React.useState<number[]>([]);

    // Two lists, because the ids come from two tables and excluding an image
    // by a media file's id would hide the wrong picture.
    const attachedFileIds = downloads
        .filter((download) => download.source === 'file')
        .map((download) => download.mediaId);
    const attachedImageIds = downloads
        .filter((download) => download.source === 'image')
        .map((download) => download.mediaId);

    // The dialog's own search distinguishes "empty" from "all attached".
    const libraryMessage = t('ui.downloads.library_empty');

    function toggle(id: number, checked: boolean) {
        setSelected((current) =>
            checked ? [...current, id] : current.filter((one) => one !== id),
        );
    }

    // The dialog holds the choice until this resolves, so a rejection here
    // is what leaves it open — with the choice still made — showing why the
    // attach failed.
    async function attachChosen(
        choice: AttachableChoice,
        label: string | null,
    ) {
        await attachDownload(pageId, payloadFor(choice, label, selected));

        setPicking(false);
    }

    /*
     * Uploading here attaches the file to the page, not just the library —
     * the whole point of the affordance. Ticked levels apply to the whole
     * batch; the label doesn't, so an uploaded file keeps its own filename
     * until edited. A dropped image attaches like anything else: the server
     * sniffs the bytes, lands it in `images` and converts it to WebP on the
     * way in — which is what makes it openable at all on a phone that shot
     * it as HEIC — and the attachment names that same row.
     */
    async function uploadAndAttach(record: UploadedRecord) {
        await attachDownload(
            pageId,
            record.type === 'image'
                ? {
                      media_file_id: null,
                      image_id: record.id,
                      label: null,
                      education_levels: selected,
                  }
                : {
                      media_file_id: record.id,
                      image_id: null,
                      label: null,
                      education_levels: selected,
                  },
        );
    }

    return (
        <div className="grid gap-4">
            {downloads.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('ui.downloads.empty')}
                </p>
            ) : (
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {downloads.map((download) => (
                        <DownloadRow
                            key={download.ulid}
                            download={download}
                            levels={levels}
                        />
                    ))}
                </ul>
            )}

            <div className="grid gap-4 rounded-lg border border-dashed border-border p-4">
                <h3 className="text-sm font-semibold">
                    {t('ui.downloads.add_heading')}
                </h3>

                <div className="grid gap-2">
                    <Label>{t('ui.downloads.levels')}</Label>
                    <LevelCheckboxes
                        levels={levels}
                        selected={selected}
                        onToggle={toggle}
                        idPrefix="new-download"
                    />
                    <p className="text-xs text-muted-foreground">
                        {t('ui.downloads.new_levels_hint')}
                    </p>
                </div>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    onUploaded={uploadAndAttach}
                    title={t('ui.downloads.upload_title')}
                    description={t('ui.downloads.upload_description', {
                        size: formatBytes(maxBytes),
                    })}
                />

                {attachableMediaAvailable ? (
                    <Button
                        size="sm"
                        variant="outline"
                        className="w-fit"
                        onClick={() => setPicking(true)}
                    >
                        <FolderOpen aria-hidden="true" />
                        {t('ui.downloads.choose_file')}
                    </Button>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {libraryMessage}
                    </p>
                )}
            </div>

            {/* Mounted only while open, like the editor's pickers, so the
                chosen file and the label start empty every time. */}
            {picking && (
                <DownloadPickerDialog
                    excludeFiles={attachedFileIds}
                    excludeImages={attachedImageIds}
                    emptyMessage={libraryMessage}
                    levelNames={levels
                        .filter((level) => selected.includes(level.id))
                        .map((level) => level.name)}
                    onAttach={attachChosen}
                    onClose={() => setPicking(false)}
                />
            )}
        </div>
    );
}
