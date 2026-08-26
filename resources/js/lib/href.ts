/**
 * Link target validation, mirroring App\Support\PageContent::sanitiseHref.
 *
 * The server is the authority — it strips the link mark from anything it
 * refuses — so this exists to turn a refusal into a Dutch error message
 * before the owner saves, not to be the check that matters.
 */

const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

const SCHEME = /^([a-z][a-z0-9+.-]*):/i;

/**
 * Return the href to store, or null when the input cannot become one.
 */
export function normaliseHref(input: string): string | null {
    const trimmed = input.trim();

    if (trimmed === '' || trimmed.length > 2048) {
        return null;
    }

    // Protocol-relative (reads like a path but leaves the site) — refused,
    // matching the server. A backslash counts too: browsers normalise
    // `/\host` to `//host`, the same off-site link in disguise.
    if (/^\/[/\\]/.test(trimmed)) {
        return null;
    }

    if (trimmed.startsWith('/')) {
        return trimmed;
    }

    const scheme = SCHEME.exec(trimmed)?.[1]?.toLowerCase();

    if (scheme === undefined) {
        // A bare domain. Assume https rather than refusing: the scheme is
        // ours, so nothing the owner typed can smuggle one in.
        return isParsable(`https://${trimmed}`) ? `https://${trimmed}` : null;
    }

    if (!ALLOWED_SCHEMES.includes(scheme)) {
        return null;
    }

    return isParsable(trimmed) ? trimmed : null;
}

/** Whether a stored href points off-site, and so wants a new tab. */
export function isExternalHref(href: string): boolean {
    return /^https?:\/\//i.test(href.trim());
}

function isParsable(value: string): boolean {
    try {
        new URL(value);
    } catch {
        return false;
    }

    return true;
}
