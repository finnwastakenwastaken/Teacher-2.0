import { Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import type { AccessPasswordOption } from '@/components/admin/access-password-field';
import type { ImageOption } from '@/components/admin/image-field';
import { PageDownloads } from '@/components/admin/page-downloads';
import type {
    EducationLevelOption,
    LibraryFile,
    PageDownload,
} from '@/components/admin/page-downloads';
import { PageForm } from '@/components/admin/page-form';
import type { IconData } from '@/components/icon';
import type { TopicOption } from '@/components/admin/page-form';
import { PageEditor } from '@/components/editor/page-editor';
import type { EditorMediaLibrary } from '@/components/editor/media-library';
import { useStatusToasts } from '@/hooks/use-status-toasts';
import { index as topicsIndex } from '@/routes/admin/topics';
import type { TipTapDoc } from '@/types/tiptap';

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
    topics: TopicOption[];
    mediaLibrary: EditorMediaLibrary;
    passwords: AccessPasswordOption[];
    images: ImageOption[];
    educationLevels: EducationLevelOption[];
    downloads: PageDownload[];
    downloadFiles: (LibraryFile & { id: number })[];
    uploadMaxBytes: number;
};

export default function PagesEdit({
    iconData,
    page,
    topics,
    mediaLibrary,
    passwords,
    images,
    educationLevels,
    downloads,
    downloadFiles,
    uploadMaxBytes,
}: Props) {
    useStatusToasts();

    return (
        <>
            <Head title={`"${page.title}" bewerken`} />

            <div className="flex flex-1 flex-col gap-8 p-4">
                <h1 className="text-xl font-semibold tracking-tight">
                    &quot;{page.title}&quot; bewerken
                </h1>

                <PageForm
                    iconData={iconData}
                    formProps={PageController.update.form(page.id)}
                    page={page}
                    topics={topics}
                    passwords={passwords}
                    images={images}
                />

                {/* The body is saved separately from the settings above: the
                    settings form is submitted deliberately, the editor saves a
                    document. Two forms, two buttons, two endpoints. */}
                <section className="grid gap-3 border-t border-border pt-8">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Inhoud
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            De tekst, afbeeldingen, bestanden en video&apos;s op
                            deze pagina. Vergeet niet op &quot;Inhoud
                            opslaan&quot; te klikken.
                        </p>
                    </div>

                    <PageEditor
                        pageId={page.id}
                        content={page.content}
                        mediaLibrary={mediaLibrary}
                        maxBytes={uploadMaxBytes}
                    />
                </section>

                {/* Relational, not part of the body: each download is saved
                    on its own, and the level tags live on the attachment so
                    the same file can be labelled differently elsewhere. */}
                <section className="grid gap-3 border-t border-border pt-8">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">
                            Downloads
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Bestanden onderaan de pagina, gegroepeerd per
                            niveau. Elke wijziging wordt meteen opgeslagen.
                        </p>
                    </div>

                    <PageDownloads
                        pageId={page.id}
                        downloads={downloads}
                        levels={educationLevels}
                        files={downloadFiles}
                        maxBytes={uploadMaxBytes}
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
        { title: 'Inhoud', href: topicsIndex.url() },
        { title: 'Pagina bewerken', href: '#' },
    ],
};
