import { router } from '@inertiajs/react';
import { History, RotateCcw } from 'lucide-react';
import * as React from 'react';
import { RichText } from '@/components/content/rich-text';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/components/ui/confirm-dialog';
import { intlLocale, t } from '@/lib/i18n';
import type { EditorMediaLibrary } from '@/components/editor/media-library';
import {
    restore as restoreRevision,
    show as showRevision,
} from '@/routes/admin/pages/revisions';
import type { PageMedia, TipTapDoc } from '@/types/tiptap';

/*
 * The last ten published bodies of a page, and the way back to one.
 *
 * A panel inside the editor rather than a screen of its own: this is
 * something the owner reaches for while looking at the body it is about to
 * replace, and a separate screen would mean leaving that behind. Collapsed by
 * default, because most sessions never need it — but the button is there even
 * on a page with no history yet, since a feature nobody can see is one nobody
 * knows they have.
 *
 * **A version can only be restored from its preview.** Restoring blind is how
 * you lose the thing you had: these entries are labelled with a timestamp and
 * nothing else, because a timestamp is genuinely all that distinguishes them,
 * and a date is not enough to recognise a lesson by. So there is no restore
 * button on the list — opening a version is what produces one.
 *
 * The preview is components/content/rich-text.tsx, the public renderer, and
 * that is deliberate too. It is the only thing that draws this document shape
 * safely (never dangerouslySetInnerHTML, never TipTap's generateHTML), and it
 * is what a student will actually see if the owner presses restore. A second
 * renderer here would be a second thing to keep in step with the whitelist,
 * and it would be showing them something other than the answer.
 *
 * Bodies are fetched one at a time. The edit screen sends only the
 * timestamps — see App\Http\Controllers\Admin\PageRevisionController for why
 * ten copies of a long lesson do not ride along in every page-edit payload.
 */

export type PageRevisionSummary = {
    id: number;
    savedAt: string | null;
};

type Props = {
    pageId: number;
    revisions: PageRevisionSummary[];
    /**
     * Whether the page is carrying an unpublished draft.
     *
     * Restoring goes through the same publish as "Save and publish", and a
     * publish ends the draft — so this changes what the confirmation says.
     * The draft exists nowhere else, and losing it silently while answering a
     * question about something else is the kind of thing this project does
     * not do.
     */
    hasDraft: boolean;
    /**
     * Put the restored body into the editor.
     *
     * Handed back rather than left to the page props, and that is not a
     * shortcut. `useEditor` reads its document once, when it is built, so a
     * new `content` prop changes nothing on its own — the editor would go on
     * showing the body that was just replaced while the site served the
     * restored one, which is precisely the disagreement this feature exists
     * to end. The document is already here, because it is the one being
     * previewed, so the swap needs no second fetch. Same handover as the
     * draft revert directly above it in page-editor.tsx.
     *
     * The library travels with it because the node views resolve an embed
     * against what the *current* body shows, and an older version is very
     * likely to hold a picture this one dropped. Without it a restored
     * gallery came out as "deze afbeeldingen bestaan niet meer" over an
     * intact block, inviting the owner to delete it — found in the browser,
     * because both halves are correct on the server.
     */
    onRestored: (
        document: TipTapDoc | null,
        library: EditorMediaLibrary,
    ) => void;
};

type Preview = {
    id: number;
    content: TipTapDoc | null;
    /** Keyed by ULID, for the public renderer to look an embed up in. */
    media: PageMedia;
    /** The same files, in the shape the editor's own node views read. */
    library: EditorMediaLibrary;
};

export function VersionHistory({
    pageId,
    revisions,
    hasDraft,
    onRestored,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const [preview, setPreview] = React.useState<Preview | null>(null);
    const [loadingId, setLoadingId] = React.useState<number | null>(null);
    const [failed, setFailed] = React.useState(false);
    const [restoringId, setRestoringId] = React.useState<number | null>(null);

    const load = async (id: number) => {
        setLoadingId(id);
        setFailed(false);

        try {
            const response = await fetch(
                showRevision({ page: pageId, revision: id }).url,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                setFailed(true);

                return;
            }

            const body = (await response.json()) as Omit<Preview, 'id'>;

            setPreview({ id, ...body });
        } catch {
            // An offline laptop is the ordinary case, not an exception. The
            // list stays; only the preview is missing, and it says so.
            setFailed(true);
        } finally {
            setLoadingId(null);
        }
    };

    const restore = async (revision: PageRevisionSummary, body: Preview) => {
        const moment = formatMoment(revision.savedAt);

        const answered = await confirm({
            title: t('ui.editor.history.restore_title'),
            description: hasDraft
                ? t('ui.editor.history.restore_description_with_draft', {
                      time: moment,
                  })
                : t('ui.editor.history.restore_description', { time: moment }),
            confirmLabel: t('ui.editor.history.restore_confirm'),
            // It replaces what the site is serving. That the replaced body is
            // itself kept makes this undoable, not harmless.
            destructive: true,
        });

        if (!answered) {
            return;
        }

        setRestoringId(revision.id);

        /*
         * State is preserved and the editor is handed the document instead.
         *
         * Not an optimisation: a remount would rebuild the TipTap editor, and
         * a rebuild is the only thing that makes it read `content` again — so
         * the alternative was relying on the page component being torn down,
         * which is a detail of the adapter rather than a promise to build on.
         * Preserving state and swapping the document explicitly is the same
         * handover the draft revert uses, and it keeps the panel open on a
         * list that now includes the entry this restore just created.
         *
         * The list itself is not patched in: props re-render either way, so
         * it comes from the server's own answer.
         */
        router.post(
            restoreRevision({ page: pageId, revision: revision.id }).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    onRestored(body.content, body.library);
                    // The version being previewed is the live body now, so
                    // the panel would be offering to restore what is already
                    // there.
                    setPreview(null);
                },
                onFinish: () => setRestoringId(null),
            },
        );
    };

    return (
        <div className="grid gap-3">
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-expanded={open}
                    onClick={() => setOpen((current) => !current)}
                >
                    <History aria-hidden="true" />
                    {open
                        ? t('ui.editor.history.hide')
                        : t('ui.editor.history.show')}
                </Button>
            </div>

            {open && (
                <section className="grid gap-4 rounded-lg border border-border bg-card p-4">
                    <div>
                        <h3 className="font-medium">
                            {t('ui.editor.history.title')}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {t('ui.editor.history.description')}
                        </p>
                    </div>

                    {revisions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('ui.editor.history.empty')}
                        </p>
                    ) : (
                        <ul className="grid gap-2">
                            {revisions.map((revision) => (
                                <li
                                    key={revision.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
                                >
                                    <span className="text-sm">
                                        {t('ui.editor.history.version', {
                                            time: formatMoment(
                                                revision.savedAt,
                                            ),
                                        })}
                                    </span>

                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        disabled={loadingId !== null}
                                        onClick={() => void load(revision.id)}
                                    >
                                        {loadingId === revision.id && (
                                            <Spinner aria-hidden="true" />
                                        )}
                                        {t('ui.editor.history.view')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {/* `error`, not `destructive`: destructive is the fill
                        colour and reads as near-white text on this card. */}
                    {failed && (
                        <p role="alert" className="text-sm text-error">
                            {t('ui.editor.history.failed')}
                        </p>
                    )}

                    {preview !== null && (
                        <RevisionPreview
                            preview={preview}
                            revision={revisions.find(
                                (entry) => entry.id === preview.id,
                            )}
                            isRestoring={restoringId === preview.id}
                            onRestore={restore}
                            onClose={() => setPreview(null)}
                        />
                    )}
                </section>
            )}
        </div>
    );
}

function RevisionPreview({
    preview,
    revision,
    isRestoring,
    onRestore,
    onClose,
}: {
    preview: Preview;
    /**
     * Absent only in the moment between a restore landing and the list being
     * re-rendered from the server's answer, when the previewed entry may no
     * longer be the tenth. Nothing to restore then, so nothing to draw.
     */
    revision: PageRevisionSummary | undefined;
    isRestoring: boolean;
    onRestore: (revision: PageRevisionSummary, body: Preview) => void;
    onClose: () => void;
}) {
    if (revision === undefined) {
        return null;
    }

    const isEmpty = (preview.content?.content?.length ?? 0) === 0;

    return (
        <div className="grid gap-3 rounded-lg border border-border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h4 className="font-medium">
                    {t('ui.editor.history.version', {
                        time: formatMoment(revision.savedAt),
                    })}
                </h4>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={onClose}
                    >
                        {t('ui.editor.history.close')}
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        disabled={isRestoring}
                        onClick={() => onRestore(revision, preview)}
                    >
                        {isRestoring ? (
                            <Spinner aria-hidden="true" />
                        ) : (
                            <RotateCcw aria-hidden="true" />
                        )}
                        {t('ui.editor.history.restore')}
                    </Button>
                </div>
            </div>

            {/* Capped and scrollable: a lesson is longer than a panel, and a
                preview that pushed the editor off the screen would be worse
                than no preview. */}
            <div className="max-h-96 overflow-y-auto">
                {isEmpty ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.history.preview_empty')}
                    </p>
                ) : (
                    <RichText doc={preview.content} media={preview.media} />
                )}
            </div>
        </div>
    );
}

/**
 * The label a version is recognised by.
 *
 * Formatted in the browser rather than on the server: the interface language
 * is the visitor's own choice, and the same page is served to both. Read at
 * render for the same reason a module-level constant would freeze the
 * language — see the note in components/admin/media-uploader.tsx.
 */
function formatMoment(savedAt: string | null): string {
    if (savedAt === null) {
        return '';
    }

    return new Date(savedAt).toLocaleString(intlLocale, {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
}
