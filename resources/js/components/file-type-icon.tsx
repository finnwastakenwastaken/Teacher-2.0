import {
    FileArchive,
    FileSpreadsheet,
    FileText,
    Film,
    Presentation,
} from 'lucide-react';
import type { MediaFileKind } from '@/types';

/**
 * The MIME is the finer signal; `kind` only distinguishes video from the
 * rest. Shared by the media library, the editor and the public download card
 * so they cannot drift apart on what a spreadsheet looks like.
 *
 * A component rather than a function returning an icon: picking a component
 * during render remounts it on every pass, and the icons are static.
 */
export function FileTypeIcon({
    mime,
    kind,
    className,
}: {
    mime: string;
    kind: MediaFileKind;
    className?: string;
}) {
    if (kind === 'video') {
        return <Film className={className} aria-hidden="true" />;
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
