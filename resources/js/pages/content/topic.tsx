import { Head, Link } from '@inertiajs/react';
import { RichText } from '@/components/content/rich-text';
import { ContentSummaryCard } from '@/components/content-summary-card';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import type { ContentSummary } from '@/components/content-summary-card';
import { PublicBreadcrumbs } from '@/components/public-breadcrumbs';
import PublicLayout from '@/layouts/public-layout';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';
import type { TipTapDoc } from '@/types/tiptap';

type Props = {
    topic: {
        id: number;
        title: string;
        description: string | null;
        content: TipTapDoc | null;
    };
    breadcrumbs: BreadcrumbItemType[];
    childTopics: ContentSummary[];
    pages: ContentSummary[];
    icons: Record<string, IconData>;
};

export default function ContentTopic({
    topic,
    breadcrumbs,
    childTopics,
    pages,
    icons,
}: Props) {
    const hasContent = childTopics.length > 0 || pages.length > 0;

    return (
        <PublicLayout>
            <Head title={topic.title} />

            <PublicBreadcrumbs items={breadcrumbs} />

            <div className="mb-8 space-y-2">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {topic.title}
                </h1>
                {topic.description && (
                    <p className="text-muted-foreground">{topic.description}</p>
                )}
            </div>

            {topic.content && (
                <div className="mb-8">
                    {/* An empty media map: a topic introduction cannot
                        contain embeds, because the server strips them
                        (App\Support\PageContent::sanitiseWithoutEmbeds). */}
                    <RichText doc={topic.content} media={{}} />
                </div>
            )}

            {!hasContent && !topic.content && (
                <p className="text-muted-foreground">
                    Dit onderdeel heeft nog geen inhoud.
                </p>
            )}

            {childTopics.length > 0 && (
                <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {childTopics.map((child) => (
                        <ContentSummaryCard
                            key={child.id}
                            item={child}
                            icon={child.icon ? icons[child.icon] : null}
                        />
                    ))}
                </div>
            )}

            {pages.length > 0 && (
                <ul className="divide-y divide-border rounded-lg border border-border">
                    {pages.map((page) => (
                        <li key={page.id}>
                            <Link
                                href={page.href}
                                className="flex items-center gap-3 p-4 hover:bg-accent/50"
                            >
                                <Icon
                                    icon={page.icon ? icons[page.icon] : null}
                                    className="size-5 shrink-0 text-muted-foreground"
                                />
                                <div>
                                    <div className="font-medium">
                                        {page.title}
                                    </div>
                                    {page.description && (
                                        <div className="text-sm text-muted-foreground">
                                            {page.description}
                                        </div>
                                    )}
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </PublicLayout>
    );
}
