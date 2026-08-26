import { Head } from '@inertiajs/react';
import { ContentSummaryCard } from '@/components/content-summary-card';
import type { IconData } from '@/components/icon';
import type { ContentSummary } from '@/components/content-summary-card';
import { ImageLightbox } from '@/components/content/image-lightbox';
import { RichText } from '@/components/content/rich-text';
import PublicLayout from '@/layouts/public-layout';
import type { TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

/*
 * The public homepage: the owner's introduction, then the category grid.
 *
 * The grid always renders and cannot be edited away — it is the site's only
 * entry point. See the technical reference, "What this is" / HomeController.
 */

type Props = {
    home: {
        heading: string;
        subheading: string | null;
        content: TipTapDoc | null;
        banner: { url: string; alt: string } | null;
    };
    topics: (ContentSummary & { slug: string })[];
    icons: Record<string, IconData>;
};

export default function Welcome({ home, topics, icons }: Props) {
    return (
        <PublicLayout>
            {/* Empty on purpose so app.tsx's title resolver falls back to the
                site name; still needs setting, or a client-side nav back here
                would keep the previous page's title. */}
            <Head title="" />

            {home.banner && (
                // Cropped to a band here, whole in the overlay — see the
                // page banner, which is the same trade.
                <ImageLightbox
                    images={[home.banner]}
                    eager
                    className="mb-8"
                    imageClassName="max-h-72 rounded-lg object-cover"
                />
            )}

            <div className="mb-8 space-y-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {home.heading}
                </h1>
                {home.subheading && (
                    <p className="text-muted-foreground">{home.subheading}</p>
                )}
            </div>

            {home.content && (
                <div className="mb-10">
                    {/* An empty media map: the homepage introduction cannot
                        contain embeds at all, because the server strips them
                        (App\Support\PageContent::sanitiseWithoutEmbeds). */}
                    <RichText doc={home.content} media={{}} />
                </div>
            )}

            {topics.length === 0 ? (
                <p className="text-muted-foreground">
                    {t('ui.public.nothing_published')}
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {topics.map((topic) => (
                        <ContentSummaryCard
                            key={topic.id}
                            item={topic}
                            icon={topic.icon ? icons[topic.icon] : null}
                        />
                    ))}
                </div>
            )}
        </PublicLayout>
    );
}
