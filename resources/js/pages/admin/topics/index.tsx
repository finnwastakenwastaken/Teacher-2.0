import { Head, Link, router } from '@inertiajs/react';
import { FileClock } from 'lucide-react';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import TopicController from '@/actions/App/Http/Controllers/Admin/TopicController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm } from '@/components/ui/confirm-dialog';
import { SortableList, SortableRow } from '@/components/admin/sortable-list';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { t } from '@/lib/i18n';

/*
 * CRUD listing of the whole content tree, with drag-to-reorder.
 *
 * Each set of siblings is its own sortable list — the root topics, each
 * topic's subtopics, and each topic's pages. Dragging never moves an item
 * between parents; that still goes through the edit form. See
 * App\Support\SortOrder for why.
 */

type PageSummary = {
    id: number;
    title: string;
    slug: string;
    is_hidden: boolean;
    /**
     * An unpublished concept is waiting on this page.
     *
     * Derived on the server from `draft_saved_at`, never from the document —
     * a page the owner emptied and has not published yet has a concept and a
     * null body, and reading the body would call that no concept at all.
     */
    has_draft: boolean;
};

type TopicNode = {
    id: number;
    title: string;
    slug: string;
    icon: string | null;
    is_hidden: boolean;
    depth: number;
    children: TopicNode[];
    pages: PageSummary[];
};

type Props = {
    tree: TopicNode[];
    icons: Record<string, IconData>;
};

/*
 * These two stay module-level functions, which is why the confirmation is a
 * module-level `confirm()` rather than a hook — see components/ui/confirm-dialog.
 * The `await` is not decoration: the native confirm() these replaced blocked
 * the thread, and a version that carried on before the answer arrived would
 * delete without asking while still looking correct on screen.
 */
async function deleteTopic(topic: TopicNode) {
    const confirmed = await confirm({
        title: t('ui.content.confirm_delete_title'),
        description: t('ui.content.confirm_delete', { title: topic.title }),
        confirmLabel: t('ui.actions.delete'),
        destructive: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(TopicController.destroy(topic.id).url, {
        preserveScroll: true,
    });
}

async function deletePage(page: PageSummary) {
    const confirmed = await confirm({
        title: t('ui.content.confirm_delete_title'),
        description: t('ui.content.confirm_delete', { title: page.title }),
        confirmLabel: t('ui.actions.delete'),
        destructive: true,
    });

    if (!confirmed) {
        return;
    }

    router.delete(PageController.destroy(page.id).url, {
        preserveScroll: true,
    });
}

/*
 * Duplicating lands on the copy's edit screen, so no preserveScroll here —
 * the whole point is to arrive somewhere else and start editing.
 */
function duplicatePage(page: PageSummary) {
    router.post(PageController.duplicate(page.id).url);
}

/*
 * Persisting a reorder is a plain post. There is deliberately no optimistic
 * local copy of the tree: the server is the only thing that knows the real
 * order, and Inertia re-renders the list from its response.
 */
function reorderTopics(ids: number[]) {
    router.post(
        TopicController.reorder().url,
        { ids },
        { preserveScroll: true },
    );
}

function reorderPages(ids: number[]) {
    router.post(
        PageController.reorder().url,
        { ids },
        { preserveScroll: true },
    );
}

function TopicRow({
    topic,
    icons,
}: {
    topic: TopicNode;
    icons: Record<string, IconData>;
}) {
    return (
        <div
            className="border-l-2 border-border py-2"
            style={{ marginLeft: topic.depth > 0 ? '1.5rem' : 0 }}
        >
            <div className="flex flex-wrap items-center gap-2 pl-3">
                <Icon
                    icon={icons[topic.icon ?? '']}
                    className="size-4 text-muted-foreground"
                />
                <span className="font-medium">{topic.title}</span>
                {topic.is_hidden && (
                    <Badge variant="secondary">{t('ui.content.hidden')}</Badge>
                )}

                <div className="ml-auto flex flex-wrap gap-2">
                    {topic.depth < 2 && (
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={TopicController.create.url({
                                    query: { parent_id: topic.id },
                                })}
                            >
                                {t('ui.content.topic.add_child')}
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={PageController.create.url({
                                query: { topic_id: topic.id },
                            })}
                        >
                            {t('ui.content.page.add')}
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={TopicController.edit(topic.id).url}>
                            {t('ui.actions.edit')}
                        </Link>
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteTopic(topic)}
                    >
                        {t('ui.actions.delete')}
                    </Button>
                </div>
            </div>

            {topic.pages.length > 0 && (
                <div className="mt-2 space-y-1 pl-3">
                    <SortableList
                        items={topic.pages}
                        getId={(page) => page.id}
                        getTitle={(page) => page.title}
                        onReorder={reorderPages}
                        label={t('ui.content.page.under_topic', {
                            title: topic.title,
                        })}
                    >
                        {(page) => (
                            <SortableRow
                                key={page.id}
                                id={page.id}
                                title={page.title}
                                className="flex-wrap items-center gap-2 pl-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <span>{page.title}</span>
                                    {page.is_hidden && (
                                        <Badge variant="secondary">
                                            {t('ui.content.hidden')}
                                        </Badge>
                                    )}
                                    {/* Beside the hidden badge, and not a
                                        variant of it: a page can be published
                                        and still be carrying writing nobody
                                        has seen. Deliberately not styled as
                                        an error — a concept is work in
                                        progress. */}
                                    {page.has_draft && (
                                        <Badge variant="warning">
                                            <FileClock aria-hidden="true" />
                                            {t('ui.content.has_draft')}
                                        </Badge>
                                    )}

                                    <div className="ml-auto flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    PageController.edit(page.id)
                                                        .url
                                                }
                                            >
                                                {t('ui.actions.edit')}
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => duplicatePage(page)}
                                        >
                                            {t('ui.content.duplicate')}
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => deletePage(page)}
                                        >
                                            {t('ui.actions.delete')}
                                        </Button>
                                    </div>
                                </div>
                            </SortableRow>
                        )}
                    </SortableList>
                </div>
            )}

            {topic.children.length > 0 && (
                <div className="mt-2">
                    <SortableList
                        items={topic.children}
                        getId={(child) => child.id}
                        getTitle={(child) => child.title}
                        onReorder={reorderTopics}
                        label={t('ui.content.topic.children_of', {
                            title: topic.title,
                        })}
                    >
                        {(child) => (
                            <SortableRow
                                key={child.id}
                                id={child.id}
                                title={child.title}
                            >
                                <TopicRow topic={child} icons={icons} />
                            </SortableRow>
                        )}
                    </SortableList>
                </div>
            )}
        </div>
    );
}

export default function TopicsIndex({ tree, icons }: Props) {
    useStatusToasts();

    return (
        <>
            <Head title={t('ui.content.title')} />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold tracking-tight">
                        {t('ui.content.title')}
                    </h1>
                    <Button asChild>
                        <Link href={TopicController.create.url()}>
                            {t('ui.content.topic.add_top_level')}
                        </Link>
                    </Button>
                </div>

                {tree.length === 0 ? (
                    <p className="text-muted-foreground">
                        {t('ui.content.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border border-border p-2">
                        <SortableList
                            items={tree}
                            getId={(topic) => topic.id}
                            getTitle={(topic) => topic.title}
                            onReorder={reorderTopics}
                            label={t('ui.content.top_level')}
                        >
                            {(topic) => (
                                <SortableRow
                                    key={topic.id}
                                    id={topic.id}
                                    title={topic.title}
                                >
                                    <TopicRow topic={topic} icons={icons} />
                                </SortableRow>
                            )}
                        </SortableList>
                    </div>
                )}
            </div>
        </>
    );
}

TopicsIndex.layout = {
    breadcrumbs: [
        {
            title: t('ui.content.title'),
            href: TopicController.index.url(),
        },
    ],
};
