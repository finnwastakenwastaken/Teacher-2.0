import { expect, test } from '@playwright/test';

/**
 * nginx sends `Referrer-Policy: same-origin` site-wide, so a cross-origin
 * request carries no Referer — and YouTube needs that header to identify the
 * embedding site, otherwise showing "configuration error 153" instead of the
 * video. Every YouTube iframe therefore sets its own referrer policy; there's
 * no server-side guard, so a stale front-end build fails silently this way.
 *
 * Asserts the attribute, not that the video plays — CI has no business
 * reaching youtube.com.
 */
test('a YouTube embed carries its own referrer policy', async ({ page }) => {
    await page.goto('/e2e/video');

    const iframe = page.locator('iframe[src*="youtube"]');

    await expect(iframe).toHaveAttribute(
        'referrerpolicy',
        'strict-origin-when-cross-origin',
    );

    // Bare origin only — must never send the path, which would name the
    // lesson a student is reading.
    await expect(iframe).toHaveAttribute(
        'src',
        /^https:\/\/www\.youtube-nocookie\.com\//,
    );
});
