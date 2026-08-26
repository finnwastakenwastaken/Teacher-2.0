import { Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import type { ImageOption } from '@/components/admin/image-field';
import { PageDownloads } from '@/components/admin/page-downloads';
import type {
    EducationLevelOption,
    PageDownload,
} from '@/components/admin/page-downloads';
import { PageForm } from '@/components/admin/page-form';
import type { IconData } from '@/components/icon';
import type { TopicOption } from '@/components/admin/page-form';
import { PageEditor } from '@/components/editor/page-editor';
import type { PageDraft } from '@/components/editor/page-editor';
import type { PageRevisionSummary } from '@/components/editor/version-history';
import type { EditorMediaLibrary } from '@/components/editor/media-library';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { index as topicsIndex } from '@/routes/admin/topics';
import type { AcceptedFormats } from '@/types';
import type { TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

type Page = {
    id: number;
    title: string;
    slug: string;
    topic_id: number;
    icon: string | null;
    description: string | null;
    sort_order: number | null;
    is_hidden: boolean;
    access_password_id: number | null;
    hero_image_id: number | null;
    content: TipTapDoc | null;
};

type Props = {
    iconData: IconData | null;
    page: Page;
    /**
     * The unpublished concept, if this page has one. Separate from
     * `page.content` on purpose: the editor opens on the concept so the owner
     * carries on from where they stopped, and DraftNotice is what keeps that
     * honest — both halves have to be here for the screen to say which is
     * which.
     */
    draft: PageDraft | null;
    /**
     * The last ten published bodies, newest first — timestamps only. The
     * bodies themselves are fetched when one is opened, so a long lesson is
     * not sent eleven times to draw a list of dates.
     */
    revisions: PageRevisionSummary[];
    topics: TopicOption[];
    mediaLibrary: EditorMediaLibrary;
    passwords: AccessPasswordOption[];
    /** The image the banner field currently points at, if any. */
    heroImage: ImageOption | null;
    educationLevels: EducationLevelOption[];
    downloads: PageDownload[];
    attachableMediaAvailable: boolean;
    uploadMaxBytes: number;
    acceptedFormats: AcceptedFormats;
};

export default function PagesEdit({
    iconData,
    page,
    draft,
    revisions,
    topics,
    mediaLibrary,
    passwords,
    heroImage,
    educationLevels,
    downloads,
    attachableMediaAvailable,
    uploadMaxBytes,
    acceptedFormats,
}: Props) {
    useStatusToasts();

    return (
        <>
            <Head title={t('ui.content.edit_title', { title: page.title })} />

            <div className="flex flex-1 flex-col gap-8 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('ui.content.edit_title', { title: page.title })}
                </h1>

                <PageForm
                    iconData={iconData}
                    formProps={PageController.update.form(page.id)}
                    page={page}
                    topics={topics}
                    passwords={passwords}
                    heroImage={heroImage}
                />

                {/* The body is saved separately from the settings above: the
                    settings form is submitted deliberately, the editor saves a
                    document. Two forms, two buttons, two endpoints. */}
                <section className="grid gap-3 border-t border-border pt-8">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            {t('ui.content.page.body_heading')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('ui.content.page.body_description')}
                        </p>
                    </div>

                    <PageEditor
                        pageId={page.id}
                        content={page.content}
                        draft={draft}
                        revisions={revisions}
                        mediaLibrary={mediaLibrary}
                        maxBytes={uploadMaxBytes}
                        acceptedFormats={acceptedFormats}
                    />
                </section>

                {/* Relational, not part of the body: each download is saved
                    on its own, and the level tags live on the attachment so
                    the same file can be labelled differently elsewhere. */}
                <section className="grid gap-3 border-t border-border pt-8">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            {t('ui.content.page.downloads_heading')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {t('ui.content.page.downloads_description')}
                        </p>
                    </div>

                    <PageDownloads
                        pageId={page.id}
                        downloads={downloads}
                        levels={educationLevels}
                        attachableMediaAvailable={attachableMediaAvailable}
                        maxBytes={uploadMaxBytes}
                        acceptedFormats={acceptedFormats}
                    />
                </section>
            </div>
        </>
    );
}

// The last breadcrumb entry always renders as plain text (BreadcrumbPage),
// never as a link — see components/breadcrumbs.tsx — so its href is unused.
PagesEdit.layout = {
    breadcrumbs: [
        { title: t('ui.content.title'), href: topicsIndex.url() },
        { title: t('ui.content.page.edit'), href: '#' },
    ],
};
