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
import {
    YOUTUBE_REFERRER_POLICY,
    isYouTubeId,
    youTubeEmbedUrl,
} from '@/lib/youtube';

/*
 * A YouTube video, stored as its 11-character id and nothing else.
 *
 * The embed URL is rebuilt from that id (see lib/youtube.ts), on the
 * nocookie host, so no pasted tracking parameter can survive into a rendered
 * page. App\Support\PageContent rejects any videoId that is not exactly 11
 * id characters, so this extension checks the same thing before drawing.
 *
 * parseHTML/renderHTML are only Tiptap's clipboard plumbing — page bodies are
 * stored and loaded as JSON.
 */

function YouTubeEmbedView(props: ReactNodeViewProps) {
    const videoId = props.node.attrs.videoId;
    const valid = typeof videoId === 'string' && isYouTubeId(videoId);

    return (
        <NodeViewWrapper className="my-4">
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
                            title="YouTube-video"
                            loading="lazy"
                            allowFullScreen
                            // Same reason as the public renderer: without it
                            // the preview shows error 153, not the video.
                            referrerPolicy={YOUTUBE_REFERRER_POLICY}
                            className="size-full border-0"
                        />
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Ongeldige YouTube-video. Verwijder dit blok.
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
                        Verwijderen
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
