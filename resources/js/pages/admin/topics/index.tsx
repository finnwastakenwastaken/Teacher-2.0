import { Head, Link, router } from '@inertiajs/react';
import { Icon } from '@/components/icon';
import type { IconData } from '@/components/icon';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import TopicController from '@/actions/App/Http/Controllers/Admin/TopicController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SortableList, SortableRow } from '@/components/admin/sortable-list';
import { useStatusToasts } from '@/hooks/use-status-toasts';

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
    topic_id: number;
    title: string;
    slug: string;
    is_hidden: boolean;
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

function deleteTopic(topic: TopicNode) {
    if (
        !confirm(
            `Weet je zeker dat je "${topic.title}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`,
        )
    ) {
        return;
    }

    router.delete(TopicController.destroy(topic.id).url, {
        preserveScroll: true,
    });
}

function deletePage(page: PageSummary) {
    if (
        !confirm(
            `Weet je zeker dat je "${page.title}" wilt verwijderen? Dit kan niet ongedaan worden gemaakt.`,
        )
    ) {
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
                    <Badge variant="secondary">Verborgen</Badge>
                )}

                <div className="ml-auto flex flex-wrap gap-2">
                    {topic.depth < 2 && (
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={TopicController.create.url({
                                    query: { parent_id: topic.id },
                                })}
                            >
                                + Subonderwerp
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={PageController.create.url({
                                query: { topic_id: topic.id },
                            })}
                        >
                            + Pagina
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={TopicController.edit(topic.id).url}>
                            Bewerken
                        </Link>
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteTopic(topic)}
                    >
                        Verwijderen
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
                        label={`Pagina’s onder ${topic.title}`}
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
                                            Verborgen
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
                                                Bewerken
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => duplicatePage(page)}
                                        >
                                            Dupliceren
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => deletePage(page)}
                                        >
                                            Verwijderen
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
                        label={`Subonderwerpen van ${topic.title}`}
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
            <Head title="Inhoud" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Inhoud
                    </h1>
                    <Button asChild>
                        <Link href={TopicController.create.url()}>
                            + Nieuw hoofdonderwerp
                        </Link>
                    </Button>
                </div>

                {tree.length === 0 ? (
                    <p className="text-muted-foreground">
                        Er zijn nog geen onderwerpen aangemaakt.
                    </p>
                ) : (
                    <div className="rounded-lg border border-border p-2">
                        <SortableList
                            items={tree}
                            getId={(topic) => topic.id}
                            getTitle={(topic) => topic.title}
                            onReorder={reorderTopics}
                            label="Hoofdonderwerpen"
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
            title: 'Inhoud',
            href: TopicController.index.url(),
        },
    ],
};
