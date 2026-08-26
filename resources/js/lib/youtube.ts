/**
 * Only the 11-character video id is ever stored (PageContent refuses
 * anything else); the embed URL is rebuilt from it, so a pasted URL's host,
 * query string and tracking parameters never reach a rendered page.
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

/*
 * The referrer policy and the allow list an embedded player needs live in
 * lib/embeds.ts now, because three platforms share them. This file keeps only
 * what is specific to YouTube.
 */
