import { Head } from '@inertiajs/react';
import TopicController from '@/actions/App/Http/Controllers/Admin/TopicController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import { TopicForm } from '@/components/admin/topic-form';
import type { PossibleParent } from '@/components/admin/topic-form';
import { index as topicsIndex } from '@/routes/admin/topics';

type Props = {
    possibleParents: PossibleParent[];
    passwords: AccessPasswordOption[];
};

function initialParentIdFromQuery(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const raw = new URLSearchParams(window.location.search).get('parent_id');
    const parsed = raw ? Number(raw) : NaN;

    return Number.isFinite(parsed) ? parsed : null;
}

export default function TopicsCreate({ possibleParents, passwords }: Props) {
    return (
        <>
            <Head title="Nieuw onderwerp" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    Nieuw onderwerp
                </h1>

                <TopicForm
                    formProps={TopicController.store.form()}
                    possibleParents={possibleParents}
                    passwords={passwords}
                    initialParentId={initialParentIdFromQuery()}
                />
            </div>
        </>
    );
}

TopicsCreate.layout = {
    breadcrumbs: [
        { title: 'Inhoud', href: topicsIndex.url() },
        { title: 'Nieuw onderwerp', href: TopicController.create.url() },
    ],
};
