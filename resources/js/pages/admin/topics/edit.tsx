import { Head } from '@inertiajs/react';
import TopicController from '@/actions/App/Http/Controllers/Admin/TopicController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import { TopicForm } from '@/components/admin/topic-form';
import type { IconData } from '@/components/icon';
import type { PossibleParent } from '@/components/admin/topic-form';
import { index as topicsIndex } from '@/routes/admin/topics';
import type { TipTapDoc } from '@/types/tiptap';

type Topic = {
    id: number;
    title: string;
    slug: string;
    parent_id: number | null;
    icon: string | null;
    description: string | null;
    content: TipTapDoc | null;
    sort_order: number | null;
    is_hidden: boolean;
    access_password_id: number | null;
};

type Props = {
    iconData: IconData | null;
    topic: Topic;
    possibleParents: PossibleParent[];
    passwords: AccessPasswordOption[];
};

export default function TopicsEdit({
    topic,
    iconData,
    possibleParents,
    passwords,
}: Props) {
    return (
        <>
            <Head title={`"${topic.title}" bewerken`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    &quot;{topic.title}&quot; bewerken
                </h1>

                <TopicForm
                    iconData={iconData}
                    formProps={TopicController.update.form(topic.id)}
                    topic={topic}
                    possibleParents={possibleParents}
                    passwords={passwords}
                />
            </div>
        </>
    );
}

// The last breadcrumb entry always renders as plain text (BreadcrumbPage),
// never as a link — see components/breadcrumbs.tsx — so its href is unused.
TopicsEdit.layout = {
    breadcrumbs: [
        { title: 'Inhoud', href: topicsIndex.url() },
        { title: 'Onderwerp bewerken', href: '#' },
    ],
};
