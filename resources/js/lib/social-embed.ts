/**
 * TikTok and Instagram Reels, stored as a platform and a post id.
 *
 * Same rule as YouTube: only the id is ever stored, and the embed URL is
 * rebuilt from it, so a pasted URL's query string and tracking parameters
 * never reach a rendered page — there is nothing for them to ride along in.
 * App\Support\PageContent enforces the same patterns server-side.
 *
 * **These are iframes, and no third-party script is loaded.** Both platforms
 * publish an "official" embed that is a blockquote plus a script from their
 * own domain; that script is what their documentation tells you to use, and
 * it is a third-party script running on a page of teaching material with
 * access to everything on it. Both also serve a plain iframe endpoint, which
 * is what these URLs are, so the whole `script-src` question does not arise.
 *
 * **Nothing is loaded until a student asks.** See the click-to-load wrapper in
 * components/content/social-embed-frame.tsx for why that is not a nicety
 * here: neither platform has a privacy-preserving host the way YouTube has
 * youtube-nocookie, so an embed that loaded on page view would track a class
 * of children who had done nothing but open a worksheet.
 */

export const SOCIAL_PLATFORMS = ['tiktok', 'instagram'] as const;

export type SocialPlatform = (typeof SOCIAL_PLATFORMS)[number];

/**
 * TikTok numbers its videos; Instagram gives each post a short code. Both are
 * matched strictly, because the value is interpolated into a URL.
 */
const ID_PATTERNS: Record<SocialPlatform, RegExp> = {
    tiktok: /^\d{10,25}$/,
    instagram: /^[A-Za-z0-9_-]{5,20}$/,
};

const HOSTS: Record<SocialPlatform, string[]> = {
    tiktok: ['tiktok.com'],
    instagram: ['instagram.com', 'instagr.am'],
};

/** `/@user/video/123`, and the `/v/123` form their share sheet produces. */
const TIKTOK_PATH = /\/(?:video|v)\/(\d{10,25})/;

/** `/reel/CODE/`, `/p/CODE/`, `/tv/CODE/` — all three are one post to us. */
const INSTAGRAM_PATH = /\/(?:reel|reels|p|tv)\/([A-Za-z0-9_-]{5,20})/;

export function isSocialId(platform: SocialPlatform, value: string): boolean {
    return ID_PATTERNS[platform].test(value);
}

export function isSocialPlatform(value: unknown): value is SocialPlatform {
    return (
        typeof value === 'string' &&
        (SOCIAL_PLATFORMS as readonly string[]).includes(value)
    );
}

/**
 * Accepts a bare id or a pasted URL and returns the post id, or null.
 *
 * A shortened link — `vm.tiktok.com/XXXX`, `instagr.am/p/…` behind a
 * redirect — cannot be resolved without following it, and following it from
 * the owner's browser would tell the platform who is authoring the page. So
 * those are refused with a message asking for the full address, rather than
 * fetched. See the dialog copy.
 */
export function extractSocialId(
    platform: SocialPlatform,
    input: string,
): string | null {
    const trimmed = input.trim();

    if (trimmed === '') {
        return null;
    }

    if (isSocialId(platform, trimmed)) {
        return trimmed;
    }

    let url: URL;

    try {
        // A pasted "tiktok.com/@a/video/1" has no scheme; assume https rather
        // than rejecting what the owner clearly meant.
        url = new URL(trimmed.includes('://') ? trimmed : `https://${trimmed}`);
    } catch {
        return null;
    }

    const host = url.hostname.replace(/^www\./, '').toLowerCase();

    if (!HOSTS[platform].includes(host)) {
        return null;
    }

    const pattern = platform === 'tiktok' ? TIKTOK_PATH : INSTAGRAM_PATH;
    const found = pattern.exec(url.pathname)?.[1] ?? null;

    return found !== null && isSocialId(platform, found) ? found : null;
}

/**
 * TikTok's iframe endpoint, rebuilt from the stored id.
 *
 * **There is deliberately no Instagram counterpart, and its absence is the
 * guard.** Instagram's `/embed/` document contains no `<video>` element at
 * all — measured, not assumed: five images, a static play-button overlay and
 * links back to instagram.com, identical whether the URL says `/p/` or
 * `/reel/`. The video is simply never sent to a logged-out viewer, and no
 * parameter changes that; playing it off-platform needs their Graph API with
 * an approved app.
 *
 * So framing Instagram would hand every student's IP and cookies to Meta in
 * exchange for a thumbnail they could not watch. The block renders as a card
 * that links out instead, `frame-src` does not name instagram.com, and this
 * function cannot be called with a platform that has nowhere to point.
 */
export function tikTokEmbedUrl(postId: string): string {
    return `https://www.tiktok.com/embed/v2/${encodeURIComponent(postId)}`;
}

/**
 * The post as anyone else sees it.
 *
 * The owner opens this from the editor to check they picked the right thing;
 * for Instagram it is also what a student follows, because that is the only
 * place the video plays. TikTok's permalink carries the author's handle, which
 * the id alone does not tell us — `@_` is a placeholder TikTok resolves to the
 * real author on the way through.
 */
export function socialPermalink(
    platform: SocialPlatform,
    postId: string,
): string {
    const id = encodeURIComponent(postId);

    return platform === 'tiktok'
        ? `https://www.tiktok.com/@_/video/${id}`
        : `https://www.instagram.com/p/${id}/`;
}
