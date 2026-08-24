import { router } from '@inertiajs/react';
import { FolderOpen } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import PageDownloadController from '@/actions/App/Http/Controllers/Admin/PageDownloadController';
import { DownloadPickerDialog } from '@/components/admin/download-picker-dialog';
import type { AttachableFile } from '@/components/admin/download-picker-dialog';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBytes } from '@/lib/format';
import type { MediaFileKind } from '@/types';
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
    mediaFileId: number;
    filename: string;
    kind: MediaFileKind;
    mime: string;
    sizeBytes: number;
    educationLevelIds: number[];
};

type Props = {
    pageId: number;
    downloads: PageDownload[];
    levels: EducationLevelOption[];
    /**
     * Whether anything remains to attach — a single boolean sent by
     * App\Http\Controllers\Admin\PageController::edit instead of the whole
     * media library, which the dialog now searches for itself (see
     * resources/js/components/admin/file-picker-list.tsx). Decides only
     * whether the "choose a file" button appears; the dialog finds out what
     * that something actually is once it's open.
     */
    attachableFilesAvailable: boolean;
    maxBytes: number;
};

/**
 * Attaching, as a promise.
 *
 * The uploader awaits each link before starting the next file, and Inertia
 * cancels the visit in flight when a new one starts — so this has to settle
 * only when the visit is really finished. A rejection is what tells the
 * uploader to mark that file as failed rather than done.
 *
 * `preserveState` is load-bearing, not a nicety: without it the page component
 * remounts on the redirect back, taking the uploader — and the rest of the
 * batch it is in the middle of — with it.
 */
function attachDownload(
    pageId: number,
    payload: {
        media_file_id: number;
        label: string | null;
        education_levels: number[];
    },
): Promise<void> {
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
            // A cancelled visit fires neither of the two above — Inertia
            // cancels one in flight when another starts, and navigating away
            // does the same. Without this the promise never settles, the
            // uploader stays awaiting it, and the rest of the batch silently
            // never uploads while the row sits on "Bezig" forever.
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

    function remove() {
        if (
            !confirm(
                t('ui.downloads.confirm_remove', {
                    name: download.label ?? download.filename,
                }),
            )
        ) {
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
                <FileTypeIcon
                    mime={download.mime}
                    kind={download.kind}
                    className="size-5 shrink-0 text-muted-foreground"
                />
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
    attachableFilesAvailable,
    maxBytes,
}: Props) {
    const [picking, setPicking] = React.useState(false);
    const [selected, setSelected] = React.useState<number[]>([]);

    const attachedIds = downloads.map((download) => download.mediaFileId);

    // An empty library and one whose every file is already here read the same
    // from here now that neither ships to the browser to tell apart — the
    // dialog's own search says which, once it's open.
    const libraryMessage = t('ui.downloads.library_empty');

    function toggle(id: number, checked: boolean) {
        setSelected((current) =>
            checked ? [...current, id] : current.filter((one) => one !== id),
        );
    }

    /*
     * The dialog holds the choice until this resolves, so the rejection has
     * to travel: it is what leaves the dialog open, with the file still
     * chosen, when the attach failed and the validation message says why.
     */
    async function attachChosen(file: AttachableFile, label: string | null) {
        await attachDownload(pageId, {
            media_file_id: file.id,
            label,
            education_levels: selected,
        });

        setPicking(false);
    }

    /*
     * Uploading here attaches the file to this page as well as putting it in
     * the media library — that is the whole point of the affordance, and what
     * the owner means by "add this worksheet to this page".
     *
     * The ticked levels apply to everything in the batch. The label field does
     * not: it names one download, and a batch of four would all end up called
     * the same thing, so an uploaded file keeps its own filename until the
     * owner edits it.
     *
     * An image cannot be a download — the server decides the type by sniffing
     * the bytes — so it goes to the library and says so rather than failing.
     */
    async function uploadAndAttach(record: UploadedRecord) {
        if (record.type === 'image') {
            toast.info(
                t('ui.downloads.image_not_a_download', {
                    name: record.original_filename,
                }),
            );

            return;
        }

        await attachDownload(pageId, {
            media_file_id: record.id,
            label: null,
            education_levels: selected,
        });
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
                    onUploaded={uploadAndAttach}
                    title={t('ui.downloads.upload_title')}
                    description={t('ui.downloads.upload_description', {
                        size: formatBytes(maxBytes),
                    })}
                />

                {attachableFilesAvailable ? (
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
                    exclude={attachedIds}
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
