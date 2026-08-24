import { Head } from '@inertiajs/react';
import { DownloadGroups } from '@/components/content/download-groups';
import type { DownloadGroup } from '@/components/content/download-groups';
import { RichText } from '@/components/content/rich-text';
import { PublicBreadcrumbs } from '@/components/public-breadcrumbs';
import PublicLayout from '@/layouts/public-layout';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import type { PageMedia, TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

type Props = {
    page: {
        id: number;
        title: string;
        description: string | null;
        content: TipTapDoc | null;
        hero: { url: string; alt: string } | null;
    };
    breadcrumbs: BreadcrumbItemType[];
    /*
     * Keyed by ULID and built server-side from this page's media reference
     * rows, so the renderer can only ever show media the page actually
     * embeds. A ULID missing here has been deleted and renders as nothing.
     */
    media: PageMedia;
    /*
     * Already grouped by education level server-side, so a download tagged
     * for two tracks appears under both headings.
     */
    downloadGroups: DownloadGroup[];
};

export default function ContentPage({
    page,
    breadcrumbs,
    media,
    downloadGroups,
}: Props) {
    const hasContent = (page.content?.content?.length ?? 0) > 0;

    return (
        <PublicLayout>
            <Head title={page.title} />

            <PublicBreadcrumbs items={breadcrumbs} />

            {page.hero && (
                <img
                    src={page.hero.url}
                    alt={page.hero.alt}
                    className="mt-6 mb-8 max-h-72 w-full rounded-lg object-cover"
                />
            )}

            <div className="mb-8 space-y-2">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {page.title}
                </h1>
                {page.description && (
                    <p className="text-muted-foreground">{page.description}</p>
                )}
            </div>

            {hasContent ? (
                <RichText doc={page.content} media={media} />
            ) : (
                downloadGroups.length === 0 && (
                    <p className="text-muted-foreground">
                        {t('ui.public.page_empty')}
                    </p>
                )
            )}

            <DownloadGroups groups={downloadGroups} />
        </PublicLayout>
    );
}
