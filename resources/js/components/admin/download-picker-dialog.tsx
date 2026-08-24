import * as React from 'react';
import { FilePickerList } from '@/components/admin/file-picker-list';
import type { PickableFile } from '@/components/admin/file-picker-list';
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
import { t } from '@/lib/i18n';

/*
 * Choosing the file for a new download.
 *
 * The same list the editor's file picker shows, with a different footer: this
 * one does not insert anything, it creates a page_downloads row. So a choice
 * is held rather than acted on immediately, and the label field travels with
 * it — the levels come from the section behind the dialog, because they apply
 * to whatever is uploaded there too.
 *
 * Mounted only while open, like the editor's dialogs, so the choice and the
 * label start empty every time without an effect to reset them.
 */

/** The numeric id is what the attach endpoint keys on; PickableFile already
 *  carries it — see resources/js/components/admin/file-picker-list.tsx. */
export type AttachableFile = PickableFile;

type Props = {
    /** Ids already on this page, so the search excludes them server-side
     *  instead of the whole library being fetched to filter here. */
    exclude: number[];
    /** Shown when a search with no query still comes back empty — an empty
     *  library and one whose every file is already here look the same from
     *  here, so the message has to speak to both. */
    emptyMessage: string;
    /** The levels ticked in the section, so the choice is not made blind. */
    levelNames: string[];
    /** Resolves when the row exists; rejects when the visit failed. */
    onAttach: (file: AttachableFile, label: string | null) => Promise<void>;
    onClose: () => void;
};

export function DownloadPickerDialog({
    exclude,
    emptyMessage,
    levelNames,
    onAttach,
    onClose,
}: Props) {
    const [chosen, setChosen] = React.useState<AttachableFile | null>(null);
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

                <FilePickerList
                    idPrefix="download-picker"
                    emptyMessage={emptyMessage}
                    selectedUlid={chosen?.ulid ?? null}
                    exclude={exclude}
                    onSelect={setChosen}
                />

                {chosen !== null && (
                    <div className="grid gap-2">
                        {/* A long list scrolls, so the choice is repeated here
                            where the button that acts on it lives. */}
                        <p className="text-sm" role="status">
                            {t('ui.downloads.chosen_file', {
                                name: chosen.original_filename,
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
