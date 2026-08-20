/*
 * CSRF for hand-rolled fetch() calls.
 *
 * Inertia's router adds the token itself, but the chunked upload endpoints
 * return JSON rather than an Inertia response, so they are driven by fetch()
 * and have to carry the header on their own. Laravel's VerifyCsrfToken reads
 * X-XSRF-TOKEN and expects the *decrypted* cookie value, so the cookie has to
 * be URL-decoded first — sending it raw yields a 419.
 */

export function csrfToken(): string {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    if (!match) {
        return '';
    }

    return decodeURIComponent(match.slice('XSRF-TOKEN='.length));
}

/**
 * Headers every JSON-returning admin endpoint needs. `Accept` matters as much
 * as the token: without it Laravel answers validation failures with a redirect
 * instead of the 422 + `message` body the uploader reports to the user.
 */
export function jsonRequestHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}
