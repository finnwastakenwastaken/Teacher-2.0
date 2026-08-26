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
    findLibraryImage,
    libraryFromOptions,
} from '@/components/editor/media-library';
import type {
    EditorLibraryImage,
    MediaEmbedOptions,
} from '@/components/editor/media-library';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';

/*
 * One or more images by ULID. Attribute set matches PageContent exactly: a
 * `ulids` list, every entry a real ULID or the node is refused. Alt text is
 * looked up, not stored here, so editing it in the library fixes every page
 * at once. parseHTML/renderHTML are only TipTap's clipboard plumbing.
 */

function toUlidList(value: unknown): string[] {
    return Array.isArray(value)
        ? value.filter((entry): entry is string => typeof entry === 'string')
        : [];
}

function ImageGalleryView(props: ReactNodeViewProps) {
    const library = libraryFromOptions(props.extension.options);
    const ulids = toUlidList(props.node.attrs.ulids);

    const images = ulids
        .map((ulid) => findLibraryImage(library, ulid))
        .filter((image): image is EditorLibraryImage => image !== null);

    return (
        <NodeViewWrapper className="clear-both my-4">
            <div
                className={cn(
                    'rounded-lg border border-border bg-card p-3',
                    props.selected && 'ring-2 ring-ring',
                )}
            >
                {images.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.images_missing')}
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {images.map((image) => (
                            <li
                                key={image.ulid}
                                className="flex aspect-video items-center justify-center overflow-hidden rounded-md bg-muted"
                            >
                                <img
                                    src={image.url}
                                    alt={image.alt_text}
                                    loading="lazy"
                                    className="max-h-full max-w-full object-contain"
                                />
                            </li>
                        ))}
                    </ul>
                )}

                <div className="mt-3 flex items-center justify-between gap-3">
                    <p className="text-xs text-muted-foreground">
                        {t('ui.editor.blocks.image_count', {
                            count: images.length,
                        })}
                    </p>
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

export const ImageGallery = Node.create<MediaEmbedOptions>({
    name: 'imageGallery',
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
            ulids: {
                default: [],
                parseHTML: (element) =>
                    toUlidList(
                        (element.getAttribute('data-ulids') ?? '')
                            .split(',')
                            .filter((entry) => entry !== ''),
                    ),
                renderHTML: (attributes) => ({
                    'data-ulids': toUlidList(attributes.ulids).join(','),
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="imageGallery"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { 'data-type': 'imageGallery' }),
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(ImageGalleryView);
    },
});
