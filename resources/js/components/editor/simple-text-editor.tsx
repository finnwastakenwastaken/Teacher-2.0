import Link from '@tiptap/extension-link';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import * as React from 'react';
import { LinkDialog } from '@/components/editor/link-dialog';
import { Button } from '@/components/ui/button';
import { normaliseHref } from '@/lib/href';
import { cn } from '@/lib/utils';
import type { TipTapDoc } from '@/types/tiptap';

/*
 * A cut-down page editor: text, links and lists, and nothing that embeds a
 * file.
 *
 * The homepage introduction uses this. Embeds are what publish a file to
 * anonymous visitors, and that decision is made by walking from a file to
 * the pages showing it — the homepage is not a page row, so an embed there
 * would render for the owner and 403 for everyone else. The server strips
 * embeds regardless (App\Support\PageContent::sanitiseWithoutEmbeds); this
 * just means the buttons are not offered in the first place.
 */

const PROSE_CLASSES = [
    '[&_p]:my-3',
    '[&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold',
    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:pl-6',
    '[&_ol]:my-3 [&_ol]:list-decimal [&_ol]:pl-6',
    '[&_li]:my-1',
    '[&_blockquote]:my-4 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:text-muted-foreground',
    '[&_a]:text-link [&_a]:underline [&_a]:underline-offset-4',
].join(' ');

type Props = {
    content: TipTapDoc | null;
    onChange: (document: TipTapDoc | null) => void;
    /** Id of the element naming this editor. A contenteditable is not a form
     *  control, so a <label for> would have nothing to point at. */
    labelledBy?: string;
};

export function SimpleTextEditor({ content, onChange, labelledBy }: Props) {
    const [linkOpen, setLinkOpen] = React.useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                code: false,
                codeBlock: false,
                strike: false,
                underline: false,
                horizontalRule: false,
                link: false,
                heading: { levels: [2] },
            }),
            Link.configure({
                openOnClick: false,
                autolink: false,
                defaultProtocol: 'https',
                isAllowedUri: (url) => normaliseHref(url) !== null,
            }),
            // H₂O belongs in an introduction as much as in a lesson. Tables
            // do not — this editor stays a paragraph editor.
            Subscript,
            Superscript,
        ],
        content: (content?.content?.length ?? 0) > 0 ? content : '',
        editorProps: {
            attributes: {
                class: cn('min-h-32 p-4 focus:outline-none', PROSE_CLASSES),
                role: 'textbox',
                'aria-multiline': 'true',
                ...(labelledBy ? { 'aria-labelledby': labelledBy } : {}),
            },
        },
        onUpdate: ({ editor: instance }) => {
            const document = instance.getJSON() as TipTapDoc;

            onChange(instance.isEmpty ? null : document);
        },
    });

    const state = useEditorState({
        editor,
        selector: ({ editor: instance }) => ({
            bold: instance.isActive('bold'),
            italic: instance.isActive('italic'),
            subscript: instance.isActive('subscript'),
            superscript: instance.isActive('superscript'),
            heading2: instance.isActive('heading', { level: 2 }),
            bulletList: instance.isActive('bulletList'),
            orderedList: instance.isActive('orderedList'),
            blockquote: instance.isActive('blockquote'),
            link: instance.isActive('link'),
            linkHref: (instance.getAttributes('link').href ?? '') as string,
        }),
    });

    return (
        <>
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
                    <ToolbarButton
                        icon={Heading2}
                        label="Kop"
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
                    <ToolbarButton
                        icon={Link2}
                        label="Link"
                        active={state.link}
                        onClick={() => setLinkOpen(true)}
                    />
                </div>

                <EditorContent editor={editor} />
            </div>

            {/* Mounted only while open, so it reads the current href every
                time — same contract as the page editor's use of it. */}
            {linkOpen && (
                <LinkDialog
                    initialHref={state.linkHref}
                    onClose={() => setLinkOpen(false)}
                    onSubmit={(href) => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .setLink({ href })
                            .run();
                        setLinkOpen(false);
                    }}
                    onRemove={() => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run();
                        setLinkOpen(false);
                    }}
                />
            )}
        </>
    );
}

function ToolbarButton({
    icon: Icon,
    label,
    active,
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
            variant={active ? 'secondary' : 'ghost'}
            size="sm"
            onClick={onClick}
            aria-label={label}
            aria-pressed={active}
            title={label}
        >
            <Icon className="size-4" />
        </Button>
    );
}
