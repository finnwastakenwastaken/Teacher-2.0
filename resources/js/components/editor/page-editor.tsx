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
    Heading2,
    Heading3,
    Images,
    Italic,
    Link2,
    List,
    ListOrdered,
    Paperclip,
    Quote,
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
import { YouTubeEmbed } from '@/components/editor/extensions/youtube-embed';
import { FilePickerDialog } from '@/components/editor/file-picker-dialog';
import { ImagePickerDialog } from '@/components/editor/image-picker-dialog';
import { LinkDialog } from '@/components/editor/link-dialog';
import { YouTubeDialog } from '@/components/editor/youtube-dialog';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { normaliseHref } from '@/lib/href';
import { cn } from '@/lib/utils';
import { update as updateContent } from '@/routes/admin/pages/content';
import { GrowingEditorLibrary } from '@/components/editor/media-library';
import type { EditorMediaLibrary } from '@/components/editor/media-library';
import type { TipTapDoc } from '@/types/tiptap';
import { t } from '@/lib/i18n';

/*
 * The page body editor.
 *
 * Everything it can produce is on the whitelist in App\Support\PageContent —
 * the extensions below are configured to match it, so the editor cannot
 * create a node the server would silently drop. StarterKit therefore has
 * code, code blocks, strikethrough, underline and horizontal rules switched
 * off, and headings are limited to the three levels below the page title.
 *
 * The reverse is the rule that bites: a node type or mark added here must be
 * added to PageContent as well, or it silently stops saving. Subscript,
 * superscript, text alignment and the four table nodes all have entries
 * there, and a case in components/content/rich-text.tsx.
 *
 * Saving is explicit. There is no autosave in this version: the teacher may
 * legitimately want to abandon an edit, and an autosave would republish
 * media references (which is what makes a file publicly reachable) on every
 * keystroke.
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
    // Tables. `table-fixed` is what makes the column resizer's widths mean
    // anything; without it the browser re-negotiates every column on each
    // keystroke and the handles drift away from the borders they belong to.
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
    // Everything that must not sit in the gutter beside a floated image (see
    // the imageAside notes in components/content/rich-text.tsx). Paragraphs
    // and lists are deliberately absent: wrapping is the feature. A `clear`
    // does nothing at all when nothing is floating, so this changes no page
    // that has no imageAside on it.
    '[&_h2]:clear-both [&_h3]:clear-both [&_h4]:clear-both',
    '[&_blockquote]:clear-both [&_table]:clear-both [&_.tableWrapper]:clear-both',
].join(' ');

type PageEditorProps = {
    pageId: number;
    content: TipTapDoc | null;
    mediaLibrary: EditorMediaLibrary;
    maxBytes: number;
};

export function PageEditor({
    pageId,
    content,
    mediaLibrary,
    maxBytes,
}: PageEditorProps) {
    const [isDirty, setIsDirty] = React.useState(false);
    const [isSaving, setIsSaving] = React.useState(false);
    const [dialog, setDialog] = React.useState<
        'file' | 'image' | 'imageAside' | 'youtube' | 'link' | null
    >(null);

    /*
     * The library the node views resolve an embed's geometry from.
     *
     * `useEditor` builds the extension list once, and rebuilding it to hand
     * the node views a new object would tear the editor down and take the
     * caret and the undo history with it. So the extensions get this one
     * object for the editor's whole life (GrowingEditorLibrary), and it is
     * mutated in place — never replaced — by uploads and by picking an
     * existing file or image out of the picker dialogs' own search results.
     *
     * It starts holding only what the page's body already shows (see
     * App\Http\Controllers\Admin\PageController::embeddedMedia): the picker
     * dialogs ask the server for anything else themselves, a page of matches
     * at a time, rather than this screen shipping the whole media library up
     * front. Nothing here needs a React re-render to take
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
            // Both get the holder, not the prop: see the comment on it
            // above. A value that were replaced on upload would mean
            // rebuilding the editor to make a new embed renderable.
            // H₂O and m/s². Not a formatting nicety in physics and chemistry
            // material — without them a lot of it simply cannot be written.
            Subscript,
            Superscript,
            TextAlign.configure({
                types: ['heading', 'paragraph'],
                // Null rather than 'left', so an unaligned paragraph carries
                // no attribute at all and existing documents do not all grow
                // one the next time they are saved.
                defaultAlignment: null,
            }),
            TableKit.configure({
                table: { resizable: true },
            }),
            FileEmbed.configure({ library: holder }),
            ImageGallery.configure({ library: holder }),
            ImageAside.configure({ library: holder }),
            YouTubeEmbed,
        ],
        // An empty stored document has no blocks, which ProseMirror's schema
        // refuses; the empty string gives it the paragraph it wants.
        content: (content?.content?.length ?? 0) > 0 ? content : '',
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
        onUpdate: () => setIsDirty(true),
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
                onSuccess: () => setIsDirty(false),
                onFinish: () => setIsSaving(false),
            },
        );
    };

    return (
        <div className="grid gap-3">
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
                <p className="text-sm text-muted-foreground">
                    {isDirty ? t('ui.editor.unsaved') : t('ui.editor.saved')}
                </p>
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

            {/* Each dialog is mounted only while it is open, so it always
                starts from a clean slate — no effect to reset its state. */}
            {dialog === 'file' && (
                <FilePickerDialog
                    maxBytes={maxBytes}
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

                        // Inserted straight away: uploading from inside the
                        // editor means "put this on the page", and leaving it
                        // in the library would be the same two-step dance
                        // this affordance exists to remove.
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
                    multiple={false}
                    onUploaded={addToLibrary}
                    onPicked={(image) => holder.addImage(image)}
                    onClose={closeDialog}
                    onSelect={(ulids) => {
                        // Side and size are left to the node's own defaults
                        // and changed on the block afterwards, where the owner
                        // can see the result. Asking for them in the dialog
                        // would be asking about a layout they cannot see yet.
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

/**
 * A table command, as a word rather than an icon.
 *
 * There are eight of these and no icon set distinguishes "row above" from
 * "row below" at 16px. They only appear while the caret is inside a table, so
 * the space is affordable and the words are unambiguous.
 */
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
