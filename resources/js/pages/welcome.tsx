import { Head } from '@inertiajs/react';
import { ContentSummaryCard } from '@/components/content-summary-card';
import type { IconData } from '@/components/icon';
import type { ContentSummary } from '@/components/content-summary-card';
import { RichText } from '@/components/content/rich-text';
import PublicLayout from '@/layouts/public-layout';
import type { TipTapDoc } from '@/types/tiptap';

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
            {/* Deliberately empty: the title resolver in app.tsx falls back
                to the site's own name, so the homepage tab reads "Natuurkunde
                bij De Vries" rather than repeating the heading beside it. It
                still has to be set, or a client-side navigation back here
                would leave the previous page's title in the tab. */}
            <Head title="" />

            {home.banner && (
                <img
                    src={home.banner.url}
                    alt={home.banner.alt}
                    className="mb-8 max-h-72 w-full rounded-lg object-cover"
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
                    Er is nog geen lesmateriaal gepubliceerd.
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
