import * as React from 'react';
import { FilePickerList } from '@/components/admin/file-picker-list';
import type { PickableFile } from '@/components/admin/file-picker-list';
import type { ImageOption } from '@/components/admin/image-field';
import { ImagePickerList } from '@/components/admin/image-picker-list';
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
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { t } from '@/lib/i18n';

/*
 * Same lists the editor's pickers show, with a different footer: this one
 * creates a page_downloads row rather than inserting a node, so the choice
 * and label are held until "Add" instead of acted on immediately. Two
 * libraries behind one switch, shown separately rather than merged, because
 * a document is recognised by its name and a picture by looking at it — one
 * list would have to pick a losing affordance for one of them. Mounted only
 * while open, so both start empty without a reset effect.
 */

/** The numeric id is what the attach endpoint keys on; both shapes carry it. */
export type AttachableFile = PickableFile;

/**
 * What was picked, and from which library. A discriminated union rather than
 * two nullable fields, so "both at once" is not a state that can be reached
 * from here — the Form Request and a CHECK constraint refuse it too.
 */
export type AttachableChoice =
    | { source: 'file'; file: PickableFile }
    | { source: 'image'; image: ImageOption };

type Props = {
    /** Media-file ids already on this page, so the search excludes them
     *  server-side instead of the whole library being fetched to filter. */
    excludeFiles: number[];
    /** The same, for the image library. */
    excludeImages: number[];
    /** Shown when a search with no query still comes back empty — an empty
     *  library and one whose every file is already here look the same from
     *  here, so the message has to speak to both. */
    emptyMessage: string;
    /** The levels ticked in the section, so the choice is not made blind. */
    levelNames: string[];
    /** Resolves when the row exists; rejects when the visit failed. */
    onAttach: (choice: AttachableChoice, label: string | null) => Promise<void>;
    onClose: () => void;
};

function chosenName(choice: AttachableChoice): string {
    return choice.source === 'file'
        ? choice.file.original_filename
        : choice.image.filename;
}

export function DownloadPickerDialog({
    excludeFiles,
    excludeImages,
    emptyMessage,
    levelNames,
    onAttach,
    onClose,
}: Props) {
    const [source, setSource] = React.useState<'file' | 'image'>('file');
    const [chosen, setChosen] = React.useState<AttachableChoice | null>(null);
    const [label, setLabel] = React.useState('');
    const [attaching, setAttaching] = React.useState(false);

    function attach() {
        if (chosen === null) {
            return;
        }

        setAttaching(true);

        onAttach(chosen, label === '' ? null : label)
            // The section closes this dialog on success and shows the error
            // itself otherwise; either way the button must stop spinning.
            .catch(() => undefined)
            .finally(() => setAttaching(false));
    }

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
                    <DialogTitle>{t('ui.downloads.add_heading')}</DialogTitle>
                    <DialogDescription>
                        {t('ui.downloads.dialog_description')}
                    </DialogDescription>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    {t('ui.downloads.dialog_levels', {
                        names:
                            levelNames.length === 0
                                ? t('ui.downloads.everyone')
                                : levelNames.join(', '),
                    })}
                </p>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={source}
                    // A deselect sends an empty string; there is always a
                    // library on show, so that is simply ignored.
                    onValueChange={(next) => {
                        if (next === 'file' || next === 'image') {
                            setSource(next);
                            // The choice belongs to the library it came from.
                            // Leaving it behind would let the owner attach a
                            // document while looking at a grid of pictures.
                            setChosen(null);
                        }
                    }}
                    aria-label={t('ui.downloads.source_label')}
                    className="w-fit"
                >
                    <ToggleGroupItem value="file">
                        {t('ui.downloads.source_files')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="image">
                        {t('ui.downloads.source_images')}
                    </ToggleGroupItem>
                </ToggleGroup>

                {source === 'file' ? (
                    <FilePickerList
                        idPrefix="download-picker"
                        emptyMessage={emptyMessage}
                        selectedUlid={
                            chosen?.source === 'file' ? chosen.file.ulid : null
                        }
                        exclude={excludeFiles}
                        onSelect={(file) => setChosen({ source: 'file', file })}
                    />
                ) : (
                    <ImagePickerList
                        idPrefix="download-picker-image"
                        emptyMessage={emptyMessage}
                        selectedId={
                            chosen?.source === 'image' ? chosen.image.id : null
                        }
                        exclude={excludeImages}
                        onSelect={(image) =>
                            setChosen({ source: 'image', image })
                        }
                    />
                )}

                {chosen !== null && (
                    <div className="grid gap-2">
                        {/* A long list scrolls, so the choice is repeated here
                            where the button that acts on it lives. */}
                        <p className="text-sm" role="status">
                            {t('ui.downloads.chosen_file', {
                                name: chosenName(chosen),
                            })}
                        </p>

                        <Label htmlFor="download-picker-label">
                            {t('ui.downloads.label_field')}
                        </Label>
                        <Input
                            id="download-picker-label"
                            value={label}
                            placeholder={t('ui.downloads.label_placeholder')}
                            onChange={(event) => setLabel(event.target.value)}
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('ui.actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        disabled={chosen === null || attaching}
                        onClick={attach}
                    >
                        {attaching && <Spinner aria-hidden="true" />}
                        {t('ui.actions.add')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
