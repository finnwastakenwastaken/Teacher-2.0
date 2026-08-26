import {
    FileArchive,
    FileSpreadsheet,
    FileText,
    Film,
    Image as ImageIcon,
    Presentation,
} from 'lucide-react';
import type { DownloadKind } from '@/types';

/**
 * MIME is the finer signal; `kind` distinguishes video and image from the
 * rest. Shared by the media library, the editor and the public download card
 * so they cannot drift on what a spreadsheet looks like. A component, not a
 * function returning one — the latter would remount on every render.
 */
export function FileTypeIcon({
    mime,
    kind,
    className,
}: {
    mime: string;
    kind: DownloadKind;
    className?: string;
}) {
    if (kind === 'video') {
        return <Film className={className} aria-hidden="true" />;
    }

    // A picture offered as a download — a poster, a scanned worksheet. It is
    // an `images` row, so it has no `kind` column of its own; the library it
    // came from is the answer.
    if (kind === 'image') {
        return <ImageIcon className={className} aria-hidden="true" />;
    }

    if (
        mime.includes('spreadsheet') ||
        mime.includes('excel') ||
        mime === 'text/csv'
    ) {
        return <FileSpreadsheet className={className} aria-hidden="true" />;
    }

    if (mime.includes('presentation') || mime.includes('powerpoint')) {
        return <Presentation className={className} aria-hidden="true" />;
    }

    if (mime === 'application/zip') {
        return <FileArchive className={className} aria-hidden="true" />;
    }

    return <FileText className={className} aria-hidden="true" />;
}
