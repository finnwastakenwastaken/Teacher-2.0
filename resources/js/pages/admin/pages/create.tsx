import { Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import type { ImageOption } from '@/components/admin/image-field';
import { PageForm } from '@/components/admin/page-form';
import type { TopicOption } from '@/components/admin/page-form';
import { index as topicsIndex } from '@/routes/admin/topics';
import { t } from '@/lib/i18n';

type Props = {
    topics: TopicOption[];
    passwords: AccessPasswordOption[];
    /** The image the banner field currently points at, if any. */
    heroImage: ImageOption | null;
};

function initialTopicIdFromQuery(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const raw = new URLSearchParams(window.location.search).get('topic_id');
    const parsed = raw ? Number(raw) : NaN;

    return Number.isFinite(parsed) ? parsed : null;
}

export default function PagesCreate({ topics, passwords, heroImage }: Props) {
    return (
        <>
            <Head title={t('ui.content.page.new')} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('ui.content.page.new')}
                </h1>

                <PageForm
                    formProps={PageController.store.form()}
                    topics={topics}
                    passwords={passwords}
                    heroImage={heroImage}
                    initialTopicId={initialTopicIdFromQuery()}
                />
            </div>
        </>
    );
}

PagesCreate.layout = {
    breadcrumbs: [
        { title: t('ui.content.title'), href: topicsIndex.url() },
        { title: t('ui.content.page.new'), href: PageController.create.url() },
    ],
};
