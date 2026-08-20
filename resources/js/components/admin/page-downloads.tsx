import { router } from '@inertiajs/react';
import * as React from 'react';
import { toast } from 'sonner';
import PageDownloadController from '@/actions/App/Http/Controllers/Admin/PageDownloadController';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatBytes } from '@/lib/format';
import type { MediaFileKind } from '@/types';

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

export type LibraryFile = {
    ulid: string;
    kind: MediaFileKind;
    mime: string;
    size_bytes: number;
    original_filename: string;
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
    /* Media files, with the numeric id the attachment endpoint needs. */
    files: (LibraryFile & { id: number })[];
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
                reject(
                    new Error(
                        'Het bestand is geüpload, maar kon niet aan deze pagina worden gekoppeld.',
                    ),
                );
            },
            // A cancelled visit fires neither of the two above — Inertia
            // cancels one in flight when another starts, and navigating away
            // does the same. Without this the promise never settles, the
            // uploader stays awaiting it, and the rest of the batch silently
            // never uploads while the row sits on "Bezig" forever.
            onFinish: () => {
                if (!settled) {
                    reject(
                        new Error(
                            'Het koppelen aan deze pagina is afgebroken. Het bestand staat wel in de mediabibliotheek.',
                        ),
                    );
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
                Er zijn nog geen niveaus. Voeg ze toe bij Niveaus.
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
                `"${download.label ?? download.filename}" van deze pagina halen? Het bestand zelf blijft in de mediabibliotheek.`,
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
                    <Badge variant="secondary">Voor iedereen</Badge>
                ) : (
                    levelNames.map((name) => (
                        <Badge key={name} variant="secondary">
                            {name}
                        </Badge>
                    ))
                )}
                <span className="text-xs text-muted-foreground">
                    {download.filename} · {formatBytes(download.sizeBytes)} ·{' '}
                    {download.downloadsCount}× gedownload
                </span>

                <div className="ml-auto flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setEditing((open) => !open)}
                    >
                        {editing ? 'Sluiten' : 'Bewerken'}
                    </Button>
                    <Button variant="destructive" size="sm" onClick={remove}>
                        Verwijderen
                    </Button>
                </div>
            </div>

            {editing && (
                <div className="grid gap-4 rounded-lg border border-border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`label-${download.ulid}`}>
                                Naam op de pagina
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
                                Volgorde
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
                        <Label>Niveaus</Label>
                        <LevelCheckboxes
                            levels={levels}
                            selected={selected}
                            onToggle={toggle}
                            idPrefix={download.ulid}
                        />
                        <p className="text-xs text-muted-foreground">
                            Laat alles leeg voor een download die voor iedereen
                            bedoeld is.
                        </p>
                    </div>

                    <Button size="sm" className="w-fit" onClick={save}>
                        Opslaan
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
    files,
    maxBytes,
}: Props) {
    const [fileId, setFileId] = React.useState<string>('');
    const [label, setLabel] = React.useState('');
    const [selected, setSelected] = React.useState<number[]>([]);

    const attached = new Set(downloads.map((download) => download.mediaFileId));
    const available = files.filter((file) => !attached.has(file.id));

    function toggle(id: number, checked: boolean) {
        setSelected((current) =>
            checked ? [...current, id] : current.filter((one) => one !== id),
        );
    }

    function add() {
        void attachDownload(pageId, {
            media_file_id: Number(fileId),
            label: label === '' ? null : label,
            education_levels: selected,
        })
            .then(() => {
                setFileId('');
                setLabel('');
            })
            // The error is already on screen as a validation message; this
            // only stops an unhandled rejection.
            .catch(() => undefined);
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
                `"${record.original_filename}" is een afbeelding en staat nu in de mediabibliotheek. Downloads zijn documenten of video's.`,
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
                    Nog geen downloads op deze pagina.
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
                <h3 className="text-sm font-semibold">Download toevoegen</h3>

                <div className="grid gap-2">
                    <Label>Niveaus</Label>
                    <LevelCheckboxes
                        levels={levels}
                        selected={selected}
                        onToggle={toggle}
                        idPrefix="new-download"
                    />
                    <p className="text-xs text-muted-foreground">
                        Geldt voor wat je hieronder toevoegt of uploadt. Laat
                        alles leeg voor een download die voor iedereen bedoeld
                        is.
                    </p>
                </div>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    onUploaded={uploadAndAttach}
                    title="Nieuw bestand uploaden"
                    description={`Komt meteen als download op deze pagina te staan, met de niveaus hierboven. Maximaal ${formatBytes(maxBytes)} per bestand.`}
                />

                {available.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {files.length === 0
                            ? 'Er staan nog geen bestanden in de mediabibliotheek.'
                            : 'Alle bestanden uit de mediabibliotheek staan al op deze pagina.'}
                    </p>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="new-download-file">
                                    Bestand
                                </Label>
                                <Select
                                    value={fileId}
                                    onValueChange={setFileId}
                                >
                                    <SelectTrigger
                                        id="new-download-file"
                                        className="w-72"
                                    >
                                        <SelectValue placeholder="Kies een bestand" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {available.map((file) => (
                                            <SelectItem
                                                key={file.ulid}
                                                value={String(file.id)}
                                            >
                                                {file.original_filename} (
                                                {formatBytes(file.size_bytes)})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="new-download-label">
                                    Naam op de pagina
                                </Label>
                                <Input
                                    id="new-download-label"
                                    value={label}
                                    placeholder="Optioneel"
                                    onChange={(event) =>
                                        setLabel(event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <Button
                            size="sm"
                            className="w-fit"
                            disabled={fileId === ''}
                            onClick={add}
                        >
                            Toevoegen
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}
