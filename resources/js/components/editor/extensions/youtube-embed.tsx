import {
    mergeAttributes,
    Node,
    NodeViewWrapper,
    ReactNodeViewRenderer,
} from '@tiptap/react';
import type { ReactNodeViewProps } from '@tiptap/react';
import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { isYouTubeId, youTubeEmbedUrl } from '@/lib/youtube';
import { EMBED_REFERRER_POLICY } from '@/lib/embeds';
import { t } from '@/lib/i18n';

/*
 * Stored as its 11-character id only; the embed URL is rebuilt from it (see
 * lib/youtube.ts) on the nocookie host, so no pasted tracking parameter
 * survives. This extension re-checks the id shape PageContent enforces
 * before drawing. parseHTML/renderHTML are only TipTap's clipboard plumbing.
 */

function YouTubeEmbedView(props: ReactNodeViewProps) {
    const videoId = props.node.attrs.videoId;
    const valid = typeof videoId === 'string' && isYouTubeId(videoId);

    return (
        <NodeViewWrapper className="clear-both my-4">
            <div
                className={cn(
                    'rounded-lg border border-border bg-card p-3',
                    props.selected && 'ring-2 ring-ring',
                )}
            >
                {valid ? (
                    <div className="aspect-video w-full overflow-hidden rounded-md bg-muted">
                        <iframe
                            src={youTubeEmbedUrl(videoId)}
                            title={t('ui.public.youtube_title')}
                            loading="lazy"
                            allowFullScreen
                            // Same reason as the public renderer: without it
                            // the preview shows error 153, not the video.
                            referrerPolicy={EMBED_REFERRER_POLICY}
                            className="size-full border-0"
                        />
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.youtube_invalid')}
                    </p>
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

export const YouTubeEmbed = Node.create({
    name: 'youtubeEmbed',
    group: 'block',
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            videoId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-video-id'),
                renderHTML: (attributes) => ({
                    'data-video-id': attributes.videoId,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="youtubeEmbed"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { 'data-type': 'youtubeEmbed' }),
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(YouTubeEmbedView);
    },
});
