import { ExternalLink, Instagram, Play } from 'lucide-react';
import * as React from 'react';
import { EMBED_ALLOW, EMBED_REFERRER_POLICY } from '@/lib/embeds';
import { socialPermalink, tikTokEmbedUrl } from '@/lib/social-embed';
import type { SocialPlatform } from '@/lib/social-embed';
import { t } from '@/lib/i18n';

/*
 * A TikTok or an Instagram reel on a lesson page. The two are drawn
 * differently, and the asymmetry is the decision rather than an inconsistency.
 *
 * TikTok's embed endpoint serves a real player — measured: three <video>
 * elements with actual sources. So it can be watched without leaving the site,
 * and the only question is when the platform gets to see the student. It has
 * no privacy-preserving host the way YouTube has youtube-nocookie, so nothing
 * is requested until a student presses the card. Before that this is our own
 * markup: no iframe, and no thumbnail either, because a thumbnail is a request
 * too.
 *
 * Instagram's embed endpoint serves no video at all — also measured: five
 * images, a static play-button overlay and links back to instagram.com,
 * byte-identical whether the URL says /p/ or /reel/. Framing it would hand
 * Meta every student's IP and cookies in exchange for a picture they cannot
 * watch, so it is not framed at all. The card links out, instagram.com is
 * absent from frame-src, and lib/social-embed.ts has no function that could
 * build the URL.
 *
 * If a teacher wants a reel to actually play in a lesson, the answer is the
 * media library: a video they have the rights to, uploaded, streams from our
 * own nginx with working scrubbing and no third party in it at all.
 */

export function SocialEmbedFrame({
    platform,
    postId,
}: {
    platform: SocialPlatform;
    postId: string;
}) {
    return platform === 'tiktok' ? (
        <TikTokCard postId={postId} />
    ) : (
        <InstagramCard postId={postId} />
    );
}

/** Per-node state on purpose: one press must not load a page's worth. */
function TikTokCard({ postId }: { postId: string }) {
    const [loaded, setLoaded] = React.useState(false);

    return (
        // Vertical video: portrait and capped, or one clip takes over a page
        // of text. `clear-both` for the same reason every other block has it —
        // a floated image must not wrap around this.
        <figure className="clear-both my-6 flex justify-center">
            <div className="aspect-[9/16] w-full max-w-sm overflow-hidden rounded-lg border border-border bg-muted">
                {loaded ? (
                    <iframe
                        src={tikTokEmbedUrl(postId)}
                        title={t('ui.public.social_title', {
                            platform: 'TikTok',
                        })}
                        loading="lazy"
                        allowFullScreen
                        // Without this the player gets no Referer at all and
                        // refuses to start. See EMBED_REFERRER_POLICY.
                        referrerPolicy={EMBED_REFERRER_POLICY}
                        allow={EMBED_ALLOW}
                        className="size-full border-0"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setLoaded(true)}
                        className="flex size-full flex-col items-center justify-center gap-3 p-6 text-center hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span className="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground">
                            <Play className="size-6" aria-hidden="true" />
                        </span>
                        <span className="text-sm font-medium">
                            {t('ui.public.social_load', { platform: 'TikTok' })}
                        </span>
                        {/* The honest sentence. Not a cookie banner, and it
                            asks for consent to nothing else — it says what
                            this one button does. */}
                        <span className="text-xs text-muted-foreground">
                            {t('ui.public.social_notice', {
                                platform: 'TikTok',
                            })}
                        </span>
                    </button>
                )}
            </div>
        </figure>
    );
}

/**
 * A link, and nothing that talks to Meta.
 *
 * An anchor rather than a button that reveals an iframe: there is no second
 * step to reveal. Pressing it leaves the site, which is what `ExternalLink`
 * and the new tab are there to say before it happens.
 */
function InstagramCard({ postId }: { postId: string }) {
    return (
        <figure className="clear-both my-6 flex justify-center">
            <a
                href={socialPermalink('instagram', postId)}
                target="_blank"
                rel="noopener noreferrer"
                className="flex w-full max-w-sm flex-col items-center gap-3 rounded-lg border border-border bg-card p-6 text-center hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span className="flex size-14 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Instagram className="size-6" aria-hidden="true" />
                </span>
                <span className="inline-flex items-center gap-1.5 text-sm font-medium">
                    {t('ui.public.instagram_open')}
                    <ExternalLink className="size-3.5" aria-hidden="true" />
                </span>
                {/* Said plainly, because a student who expected a video here
                    deserves to know why there isn't one rather than assume
                    the page is broken. */}
                <span className="text-xs text-muted-foreground">
                    {t('ui.public.instagram_notice')}
                </span>
            </a>
        </figure>
    );
}
