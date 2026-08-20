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
    Youtube,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';
import type { UploadedRecord } from '@/components/admin/media-uploader';
import { FileEmbed } from '@/components/editor/extensions/file-embed';
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
        'file' | 'image' | 'youtube' | 'link' | null
    >(null);

    /*
     * The library, in two forms, because it now changes while the editor is
     * open: the pickers upload into it.
     *
     * `useEditor` builds the extension list once, and rebuilding it to hand
     * the node views a new object would tear the editor down and take the
     * caret and the undo history with it. So the extensions get this one
     * object for the editor's whole life (GrowingEditorLibrary) and uploads
     * change what is inside it. The second copy is plain React state, because
     * the picker dialogs are ordinary components and need a re-render.
     */
    const [holder] = React.useState(
        () => new GrowingEditorLibrary(mediaLibrary),
    );
    const [library, setLibrary] = React.useState<EditorMediaLibrary>(() =>
        holder.snapshot(),
    );
    const [uploadedCount, setUploadedCount] = React.useState(0);

    const addToLibrary = React.useCallback(
        (record: UploadedRecord) => {
            setLibrary(
                record.type === 'image'
                    ? holder.addImage({
                          ulid: record.ulid,
                          alt_text: record.alt_text,
                          original_filename: record.original_filename,
                          url: record.url,
                      })
                    : holder.addFile({
                          ulid: record.ulid,
                          kind: record.kind,
                          mime: record.mime,
                          size_bytes: record.size_bytes,
                          original_filename: record.original_filename,
                          url: record.url,
                      }),
            );
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
            YouTubeEmbed,
        ],
        // An empty stored document has no blocks, which ProseMirror's schema
        // refuses; the empty string gives it the paragraph it wants.
        content: (content?.content?.length ?? 0) > 0 ? content : '',
        editorProps: {
            attributes: {
                class: cn('min-h-64 p-4 focus:outline-none', PROSE_CLASSES),
                // A contenteditable is not a form control, so nothing else
                // gives this a name in the accessibility tree.
                role: 'textbox',
                'aria-multiline': 'true',
                'aria-label': 'Pagina-inhoud',
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
                        label="Vet"
                        active={state.bold}
                        onClick={() =>
                            editor.chain().focus().toggleBold().run()
                        }
                    />
                    <ToolbarButton
                        icon={Italic}
                        label="Cursief"
                        active={state.italic}
                        onClick={() =>
                            editor.chain().focus().toggleItalic().run()
                        }
                    />
                    <ToolbarButton
                        icon={SubscriptIcon}
                        label="Subscript (H₂O)"
                        active={state.subscript}
                        onClick={() =>
                            editor.chain().focus().toggleSubscript().run()
                        }
                    />
                    <ToolbarButton
                        icon={SuperscriptIcon}
                        label="Superscript (m/s²)"
                        active={state.superscript}
                        onClick={() =>
                            editor.chain().focus().toggleSuperscript().run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Heading2}
                        label="Kop 2"
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
                        label="Kop 3"
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
                        label="Links uitlijnen"
                        active={state.alignLeft}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('left').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignCenter}
                        label="Centreren"
                        active={state.alignCenter}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('center').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignRight}
                        label="Rechts uitlijnen"
                        active={state.alignRight}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('right').run()
                        }
                    />
                    <ToolbarButton
                        icon={AlignJustify}
                        label="Uitvullen"
                        active={state.alignJustify}
                        onClick={() =>
                            editor.chain().focus().setTextAlign('justify').run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={List}
                        label="Opsomming"
                        active={state.bulletList}
                        onClick={() =>
                            editor.chain().focus().toggleBulletList().run()
                        }
                    />
                    <ToolbarButton
                        icon={ListOrdered}
                        label="Genummerde lijst"
                        active={state.orderedList}
                        onClick={() =>
                            editor.chain().focus().toggleOrderedList().run()
                        }
                    />
                    <ToolbarButton
                        icon={Quote}
                        label="Citaat"
                        active={state.blockquote}
                        onClick={() =>
                            editor.chain().focus().toggleBlockquote().run()
                        }
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Link2}
                        label="Link"
                        active={state.link}
                        onClick={() => setDialog('link')}
                    />

                    <ToolbarSeparator />

                    <ToolbarButton
                        icon={Paperclip}
                        label="Bestand invoegen"
                        onClick={() => setDialog('file')}
                    />
                    <ToolbarButton
                        icon={Images}
                        label="Afbeeldingen invoegen"
                        onClick={() => setDialog('image')}
                    />
                    <ToolbarButton
                        icon={Youtube}
                        label="YouTube-video invoegen"
                        onClick={() => setDialog('youtube')}
                    />
                    <ToolbarButton
                        icon={TableIcon}
                        label="Tabel invoegen"
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
                            label="Rij erboven"
                            onClick={() =>
                                editor.chain().focus().addRowBefore().run()
                            }
                        />
                        <TableButton
                            label="Rij eronder"
                            onClick={() =>
                                editor.chain().focus().addRowAfter().run()
                            }
                        />
                        <TableButton
                            label="Rij wissen"
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
                            label="Kolom links"
                            onClick={() =>
                                editor.chain().focus().addColumnBefore().run()
                            }
                        />
                        <TableButton
                            label="Kolom rechts"
                            onClick={() =>
                                editor.chain().focus().addColumnAfter().run()
                            }
                        />
                        <TableButton
                            label="Kolom wissen"
                            onClick={() =>
                                editor.chain().focus().deleteColumn().run()
                            }
                        />

                        <ToolbarSeparator />

                        <TableButton
                            label="Cellen samenvoegen"
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
                            Tabel wissen
                        </Button>
                    </div>
                )}

                <div className="relative">
                    <EditorContent editor={editor} />

                    {state.isEmpty && (
                        <p className="pointer-events-none absolute top-4 left-4 text-muted-foreground select-none">
                            Schrijf hier de inhoud van deze pagina…
                        </p>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-3">
                <p className="text-sm text-muted-foreground">
                    {isDirty
                        ? 'Er zijn niet-opgeslagen wijzigingen.'
                        : 'Alle wijzigingen zijn opgeslagen.'}
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
                    Inhoud opslaan
                </Button>
            </div>

            {/* Each dialog is mounted only while it is open, so it always
                starts from a clean slate — no effect to reset its state. */}
            {dialog === 'file' && (
                <FilePickerDialog
                    files={library.files}
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
                                `"${record.original_filename}" is een afbeelding. Voeg hem in met de knop "Afbeeldingen invoegen".`,
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
                    images={library.images}
                    maxBytes={maxBytes}
                    onUploaded={addToLibrary}
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
