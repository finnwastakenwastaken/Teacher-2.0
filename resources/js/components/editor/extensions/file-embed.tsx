import {
    mergeAttributes,
    Node,
    NodeViewWrapper,
    ReactNodeViewRenderer,
} from '@tiptap/react';
import type { ReactNodeViewProps } from '@tiptap/react';
import { Trash2 } from 'lucide-react';
import {
    EMPTY_MEDIA_LIBRARY,
    findLibraryFile,
    libraryFromOptions,
} from '@/components/editor/media-library';
import type { MediaEmbedOptions } from '@/components/editor/media-library';
import { FileTypeIcon } from '@/components/file-type-icon';
import { Button } from '@/components/ui/button';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

/*
 * A single document or video, referenced by ULID.
 *
 * The attribute set matches App\Support\PageContent exactly — that class is
 * the authority, and an attribute it does not know about is dropped on save.
 *
 * parseHTML/renderHTML exist only for Tiptap's internal clipboard handling.
 * Page bodies are stored and loaded as JSON, so neither is ever involved in
 * persistence or in rendering the public page.
 */

function FileEmbedView(props: ReactNodeViewProps) {
    const library = libraryFromOptions(props.extension.options);
    const file = findLibraryFile(library, props.node.attrs.ulid);

    return (
        <NodeViewWrapper className="clear-both my-4">
            <div
                className={cn(
                    'rounded-lg border border-border bg-card p-3',
                    props.selected && 'ring-2 ring-ring',
                )}
            >
                {file === null ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.file_missing')}
                    </p>
                ) : (
                    <FileEmbedBody file={file} />
                )}

                <div className="mt-3 flex justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => props.deleteNode()}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('ui.actions.delete')}
                    </Button>
                </div>
            </div>
        </NodeViewWrapper>
    );
}

function FileEmbedBody({
    file,
}: {
    file: NonNullable<ReturnType<typeof findLibraryFile>>;
}) {
    if (file.kind === 'video') {
        return (
            <div className="grid gap-2">
                <video
                    controls
                    preload="metadata"
                    src={file.url}
                    className="max-h-96 w-full rounded-md bg-muted"
                />
                <p className="truncate text-xs text-muted-foreground">
                    {file.original_filename} · {formatBytes(file.size_bytes)}
                </p>
            </div>
        );
    }

    return (
        <div className="flex items-center gap-3">
            <FileTypeIcon
                mime={file.mime}
                kind={file.kind}
                className="size-6 shrink-0 text-muted-foreground"
            />
            <div className="min-w-0">
                <p
                    className="truncate font-medium"
                    title={file.original_filename}
                >
                    {file.original_filename}
                </p>
                <p className="text-xs text-muted-foreground">
                    {t('ui.editor.blocks.download_block')} ·{' '}
                    {formatBytes(file.size_bytes)}
                </p>
            </div>
        </div>
    );
}

export const FileEmbed = Node.create<MediaEmbedOptions>({
    name: 'fileEmbed',
    group: 'block',
    atom: true,
    selectable: true,

    addOptions() {
        return {
            library: EMPTY_MEDIA_LIBRARY,
        };
    },

    addAttributes() {
        return {
            ulid: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-ulid'),
                renderHTML: (attributes) => ({ 'data-ulid': attributes.ulid }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="fileEmbed"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { 'data-type': 'fileEmbed' }),
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(FileEmbedView);
    },
});
