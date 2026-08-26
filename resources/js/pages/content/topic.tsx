import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { RichText } from '@/components/content/rich-text';
import { ContentSummaryCard } from '@/components/content-summary-card';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import type { ContentSummary } from '@/components/content-summary-card';
import { PublicBreadcrumbs } from '@/components/public-breadcrumbs';
import PublicLayout from '@/layouts/public-layout';
import { t } from '@/lib/i18n';
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
                <h1 className="text-3xl font-semibold tracking-tight text-balance">
                    {topic.title}
                </h1>
                {topic.description && (
                    <p className="text-lg text-pretty text-muted-foreground">
                        {topic.description}
                    </p>
                )}
            </div>

            {topic.content && (
                // The introduction is prose and gets the same reading measure
                // as a page body. The grids below stay at container width —
                // they are scanned, not read.
                <div className="mb-8 max-w-[35rem] text-[1.0625rem]">
                    {/* An empty media map: a topic introduction cannot
                        contain embeds, because the server strips them
                        (App\Support\PageContent::sanitiseWithoutEmbeds). */}
                    <RichText doc={topic.content} media={{}} />
                </div>
            )}

            {!hasContent && !topic.content && (
                <p className="text-muted-foreground">
                    {t('ui.public.topic.empty')}
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
                <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                    {pages.map((page) => (
                        <li key={page.id}>
                            {/* Same vocabulary as ContentSummaryCard — tinted
                                icon square, clamped description, a chevron
                                that moves — so a subtopic and a page read as
                                the same kind of destination. The focus ring is
                                on the link and is not optional: these rows
                                were hoverable and invisibly focusable. */}
                            <Link
                                href={page.href}
                                className="group flex items-center gap-3 p-4 transition-colors hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <span
                                    aria-hidden="true"
                                    className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                                >
                                    <Icon
                                        icon={
                                            page.icon ? icons[page.icon] : null
                                        }
                                        className="size-5"
                                    />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="font-medium">
                                        {page.title}
                                    </div>
                                    {page.description && (
                                        <div className="line-clamp-2 text-sm text-muted-foreground">
                                            {page.description}
                                        </div>
                                    )}
                                </div>
                                <ChevronRight
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </PublicLayout>
    );
}
