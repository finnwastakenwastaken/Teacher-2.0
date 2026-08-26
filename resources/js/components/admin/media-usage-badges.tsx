import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import type { MediaUsage } from '@/types';

/*
 * What a library item is being used for.
 *
 * Derived on the server from page_media_references and page_downloads, never
 * a flag the owner sets — see App\Http\Controllers\Admin\MediaLibraryController.
 * Both badges can show at once, and that is the case worth having: a diagram
 * embedded in a lesson *and* offered as a printable handout is one picture
 * used twice, which a single "this is a download" flag would have made
 * impossible to say.
 */

export type UsageFilter = 'all' | 'shown' | 'download' | 'unused';

export function matchesUsage(item: MediaUsage, filter: UsageFilter): boolean {
    switch (filter) {
        case 'shown':
            return item.shownOnPage;
        case 'download':
            return item.offeredAsDownload;
        case 'unused':
            return !item.shownOnPage && !item.offeredAsDownload;
        default:
            return true;
    }
}

export function MediaUsageBadges({ usage }: { usage: MediaUsage }) {
    if (!usage.shownOnPage && !usage.offeredAsDownload) {
        // `secondary`, not a warning colour: an unused file is not a problem,
        // it is one the owner has not placed yet — and until something points
        // at it, it is also private to everyone but them.
        return (
            <Badge variant="secondary">{t('ui.library.usage_unused')}</Badge>
        );
    }

    return (
        <>
            {usage.shownOnPage && (
                <Badge variant="secondary">{t('ui.library.usage_shown')}</Badge>
            )}
            {usage.offeredAsDownload && (
                <Badge variant="secondary">
                    {t('ui.library.usage_download')}
                </Badge>
            )}
        </>
    );
}
