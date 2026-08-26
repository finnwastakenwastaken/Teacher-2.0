import { router } from '@inertiajs/react';
import Link from '@tiptap/extension-link';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Columns3,
    FileClock,
    Heading2,
    Heading3,
    Images,
    Italic,
    Instagram,
    Link2,
    List,
    ListOrdered,
    Paperclip,
    Quote,
    Music2,
    Rows3,
    Save,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Table as TableIcon,
    Trash2,
    WrapText,
    Youtube,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { FileEmbed } from '@/components/editor/extensions/file-embed';
import { ImageAside } from '@/components/editor/extensions/image-aside';
import { ImageGallery } from '@/components/editor/extensions/image-gallery';
import { SocialEmbed } from '@/components/editor/extensions/social-embed';
import { YouTubeEmbed } from '@/components/editor/extensions/youtube-embed';
import { DraftNotice } from '@/components/editor/draft-notice';
import { VersionHistory } from '@/components/editor/version-history';
import type { PageRevisionSummary } from '@/components/editor/version-history';
import { FilePickerDialog } from '@/components/editor/file-picker-dialog';
import { ImagePickerDialog } from '@/components/editor/image-picker-dialog';
import { LinkDialog } from '@/components/editor/link-dialog';
import { SocialDialog } from '@/components/editor/social-dialog';
import { YouTubeDialog } from '@/components/editor/youtube-dialog';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/components/ui/confirm-dialog';
import { useDraftAutosave } from '@/hooks/use-draft-autosave';
import type { DraftStatus } from '@/hooks/use-draft-autosave';
import { normaliseHref } from '@/lib/href';
import { intlLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { update as updateContent } from '@/routes/admin/pages/content';
import { destroy as destroyDraft } from '@/routes/admin/pages/draft';
import { GrowingEditorLibrary } from '@/components/editor/media-library';
import type { EditorMediaLibrary } from '@/components/editor/media-library';
import type { AcceptedFormats } from '@/types';
import type { TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

/*
 * The extensions below are configured to match the server whitelist in
 * App\Support\PageContent; a node/mark added here must be added there too or
 * it silently stops saving (also update the case in
 * components/content/rich-text.tsx).
 *
 * StarterKit has code, code blocks, strikethrough, underline and horizontal
 * rules switched off, and headings limited to the three levels below the
 * page title, so the editor cannot create a node the server would drop.
 *
 * There are two saves, and the difference between them is the point.
 * "Concept opslaan" — and the autosave behind it — writes the body to
 * `pages.draft_content`, which publishes nothing. "Opslaan en publiceren"
 * promotes that concept through Page::writeContent(), which is where
 * `page_media_references` (what makes an embedded file fetchable by an
 * anonymous visitor) and `content_text` (the search vector) are rebuilt. An
 * autosave that went through writeContent() would publish every image in a
 * half-written body and put an unfinished page in the public search box.
 */

const PROSE_CLASSES = [
    '[&_p]:my-3',
    '[&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold',
    '[&_h3]:mt-5 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold',
    '[&_h4]:mt-4 [&_h4]:mb-2 [&_h4]:text-base [&_h4]:font-semibold',
    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6',
    '[&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6',
    '[&_li]:my-1',
    '[&_blockquote]:my-4 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground',
    '[&_a]:text-link [&_a]:underline [&_a]:underline-offset-4',
    // table-fixed keeps the resize handles aligned with the column borders.
    '[&_table]:my-4 [&_table]:w-full [&_table]:table-fixed [&_table]:border-collapse',
    '[&_th]:border [&_th]:border-border [&_th]:bg-card [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:align-top [&_th]:font-semibold',
    '[&_td]:border [&_td]:border-border [&_td]:px-3 [&_td]:py-2 [&_td]:align-top',
    '[&_th>p]:my-0 [&_td>p]:my-0',
    // The cell the caret is in, and any cells dragged across.
    '[&_.selectedCell]:bg-accent',
    // The resize handle ProseMirror draws between columns.
    '[&_.column-resize-handle]:absolute [&_.column-resize-handle]:top-0 [&_.column-resize-handle]:bottom-0 [&_.column-resize-handle]:-right-0.5 [&_.column-resize-handle]:w-1 [&_.column-resize-handle]:bg-primary',
    '[&_.tableWrapper]:overflow-x-auto',
    '[&.resize-cursor]:cursor-col-resize',
    // These clear a floated imageAside (see rich-text.tsx); paragraphs/lists
    // are deliberately absent since wrapping around it is the feature.
    '[&_h2]:clear-both [&_h3]:clear-both [&_h4]:clear-both',
    '[&_blockquote]:clear-both [&_table]:clear-both [&_.tableWrapper]:clear-both',
].join(' ');

/**
 * What to hand TipTap for a stored document that may be empty or absent.
 *
 * An empty stored document has no blocks and ProseMirror's schema refuses it;
 * the empty string gives it the one paragraph it wants. Shared by the initial
 * load and by reverting, because the two must agree — a guard applied in one
 * place and forgotten in the other throws only for a page nobody has written
 * yet, which is exactly the page nobody tests with.
 */
function initialDocument(document: TipTapDoc | null): TipTapDoc | string {
    return (document?.content?.length ?? 0) > 0 ? (document as TipTapDoc) : '';
}

export type PageDraft = {
    content: TipTapDoc | null;
    savedAt: string | null;
};

type PageEditorProps = {
    pageId: number;
    content: TipTapDoc | null;
    /** An unpublished concept, if this page has one. */
    draft: PageDraft | null;
    /**
     * When each previously published body was replaced, newest first. Just
     * the timestamps — the bodies are fetched one at a time, when the owner
     * opens one. See components/editor/version-history.tsx.
     */
    revisions: PageRevisionSummary[];
    mediaLibrary: EditorMediaLibrary;
    maxBytes: number;
    acceptedFormats: AcceptedFormats;
};

export function PageEditor({
    pageId,
    content,
    draft,
    revisions,
    mediaLibrary,
    maxBytes,
    acceptedFormats,
}: PageEditorProps) {
    const [isDirty, setIsDirty] = React.useState(false);
    const [isSaving, setIsSaving] = React.useState(false);
    const [isReverting, setIsReverting] = React.useState(false);
    const [dialog, setDialog] = React.useState<
        | 'file'
        | 'image'
        | 'imageAside'
        | 'youtube'
        | 'tiktok'
        | 'instagram'
        | 'link'
        | null
    >(null);

    /*
     * Bumped on every document change; the autosave debounces on it. A
     * counter, not the document: the hook needs to know *that* something
     * changed and then went quiet, and diffing two ProseMirror documents on
     * each keystroke would cost more than the save it is deciding about.
     */
    const [revision, setRevision] = React.useState(0);

    /*
     * When the editor is holding an unpublished concept rather than what the
     * site is serving, this is when that concept was last written.
     *
     * The editor **opens on the concept**, so the owner carries on from where
     * they stopped and their most recent writing is never the thing at risk —
     * which is why autosave is not suspended: there is no second version on
     * screen that could overwrite a first. What it costs is that the editor
     * shows something the public page does not, so it may never be silent
     * about it: DraftNotice says so for as long as this is set, and only
     * publishing or reverting clears it.
     */
    const [editingDraft, setEditingDraft] = React.useState(
        draft?.savedAt ?? null,
    );

    /*
     * The library the node views resolve an embed's geometry from. `useEditor`
     * builds the extension list once, so this object (GrowingEditorLibrary)
     * lives for the editor's whole life and is mutated in place, never
     * replaced, by uploads and by picking an existing file or image out of
     * the picker dialogs' own search results — replacing it would tear down
     * the editor and lose the caret/undo history.
     *
     * It starts holding only what the body already embeds (see
     * PageController::embeddedMedia); the picker dialogs ask the server for
     * anything else themselves. Nothing here needs a React re-render to take
     * effect — inserting a node is itself a transaction, and by the time it
     * happens the holder already has whatever that node is about to embed.
     */
    const [holder] = React.useState(
        () => new GrowingEditorLibrary(mediaLibrary),
    );
    const [uploadedCount, setUploadedCount] = React.useState(0);

    const addToLibrary = React.useCallback(
        (record: UploadedRecord) => {
            if (record.type === 'image') {
                holder.addImage({
                    ulid: record.ulid,
                    alt_text: record.alt_text,
                    original_filename: record.original_filename,
                    url: record.url,
                });

                return;
            }

            holder.addFile({
                id: record.id,
                ulid: record.ulid,
                kind: record.kind,
                mime: record.mime,
                size_bytes: record.size_bytes,
                original_filename: record.original_filename,
                url: record.url,
            });
        },
        [holder],
    );

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                // Everything below is absent from the server whitelist, so
                // allowing it here would only produce content that silently
                // disappears on save.
                code: false,
                codeBlock: false,
                strike: false,
                underline: false,
                horizontalRule: false,
                // Configured separately, to keep the scheme restriction in
                // one obvious place.
                link: false,
                heading: { levels: [2, 3, 4] },
            }),
            Link.configure({
                openOnClick: false,
                autolink: false,
                defaultProtocol: 'https',
                isAllowedUri: (url) => normaliseHref(url) !== null,
            }),
            // H₂O and m/s² are unwritable without these.
            Subscript,
            Superscript,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
                // null (not 'left') so an unaligned paragraph carries no
                // attribute and existing documents don't all grow one on save.
                defaultAlignment: null,
            }),
            TableKit.configure({
                table: { resizable: true },
            }),
            // These get the holder object, not the mediaLibrary prop — see
            // the comment on `holder` above.
            FileEmbed.configure({ library: holder }),
            ImageGallery.configure({ library: holder }),
            ImageAside.configure({ library: holder }),
            YouTubeEmbed,
            SocialEmbed,
        ],
        /*
         * The concept if there is one, otherwise what is published.
         *
         * This is the whole of "open on the concept": a document already sent
         * with the page, so there is no round trip and no moment where the
         * editor shows one version and then swaps to another.
         *
         * The empty-string guard is not cosmetic — an empty stored document
         * has no blocks and ProseMirror's schema refuses it.
         */
        content: initialDocument(draft?.content ?? content),
        editorProps: {
            attributes: {
                // `flow-root` contains a floated imageAside inside the
                // document rather than letting it run out over the toolbar
                // below. The editable already has padding, so establishing a
                // block formatting context here costs nothing.
                class: cn(
                    'flow-root min-h-64 p-4 focus:outline-none',
                    PROSE_CLASSES,
                ),
                // A contenteditable is not a form control, so nothing else
                // gives this a name in the accessibility tree.
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': t('ui.editor.aria_label'),
            },
        },
        onUpdate: () => {
            setIsDirty(true);
            setRevision((count) => count + 1);
        },
    });

    const autosave = useDraftAutosave({
        pageId,
        revision,
        getDocument: () => editor.getJSON() as TipTapDoc,
        isPublishing: isSaving,
    });

    const state = useEditorState({
        editor,
        selector: ({ editor: instance }) => ({
            isEmpty: instance.isEmpty,
            bold: instance.isActive('bold'),
            italic: instance.isActive('italic'),
            subscript: instance.isActive('subscript'),
            superscript: instance.isActive('superscript'),
            alignLeft: instance.isActive({ textAlign: 'left' }),
            alignCenter: instance.isActive({ textAlign: 'center' }),
            alignRight: instance.isActive({ textAlign: 'right' }),
            alignJustify: instance.isActive({ textAlign: 'justify' }),
            inTable: instance.isActive('table'),
            heading2: instance.isActive('heading', { level: 2 }),
            heading3: instance.isActive('heading', { level: 3 }),
            bulletList: instance.isActive('bulletList'),
            orderedList: instance.isActive('orderedList'),
            blockquote: instance.isActive('blockquote'),
            link: instance.isActive('link'),
            linkHref: (instance.getAttributes('link').href ?? '') as string,
        }),
    });

    const closeDialog = () => setDialog(null);

    const save = () => {
        setIsSaving(true);

        router.put(
            updateContent(pageId).url,
            { content: editor.getJSON() },
            {
                preserveScroll: true,
                // Without this the component remounts on the redirect back
                // and the editor loses its caret and undo history.
                preserveState: true,
                onSuccess: () => {
                    setIsDirty(false);
                    // The server clears the concept as part of publishing
                    // (Page::writeContent), so the status line must stop
                    // saying there is one.
                    autosave.forget();
                    setEditingDraft(null);
                },
                onFinish: () => setIsSaving(false),
            },
        );
    };

    /*
     * Throw the concept away and go back to what the site is serving.
     *
     * The published body is already here in the `content` prop, so this is a
     * delete plus a local swap rather than a reload — the owner keeps their
     * scroll position and the screen never blanks. See showPublished() below
     * for the swap and for why it cannot be left to the prop.
     */
    const revertToPublished = async () => {
        const answered = await confirm({
            title: t('ui.editor.draft.revert_title'),
            description: t('ui.editor.draft.revert_description'),
            confirmLabel: t('ui.editor.draft.revert_confirm'),
            destructive: true,
        });

        if (!answered) {
            return;
        }

        setIsReverting(true);

        router.delete(destroyDraft(pageId).url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => showPublished(content),
            onFinish: () => setIsReverting(false),
        });
    };

    /**
     * Put a body the server has just published into the editor.
     *
     * Shared by the draft revert above and by restoring a version, because
     * both end the same way: something other than this editor decided what
     * the published body is, and the editor has to be showing that rather
     * than what it happened to be holding. `useEditor` reads its document
     * once, when it is built, so a changed prop does nothing on its own.
     *
     * `emitUpdate: false` is load-bearing in both cases. Setting the content
     * normally fires onUpdate, which bumps `revision`, which is exactly what
     * the autosave debounces on — so the body just published would be written
     * straight back as a concept, and the owner would be told they have
     * unpublished work a moment after publishing.
     */
    const showPublished = (document: TipTapDoc | null) => {
        editor
            .chain()
            .setContent(initialDocument(document), { emitUpdate: false })
            .focus()
            .run();

        setEditingDraft(null);
        setIsDirty(false);
        autosave.forget();
    };

    return (
        <div className="grid gap-3">
            {editingDraft !== null && (
                <DraftNotice
                    savedAt={editingDraft}
                    onRevert={revertToPublished}
                    isReverting={isReverting}
                />
            )}

            {/* The contenteditable itself drops its outline (an outline
                around a whole document body reads as an error state), so the
                frame takes the focus ring instead — the editor must still
                announce itself as focused to a keyboard user. */}
            <div className="overflow-hidden rounded-lg border border-border bg-background focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring">
                <div className="flex flex-wrap items-center gap-1 border-b border-border bg-card p-2">
                    <ToolbarButton
                        icon={Bold}
                        label={t('ui.editor.bold')}
                        active={state.bold}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    />
                    <ToolbarButton
                        icon={Italic}
                        label={t('ui.editor.italic')}
                        active={state.italic}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    />
                    <ToolbarButton
                        icon={SubscriptIcon}
                        label={t('ui.editor.subscript')}
                        active={state.subscript}
                        onClick={() =>
                            editor.chain().focus().toggleSubscript().run()
                        }
                    />
                    <ToolbarButton
                        icon={SuperscriptIcon}
                        label={t('ui.editor.superscript')}
                        active={state.superscript}
                        onClick={() =>
                            editor.chain().focus().toggleSuperscript().run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Heading2}
                        label={t('ui.editor.heading_2')}
                        active={state.heading2}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 2 })
                                .run()
                        }
                    />
                    <ToolbarButton
                        icon={Heading3}
                        label={t('ui.editor.heading_3')}
                        active={state.heading3}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .toggleHeading({ level: 3 })
                                .run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={AlignLeft}
                        label={t('ui.editor.align_left')}
                        active={state.alignLeft}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('left').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignCenter}
                        label={t('ui.editor.align_center')}
                        active={state.alignCenter}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('center').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignRight}
                        label={t('ui.editor.align_right')}
                        active={state.alignRight}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('right').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignJustify}
                        label={t('ui.editor.align_justify')}
                        active={state.alignJustify}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('justify').run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={List}
                        label={t('ui.editor.bullet_list')}
                        active={state.bulletList}
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                    />
                    <ToolbarButton
                        icon={ListOrdered}
                        label={t('ui.editor.ordered_list')}
                        active={state.orderedList}
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                    />
                    <ToolbarButton
                        icon={Quote}
                        label={t('ui.editor.blockquote')}
                        active={state.blockquote}
                        onClick={() =>
                            editor.chain().focus().toggleBlockquote().run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Link2}
                        label={t('ui.editor.link')}
                        active={state.link}
                        onClick={() => setDialog('link')}
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Paperclip}
                        label={t('ui.editor.insert_file')}
                        onClick={() => setDialog('file')}
                    />
                    <ToolbarButton
                        icon={Images}
                        label={t('ui.editor.insert_images')}
                        onClick={() => setDialog('image')}
                    />
                    <ToolbarButton
                        icon={WrapText}
                        label={t('ui.editor.insert_image_aside')}
                        onClick={() => setDialog('imageAside')}
                    />
                    <ToolbarButton
                        icon={Youtube}
                        label={t('ui.editor.insert_youtube')}
                        onClick={() => setDialog('youtube')}
                    />
                    <ToolbarButton
                        icon={Music2}
                        label={t('ui.editor.insert_tiktok')}
                        onClick={() => setDialog('tiktok')}
                    />
                    <ToolbarButton
                        icon={Instagram}
                        label={t('ui.editor.insert_instagram')}
                        onClick={() => setDialog('instagram')}
                    />
                    <ToolbarButton
                        icon={TableIcon}
                        label={t('ui.editor.insert_table')}
                        onClick={() =>
                            editor
                                .chain()
                                .focus()
                                .insertTable({
                                    rows: 3,
                                    cols: 3,
                                    withHeaderRow: true,
                                })
                                .run()
                        }
                    />
                </div>

                {/* Table commands, only while the caret is in one. Eight
                    permanent buttons for something most pages never use would
                    crowd out the ones every page needs. */}
                {state.inTable && (
                    <div className="flex flex-wrap items-center gap-1 border-b border-border bg-card px-2 pb-2">
                        <Rows3
                            aria-hidden="true"
                            className="mx-1 size-4 text-muted-foreground"
                        />
                        <TableButton
                            label={t('ui.editor.row_above')}
                            onClick={() =>
                                editor.chain().focus().addRowBefore().run()
                            }
                        />
                        <TableButton
                            label={t('ui.editor.row_below')}
                            onClick={() =>
                                editor.chain().focus().addRowAfter().run()
                            }
                        />
                        <TableButton
                            label={t('ui.editor.delete_row')}
                            onClick={() =>
                                editor.chain().focus().deleteRow().run()
                            }
                        />

                        <ToolbarSeparator />

                        <Columns3
                            aria-hidden="true"
                            className="mx-1 size-4 text-muted-foreground"
                        />
                        <TableButton
                            label={t('ui.editor.column_left')}
                            onClick={() =>
                                editor.chain().focus().addColumnBefore().run()
                            }
                        />
                        <TableButton
                            label={t('ui.editor.column_right')}
                            onClick={() =>
                                editor.chain().focus().addColumnAfter().run()
                            }
                        />
                        <TableButton
                            label={t('ui.editor.delete_column')}
                            onClick={() =>
                                editor.chain().focus().deleteColumn().run()
                            }
                        />

                        <ToolbarSeparator />

                        <TableButton
                            label={t('ui.editor.merge_cells')}
                            onClick={() =>
                                editor.chain().focus().mergeOrSplit().run()
                            }
                        />

                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="ml-auto text-error hover:text-error"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() =>
                                editor.chain().focus().deleteTable().run()
                            }
                        >
                            <Trash2 aria-hidden="true" />
                            {t('ui.editor.delete_table')}
                        </Button>
                    </div>
                )}

                <div className="relative">
                    <EditorContent editor={editor} />

                    {state.isEmpty && (
                        <p className="pointer-events-none absolute top-4 left-4 text-muted-foreground select-none">
                            {t('ui.editor.placeholder')}
                        </p>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-3">
                {/* Two lines, because they answer two different questions:
                    whether the body on screen is on the site, and whether it
                    has at least been kept somewhere. A concept that is safely
                    stored is still not published, and saying only "saved"
                    would be the more comforting of the two lies. */}
                <p
                    aria-live="polite"
                    className="mr-auto text-sm text-muted-foreground"
                >
                    {draftLine(autosave.status, autosave.savedAt)}
                </p>

                <p className="text-sm text-muted-foreground">
                    {isDirty ? t('ui.editor.unsaved') : t('ui.editor.saved')}
                </p>

                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void autosave.saveNow()}
                    disabled={
                        !isDirty || isSaving || autosave.status === 'saving'
                    }
                >
                    {autosave.status === 'saving' ? (
                        <Spinner aria-hidden="true" />
                    ) : (
                        <FileClock aria-hidden="true" />
                    )}
                    {t('ui.editor.draft.save')}
                </Button>

                <Button
                    type="button"
                    onClick={save}
                    disabled={!isDirty || isSaving}
                >
                    {isSaving ? (
                        <Spinner aria-hidden="true" />
                    ) : (
                        <Save aria-hidden="true" />
                    )}
                    {t('ui.editor.save')}
                </Button>
            </div>

            {/* Both halves of "is there a concept": one this screen opened
                with, and one the autosave has written since. Restoring is a
                publish and a publish ends the concept, so the confirmation
                has to say so — and it would be wrong in exactly the session
                where the owner had been typing. */}
            <VersionHistory
                pageId={pageId}
                revisions={revisions}
                hasDraft={editingDraft !== null || autosave.savedAt !== null}
                onRestored={(document, library) => {
                    // Before the document, not after: setContent remounts the
                    // node views, and they resolve their embed against the
                    // holder at that moment. The other order draws "these
                    // images no longer exist" over a gallery that is intact.
                    holder.merge(library);
                    showPublished(document);
                }}
            />

            {/* Each dialog is mounted only while it is open, so it always
                starts from a clean slate — no effect to reset its state. */}
            {dialog === 'file' && (
                <FilePickerDialog
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    uploadedCount={uploadedCount}
                    onUploaded={(record) => {
                        addToLibrary(record);

                        // An image is not something a file embed can show —
                        // the server decides the type by sniffing the bytes,
                        // so this is found out here. It is in the library now
                        // and the image button will offer it.
                        if (record.type === 'image') {
                            toast.info(
                                t('ui.editor.image_not_a_file', {
                                    name: record.original_filename,
                                }),
                            );

                            return;
                        }

                        // Inserted immediately — uploading from the editor
                        // means "put this on the page", not "add to library".
                        editor
                            .chain()
                            .focus()
                            .insertContent({
                                type: 'fileEmbed',
                                attrs: { ulid: record.ulid },
                            })
                            .run();

                        setUploadedCount((count) => count + 1);
                    }}
                    onClose={() => {
                        setUploadedCount(0);
                        closeDialog();
                    }}
                    onSelect={(file) => {
                        // Found by search rather than uploaded, so the holder
                        // has never seen it — without this the node view
                        // mounted just below would find nothing to draw.
                        holder.addFile(file);

                        editor
                            .chain()
                            .focus()
                            .insertContent({
                                type: 'fileEmbed',
                                attrs: { ulid: file.ulid },
                            })
                            .run();
                        setUploadedCount(0);
                        closeDialog();
                    }}
                />
            )}

            {dialog === 'image' && (
                <ImagePickerDialog
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    onUploaded={addToLibrary}
                    onPicked={(image) => holder.addImage(image)}
                    onClose={closeDialog}
                    onSelect={(ulids) => {
                        editor
                            .chain()
                            .focus()
                            .insertContent({
                                type: 'imageGallery',
                                attrs: { ulids },
                            })
                            .run();
                        closeDialog();
                    }}
                />
            )}

            {dialog === 'imageAside' && (
                <ImagePickerDialog
                    maxBytes={maxBytes}
                    acceptedFormats={acceptedFormats}
                    multiple={false}
                    onUploaded={addToLibrary}
                    onPicked={(image) => holder.addImage(image)}
                    onClose={closeDialog}
                    onSelect={(ulids) => {
                        // Side/size default and are adjusted on the block
                        // afterwards, once the owner can see the result.
                        if (ulids[0] !== undefined) {
                            editor
                                .chain()
                                .focus()
                                .insertContent({
                                    type: 'imageAside',
                                    attrs: { ulid: ulids[0] },
                                })
                                .run();
                        }

                        closeDialog();
                    }}
                />
            )}

            {dialog === 'youtube' && (
                <YouTubeDialog
                    onClose={closeDialog}
                    onSelect={(videoId) => {
                        editor
                            .chain()
                            .focus()
                            .insertContent({
                                type: 'youtubeEmbed',
                                attrs: { videoId },
                            })
                            .run();
                        closeDialog();
                    }}
                />
            )}

            {(dialog === 'tiktok' || dialog === 'instagram') && (
                <SocialDialog
                    platform={dialog}
                    onClose={closeDialog}
                    onSelect={(postId) => {
                        editor
                            .chain()
                            .focus()
                            .insertContent({
                                type: 'socialEmbed',
                                attrs: { platform: dialog, postId },
                            })
                            .run();
                        closeDialog();
                    }}
                />
            )}

            {dialog === 'link' && (
                <LinkDialog
                    initialHref={state.linkHref}
                    onClose={closeDialog}
                    onSubmit={(href) => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .setLink({ href })
                            .run();
                        closeDialog();
                    }}
                    onRemove={() => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run();
                        closeDialog();
                    }}
                />
            )}
        </div>
    );
}

/**
 * The concept's own status line.
 *
 * Built from a fixed set of branches rather than by interpolating the status
 * into a key: a key assembled from a variable is one LocalisationTest cannot
 * check, and a missing one puts a dotted path on screen.
 */
function draftLine(status: DraftStatus, savedAt: string | null): string {
    if (status === 'saving') {
        return t('ui.editor.draft.saving');
    }

    if (status === 'failed') {
        return t('ui.editor.draft.failed');
    }

    if (savedAt === null) {
        return '';
    }

    const time = new Date(savedAt).toLocaleTimeString(intlLocale, {
        hour: '2-digit',
        minute: '2-digit',
    });

    return `${t('ui.editor.draft.saved_at', { time })} ${t('ui.editor.draft.unpublished')}`;
}

function ToolbarButton({
    icon: Icon,
    label,
    active = false,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    active?: boolean;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            size="icon"
            variant={active ? 'secondary' : 'ghost'}
            aria-label={label}
            aria-pressed={active}
            title={label}
            // Keep the selection intact: the toolbar must not take focus away
            // from the document before the command runs.
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            <Icon aria-hidden="true" />
        </Button>
    );
}

function ToolbarSeparator() {
    return <Separator orientation="vertical" className="mx-1 !h-6" />;
}

/** A table command as a word, not an icon — no icon set distinguishes "row
 * above" from "row below" at 16px, and these only show while in a table. */
function TableButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            size="sm"
            variant="ghost"
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            {label}
        </Button>
    );
}
