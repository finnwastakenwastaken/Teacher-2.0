import { Head } from '@inertiajs/react';
import { DownloadGroups } from '@/components/content/download-groups';
import type { DownloadGroup } from '@/components/content/download-groups';
import { ImageLightbox } from '@/components/content/image-lightbox';
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
                // The thumbnail is cropped to a band by `object-cover`, so
                // enlarging it is the only way to see the whole picture.
                <ImageLightbox
                    images={[page.hero]}
                    eager
                    className="mt-6 mb-8"
                    imageClassName="max-h-72 rounded-lg object-cover"
                />
            )}

            {/*
             * A reading column, not the full container.
             *
             * A worksheet is text, and the layout's max-w-5xl put roughly 120
             * characters on a line — about double a comfortable measure, on
             * the one screen students actually read rather than scan. This
             * width measures **~73 characters in Dutch and ~76 in English** at
             * the body size below, which is the top of the usual 45–75 band.
             *
             * **Not expressed in `ch`, deliberately.** `ch` is the width of
             * the "0" glyph, and in Instrument Sans that is 11.57px against
             * 7.4–7.7px for average prose — 57% wider. `68ch` therefore reads
             * as "68 characters" and renders 100–104 of them, which is barely
             * an improvement on the bug it was meant to fix. Measured in the
             * browser, as §5 requires; the numbers above are what it returned.
             *
             * The banner above stays at container width on purpose: a wide
             * band over a narrow column reads as a deliberate layout, and
             * cropping it narrower would show less of the picture.
             *
             * The font size is set here rather than on each paragraph.
             * Tailwind's size utilities are rem-based, so only elements
             * without one of their own inherit it — headings and the download
             * meta keep their explicit sizes.
             */}
            <div className="mx-auto max-w-[35rem] text-[1.0625rem]">
                <div className="mb-8 space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight text-balance">
                        {page.title}
                    </h1>
                    {page.description && (
                        <p className="text-lg text-pretty text-muted-foreground">
                            {page.description}
                        </p>
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
            </div>
        </PublicLayout>
    );
}
