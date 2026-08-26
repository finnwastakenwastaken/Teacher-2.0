import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * Media is authorised by PHP, then streamed by nginx via X-Accel-Redirect.
 * The PHP suite runs with MEDIA_X_ACCEL=false (PHP reads the file itself, as
 * the user that wrote it), while production hands the path to nginx, which
 * reads it as a different user from a read-only mount — a mismatch that once
 * caused a total outage a fully green PHP suite never saw. Only these tests,
 * against the real nginx, can catch that.
 */
const downloadHref = async (locator: {
    getAttribute(name: string): Promise<string | null>;
}) => {
    const href = await locator.getAttribute('href');
    expect(href, 'the page should offer a download link').toBeTruthy();

    return href as string;
};

test('a download on an open page streams through nginx', async ({
    page,
    request,
}) => {
    await page.goto('/e2e/open');

    const href = await downloadHref(
        page.locator('a[href*="/downloads/"]').first(),
    );

    const response = await request.get(href);
    expect(response.status()).toBe(200);

    const headers = response.headers();

    // nginx served it, not PHP: PHP does not emit these.
    expect(headers['accept-ranges']).toBe('bytes');
    expect(headers['etag']).toBeTruthy();

    // add_header doesn't merge in nginx — a location setting one discards all
    // inherited ones silently, which once dropped these four from gated media.
    expect(headers['x-frame-options']).toBe('SAMEORIGIN');
    expect(headers['referrer-policy']).toBe('same-origin');
    expect(headers['content-security-policy']).toBeTruthy();
    expect(headers['x-content-type-options']).toBe('nosniff');
});

test('a range request is answered by nginx, so video can be scrubbed', async ({
    page,
    request,
}) => {
    await page.goto('/e2e/open');

    const href = await downloadHref(
        page.locator('a[href*="/downloads/"]').first(),
    );

    const response = await request.get(href, {
        headers: { Range: 'bytes=0-99' },
    });

    expect(response.status()).toBe(206);
    expect((await response.body()).length).toBe(100);
});

test('a file reachable only from a locked page is refused anonymously', async ({
    page,
    request,
}) => {
    // The owner can see the locked page without holding its password, which
    // is how the URL becomes known. `request` is a separate, genuinely
    // anonymous context.
    await useAdminSession(page);
    await page.goto('/e2e/locked');

    const href = await downloadHref(
        page.locator('a[href*="/downloads/"]').first(),
    );

    const response = await request.get(href);
    expect(response.status()).toBe(403);
});

test('the internal locations are unreachable from outside', async ({
    request,
}) => {
    for (const path of ['/__media/', '/__backup/', '/storage/app/private']) {
        const response = await request.get(path, { maxRedirects: 0 });
        expect(response.status(), `${path} must not be servable`).toBe(404);
    }
});
