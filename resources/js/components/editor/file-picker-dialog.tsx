import { FilePickerList } from '@/components/admin/file-picker-list';
import type { PickableFile } from '@/components/admin/file-picker-list';
import { MediaUploader } from '@/components/admin/media-uploader';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AcceptedFormats } from '@/types';
import { t } from '@/lib/i18n';

type Props = {
    maxBytes: number;
    acceptedFormats: AcceptedFormats;
    /** How many uploads this dialog has already put on the page. */
    uploadedCount: number;
    /** Registers the file with the library and inserts it. */
    onUploaded: (record: UploadedRecord) => void;
    onSelect: (file: PickableFile) => void;
    onClose: () => void;
};

/**
 * Mounted only while open, so state starts fresh without a reset effect.
 * Unlike the image picker, an upload goes straight into the document (one
 * block, no batch to assemble); the dialog stays open for adding several in
 * a row. Clicking a row inserts it, so there's no selection to carry.
 */
export function FilePickerDialog({
    maxBytes,
    acceptedFormats,
    uploadedCount,
    onUploaded,
    onSelect,
    onClose,
}: Props) {
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
                    <DialogTitle>{t('ui.editor.insert_file')}</DialogTitle>
                    <DialogDescription>
                        {t('ui.editor.file_dialog.description')}
                    </DialogDescription>
                </DialogHeader>

                <MediaUploader
                    compact
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    title={t('ui.editor.file_dialog.upload_title')}
                    description={t('ui.editor.file_dialog.upload_description')}
                    onUploaded={onUploaded}
                />

                {uploadedCount > 0 && (
                    <p className="text-sm text-muted-foreground" role="status">
                        {t('ui.editor.file_dialog.added', {
                            count: uploadedCount,
                        })}{' '}
                        {t('ui.editor.file_dialog.remember_to_save')}
                    </p>
                )}

                <FilePickerList
                    idPrefix="file-picker"
                    emptyMessage={t('ui.editor.file_dialog.empty')}
                    onSelect={onSelect}
                />

                <div className="flex justify-end">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('ui.actions.cancel')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
