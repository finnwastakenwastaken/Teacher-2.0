import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * TikTok and Instagram Reels, and the promise that neither is contacted until
 * a student asks.
 *
 * That promise is the whole reason these blocks are allowed on the site at
 * all. Neither platform publishes a privacy-preserving host the way YouTube
 * publishes youtube-nocookie, so an embed that loaded with the page would set
 * cookies for every child who opened a worksheet, whether or not they ever
 * watched anything. The public renderer therefore draws a card of our own
 * markup and mounts the iframe only on a press.
 *
 * Nothing else in the project can catch a regression here. The PHP suite sees
 * the stored JSON and never a rendered page; the CSP allows the hosts, so a
 * renderer that loaded them eagerly would work perfectly and silently undo the
 * decision. Only a browser watching its own network can tell the difference.
 *
 * Both hosts are blocked outright below, so no request leaves CI even after
 * the press — what is asserted is that the browser *tried*, and when.
 */

const PLATFORM_HOSTS = /(tiktok\.com|instagram\.com)/;

/**
 * Record every request the page makes to either platform, and refuse to let
 * one out.
 *
 * The abort matters for more than hermeticism: a real load would pull in a
 * player that makes further requests of its own, and the counts below would
 * then be measuring TikTok's behaviour rather than ours.
 */
async function watchPlatformRequests(page: Page): Promise<string[]> {
    const seen: string[] = [];

    await page.route(PLATFORM_HOSTS, async (route) => {
        seen.push(route.request().url());
        await route.abort();
    });

    return seen;
}

test('nothing is requested from either platform before a student presses play', async ({
    page,
}) => {
    const requests = await watchPlatformRequests(page);

    await page.goto('/e2e/social');
    await page.waitForLoadState('networkidle');

    // Both cards are drawn — TikTok as a button that will load a player,
    // Instagram as a link that will not.
    await expect(page.getByRole('button', { name: /TikTok/ })).toBeVisible();
    await expect(page.getByRole('link', { name: /Instagram/ })).toBeVisible();

    // And nothing has been fetched from either. No iframe, and no thumbnail
    // either — a preview image is a request too, which is why the card has
    // none.
    expect(requests).toEqual([]);
    await expect(page.locator('iframe')).toHaveCount(0);
});

test('pressing the TikTok card loads it, and Instagram is never framed at all', async ({
    page,
}) => {
    const requests = await watchPlatformRequests(page);

    await page.goto('/e2e/social');
    await page.getByRole('button', { name: /TikTok/ }).click();

    const iframe = page.locator('iframe');

    await expect(iframe).toHaveCount(1);

    // Built from the stored id, on the embed endpoint — never the URL that
    // was pasted, which is what keeps a share link's tracking parameters out
    // of a rendered page.
    await expect(iframe).toHaveAttribute(
        'src',
        'https://www.tiktok.com/embed/v2/7234567890123456789',
    );

    // Same reason as the YouTube embed beside it: the site-wide `same-origin`
    // policy sends no Referer at all, and a player that cannot identify the
    // embedding site refuses to start. The bare origin, never the path — the
    // path names the lesson a student is reading.
    await expect(iframe).toHaveAttribute(
        'referrerpolicy',
        'strict-origin-when-cross-origin',
    );

    await expect
        .poll(() => requests.filter((url) => url.includes('tiktok')).length)
        .toBeGreaterThan(0);

    // Exactly one iframe on a page holding two embeds: Instagram has no
    // loading step because it is never framed. Its embed document carries no
    // video, so framing it would spend a student's IP and cookies on a
    // picture they could not watch.
    expect(requests.filter((url) => url.includes('instagram'))).toEqual([]);
});

test('the Instagram block is a link out, and contacts Instagram never', async ({
    page,
}) => {
    const requests = await watchPlatformRequests(page);

    await page.goto('/e2e/social');
    await page.waitForLoadState('networkidle');

    const link = page.getByRole('link', { name: /Instagram/ });

    // A real link with a real destination, so a student can see where they
    // are going before they go — and so it works with a middle-click and a
    // screen reader like any other link.
    await expect(link).toHaveAttribute(
        'href',
        'https://www.instagram.com/p/C1a2B3c4D5e/',
    );
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('rel', /noopener/);

    // Nothing was fetched, and there is no press that would fetch anything.
    // This is the assertion the whole block exists to keep true.
    expect(requests).toEqual([]);
});
