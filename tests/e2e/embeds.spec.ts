import { expect, test } from '@playwright/test';

/**
 * nginx sends `Referrer-Policy: same-origin` for the whole site, so a
 * cross-origin request carries no Referer at all — and YouTube uses that
 * header to identify the embedding site, answering its absence with
 * "Video player configuration error 153" instead of the video.
 *
 * Every YouTube iframe therefore sets its own referrer policy. There is no
 * server-side guard for this: the attribute lives in the bundle, so a stale
 * front-end build fails exactly this way and nothing about it looks wrong.
 *
 * The test asserts the attribute rather than that the video plays. CI has no
 * business reaching youtube.com, and a green tick that depends on a third
 * party being up is worse than no tick.
 */
test('a YouTube embed carries its own referrer policy', async ({ page }) => {
    await page.goto('/e2e/video');

    const iframe = page.locator('iframe[src*="youtube"]');

    await expect(iframe).toHaveAttribute(
        'referrerpolicy',
        'strict-origin-when-cross-origin',
    );

    // The privacy-preserving host, and the bare origin only — the policy above
    // must never become one that sends the path, because the path names the
    // lesson a student is reading.
    await expect(iframe).toHaveAttribute(
        'src',
        /^https:\/\/www\.youtube-nocookie\.com\//,
    );
});
