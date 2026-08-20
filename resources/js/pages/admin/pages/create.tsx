import { Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import type { ImageOption } from '@/components/admin/image-field';
import { PageForm } from '@/components/admin/page-form';
import type { TopicOption } from '@/components/admin/page-form';
import { index as topicsIndex } from '@/routes/admin/topics';

type Props = {
    topics: TopicOption[];
    passwords: AccessPasswordOption[];
    images: ImageOption[];
};

function initialTopicIdFromQuery(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const raw = new URLSearchParams(window.location.search).get('topic_id');
    const parsed = raw ? Number(raw) : NaN;

    return Number.isFinite(parsed) ? parsed : null;
}

export default function PagesCreate({ topics, passwords, images }: Props) {
    return (
        <>
            <Head title="Nieuwe pagina" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    Nieuwe pagina
                </h1>

                <PageForm
                    formProps={PageController.store.form()}
                    topics={topics}
                    passwords={passwords}
                    images={images}
                    initialTopicId={initialTopicIdFromQuery()}
                />
            </div>
        </>
    );
}

PagesCreate.layout = {
    breadcrumbs: [
        { title: 'Inhoud', href: topicsIndex.url() },
        { title: 'Nieuwe pagina', href: PageController.create.url() },
    ],
};
