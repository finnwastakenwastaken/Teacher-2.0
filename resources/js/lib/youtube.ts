/**
 * YouTube helpers shared by the editor and the public renderer.
 *
 * Only the 11-character video id is ever stored (App\Support\PageContent
 * refuses anything else), and the embed URL is rebuilt from that id. A pasted
 * URL's host, query string and tracking parameters therefore never reach a
 * rendered page — there is nothing for them to ride along in.
 */

const VIDEO_ID = /^[A-Za-z0-9_-]{11}$/;

const PATH_FORMS = /^\/(?:embed|v|shorts|live)\/([^/?#]+)/;

const HOSTS = ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'];

export function isYouTubeId(value: string): boolean {
    return VIDEO_ID.test(value);
}

/**
 * Accepts a bare id or any of the usual YouTube URL shapes and returns the
 * video id, or null when nothing usable can be found.
 */
export function extractYouTubeId(input: string): string | null {
    const trimmed = input.trim();

    if (trimmed === '') {
        return null;
    }

    if (isYouTubeId(trimmed)) {
        return trimmed;
    }

    let url: URL;

    try {
        // A pasted "youtu.be/xxx" has no scheme; assume https rather than
        // rejecting what the owner clearly meant.
        url = new URL(trimmed.includes('://') ? trimmed : `https://${trimmed}`);
    } catch {
        return null;
    }

    const host = url.hostname.replace(/^www\./, '').toLowerCase();
    const candidates: (string | null)[] = [];

    if (host === 'youtu.be') {
        candidates.push(url.pathname.slice(1));
    }

    if (HOSTS.includes(host)) {
        candidates.push(url.searchParams.get('v'));
        candidates.push(PATH_FORMS.exec(url.pathname)?.[1] ?? null);
    }

    for (const candidate of candidates) {
        if (candidate !== null && isYouTubeId(candidate)) {
            return candidate;
        }
    }

    return null;
}

/**
 * The privacy-preserving embed host. Students are never tracked (the technical
 * reference), so youtube-nocookie is the only domain used here.
 */
export function youTubeEmbedUrl(videoId: string): string {
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}`;
}

/**
 * Every YouTube iframe must carry this, or the player refuses to start.
 *
 * nginx sends `Referrer-Policy: same-origin` for the whole site, which means a
 * cross-origin request carries no `Referer` at all — verified by watching one
 * arrive at our own access log as "-". YouTube's embedded player uses that
 * header to work out which site is embedding it, and without it answers with
 * "Video player configuration error 153" instead of the video.
 *
 * So the policy is relaxed here and **only** here. `strict-origin-when-cross-
 * origin` sends the bare origin — `https://example.school` — and never the
 * path, so YouTube still learns nothing about *which lesson page* a student is
 * reading. That is the part worth protecting; the origin it necessarily knows
 * already, because it is serving the embed.
 *
 * Do not fix this by widening the nginx header: that would leak the path to
 * every other cross-origin destination on the site as well.
 */
export const YOUTUBE_REFERRER_POLICY = 'strict-origin-when-cross-origin';
