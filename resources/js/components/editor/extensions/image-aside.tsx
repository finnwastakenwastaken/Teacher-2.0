import {
    mergeAttributes,
    Node,
    NodeViewWrapper,
    ReactNodeViewRenderer,
} from '@tiptap/react';
import type { ReactNodeViewProps } from '@tiptap/react';
import { PanelLeft, PanelRight, Trash2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    ASIDE_BASE,
    ASIDE_SIDE_CLASSES,
    ASIDE_SIZE_CLASSES,
} from '@/components/content/rich-text';
import {
    EMPTY_MEDIA_LIBRARY,
    findLibraryImage,
    libraryFromOptions,
} from '@/components/editor/media-library';
import type { MediaEmbedOptions } from '@/components/editor/media-library';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n';
import type { TipTapAsideSide, TipTapAsideSize } from '@/types/tiptap';

/*
 * Attribute set matches App\Support\PageContent: a ULID (refused if invalid)
 * plus two enumerations the server clamps to their default rather than
 * dropping the picture — both declare a real default here, so neither is in
 * PageContent::NULLABLE_ATTRS. Geometry classes are imported from the public
 * renderer, not restated, so the owner sees what a student sees.
 * parseHTML/renderHTML are only TipTap's clipboard plumbing; bodies are
 * stored as JSON.
 */

const SIDES: { value: TipTapAsideSide; icon: LucideIcon }[] = [
    { value: 'left', icon: PanelLeft },
    { value: 'right', icon: PanelRight },
];

/** S/M/L, not words — the control row lives inside the narrow floated box;
 * the accessible name carries the real word. */
const SIZES: { value: TipTapAsideSize; short: string }[] = [
    { value: 'small', short: 'S' },
    { value: 'medium', short: 'M' },
    { value: 'large', short: 'L' },
];

// Looked up at render, not module scope (t() would freeze the first-loaded
// language), with keys written out in full — a key built from `value` is
// invisible to LocalisationTest.
function sideLabel(side: TipTapAsideSide): string {
    return side === 'left'
        ? t('ui.editor.blocks.aside_left')
        : t('ui.editor.blocks.aside_right');
}

function sizeLabel(size: TipTapAsideSize): string {
    switch (size) {
        case 'small':
            return t('ui.editor.blocks.aside_small');
        case 'large':
            return t('ui.editor.blocks.aside_large');
        default:
            return t('ui.editor.blocks.aside_medium');
    }
}

function asSide(value: unknown): TipTapAsideSide {
    return value === 'left' ? 'left' : 'right';
}

function asSize(value: unknown): TipTapAsideSize {
    return value === 'small' || value === 'large' ? value : 'medium';
}

function ImageAsideView(props: ReactNodeViewProps) {
    const library = libraryFromOptions(props.extension.options);
    const image = findLibraryImage(library, props.node.attrs.ulid);

    const side = asSide(props.node.attrs.side);
    const size = asSize(props.node.attrs.size);

    return (
        <NodeViewWrapper
            className={cn(
                ASIDE_BASE,
                ASIDE_SIDE_CLASSES[side],
                ASIDE_SIZE_CLASSES[size],
            )}
        >
            <div
                className={cn(
                    'rounded-lg border border-border bg-card p-2',
                    props.selected && 'ring-2 ring-ring',
                )}
            >
                {image === null ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.aside_missing')}
                    </p>
                ) : (
                    <img
                        src={image.url}
                        alt={image.alt_text}
                        loading="lazy"
                        className="h-auto w-full rounded-md bg-muted object-contain"
                    />
                )}

                <div className="mt-2 flex flex-wrap items-center gap-1">
                    {SIDES.map((option) => {
                        const label = sideLabel(option.value);

                        return (
                            <Button
                                key={option.value}
                                type="button"
                                size="icon"
                                variant={
                                    side === option.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                aria-label={label}
                                aria-pressed={side === option.value}
                                title={label}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() =>
                                    props.updateAttributes({
                                        side: option.value,
                                    })
                                }
                            >
                                <option.icon aria-hidden="true" />
                            </Button>
                        );
                    })}

                    {SIZES.map((option) => {
                        const label = sizeLabel(option.value);

                        return (
                            <Button
                                key={option.value}
                                type="button"
                                size="icon"
                                variant={
                                    size === option.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                aria-label={label}
                                aria-pressed={size === option.value}
                                title={label}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() =>
                                    props.updateAttributes({
                                        size: option.value,
                                    })
                                }
                            >
                                <span aria-hidden="true" className="text-xs">
                                    {option.short}
                                </span>
                            </Button>
                        );
                    })}

                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label={t('ui.actions.delete')}
                        title={t('ui.actions.delete')}
                        className="ml-auto text-error hover:text-error"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => props.deleteNode()}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </NodeViewWrapper>
    );
}

export const ImageAside = Node.create<MediaEmbedOptions>({
    name: 'imageAside',
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
            // A real default, not null: there is no "nobody chose a side"
            // state to represent, and it is what keeps the server from ever
            // meeting a null here. See PageContent::NULLABLE_ATTRS.
            side: {
                default: 'right',
                parseHTML: (element) =>
                    asSide(element.getAttribute('data-side')),
                renderHTML: (attributes) => ({
                    'data-side': asSide(attributes.side),
                }),
            },
            size: {
                default: 'medium',
                parseHTML: (element) =>
                    asSize(element.getAttribute('data-size')),
                renderHTML: (attributes) => ({
                    'data-size': asSize(attributes.size),
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="imageAside"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { 'data-type': 'imageAside' }),
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(ImageAsideView);
    },
});
