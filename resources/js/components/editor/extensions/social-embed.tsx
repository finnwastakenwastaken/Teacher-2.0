import {
    mergeAttributes,
    Node,
    NodeViewWrapper,
    ReactNodeViewRenderer,
} from '@tiptap/react';
import type { ReactNodeViewProps } from '@tiptap/react';
import { ExternalLink, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { EMBED_REFERRER_POLICY } from '@/lib/embeds';
import { t } from '@/lib/i18n';
import {
    isSocialId,
    isSocialPlatform,
    socialPermalink,
    tikTokEmbedUrl,
} from '@/lib/social-embed';
import { cn } from '@/lib/utils';

/*
 * A TikTok or an Instagram Reel in the editor.
 *
 * Stored as a platform and a post id, nothing else; the URL is rebuilt from
 * the pair (see lib/social-embed.ts) and App\Support\PageContent checks the
 * same two patterns server-side, rejecting the node if they disagree. So this
 * view checks them too before drawing anything.
 *
 * **The TikTok preview loads for real, and the public page does not.** That is
 * deliberate rather than an inconsistency: the owner is one adult who chose to
 * embed this post and needs to see that they picked the right one, whereas the
 * public renderer draws a click-to-load card so that a class of students is not
 * silently handed to TikTok for opening a worksheet.
 *
 * **Instagram is not framed here either**, and that is the opposite reason:
 * its embed document carries no video at all, so the public page renders a
 * card that links out. An editor preview the public page cannot reproduce
 * would be a lie the owner only discovers after publishing. See
 * components/content/social-embed-frame.tsx for the measurements behind both.
 *
 * parseHTML/renderHTML are only Tiptap's clipboard plumbing — page bodies are
 * stored and loaded as JSON.
 */

function SocialEmbedView(props: ReactNodeViewProps) {
    const platform = props.node.attrs.platform;
    const postId = props.node.attrs.postId;

    const valid =
        isSocialPlatform(platform) &&
        typeof postId === 'string' &&
        isSocialId(platform, postId);

    return (
        <NodeViewWrapper className="clear-both my-4">
            <div
                className={cn(
                    'rounded-lg border border-border bg-card p-3',
                    props.selected && 'ring-2 ring-ring',
                )}
            >
                {!valid ? (
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.social_invalid')}
                    </p>
                ) : platform === 'tiktok' ? (
                    // Portrait, and capped: a clip is 9:16 and would otherwise
                    // take over the whole editor column.
                    <div className="mx-auto aspect-[9/16] w-full max-w-sm overflow-hidden rounded-md bg-muted">
                        <iframe
                            src={tikTokEmbedUrl(postId)}
                            title={t('ui.public.social_title', {
                                platform: 'TikTok',
                            })}
                            loading="lazy"
                            allowFullScreen
                            // Same reason as every other embed: without it the
                            // player gets no Referer and refuses to start.
                            referrerPolicy={EMBED_REFERRER_POLICY}
                            className="size-full border-0"
                        />
                    </div>
                ) : (
                    // Instagram is never framed — here either. The editor has
                    // to show what a student will get, and a preview the
                    // public page cannot produce would be a lie that only
                    // surfaces after publishing. The link below is how the
                    // owner checks they picked the right post.
                    <p className="text-sm text-muted-foreground">
                        {t('ui.editor.blocks.instagram_preview')}
                    </p>
                )}

                <div className="mt-3 flex items-center justify-between gap-2">
                    {/* The post as anyone else sees it, so a wrong pick is
                        obvious even when the inline preview will not load —
                        a private or deleted post renders as an empty frame,
                        which says nothing about why. */}
                    {valid ? (
                        <a
                            href={socialPermalink(platform, postId)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-sm text-link hover:underline"
                        >
                            <ExternalLink
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            {t('ui.editor.blocks.social_open')}
                        </a>
                    ) : (
                        <span />
                    )}

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

export const SocialEmbed = Node.create({
    name: 'socialEmbed',
    group: 'block',
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            platform: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-platform'),
                renderHTML: (attributes) => ({
                    'data-platform': attributes.platform,
                }),
            },
            postId: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-post-id'),
                renderHTML: (attributes) => ({
                    'data-post-id': attributes.postId,
                }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="socialEmbed"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'div',
            mergeAttributes(HTMLAttributes, { 'data-type': 'socialEmbed' }),
        ];
    },

    addNodeView() {
        return ReactNodeViewRenderer(SocialEmbedView);
    },
});
