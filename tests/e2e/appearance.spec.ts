import { expect, test } from '@playwright/test';

/**
 * The light/dark switch in the public header.
 *
 * The half worth a browser is not that the class flips — that is one line of
 * React. It is that the choice survives a fresh document: `updateAppearance()`
 * mirrors it into an `appearance` cookie, HandleAppearance reads that cookie,
 * and Blade writes the class onto <html> before a byte of JavaScript runs. A
 * control that only toggled the class on the live page would satisfy every
 * assertion here up to the reload, and look correct until a student clicked a
 * link.
 *
 * Anonymous on purpose. This is the one preference a student can express, and
 * it must work with no session at all.
 */
test('the header toggle survives a full page load, because it writes the cookie', async ({
    page,
}) => {
    await page.goto('/e2e/open');

    // Dark is this site's default, not "follow the operating system".
    await expect(page.locator('html')).toHaveClass(/dark/);

    // Icon-only, so the accessible name is the only name it has — and it says
    // what the press does rather than which theme is showing.
    const toLight = page.getByRole('button', {
        name: 'Overschakelen naar de lichte weergave',
    });

    await toLight.click();

    await expect(page.locator('html')).not.toHaveClass(/dark/);

    // Asked for as raw markup, over the page's own cookie jar, with no
    // JavaScript running at all: this is the server's answer, so nothing that
    // merely toggled a class in the browser could have produced it.
    const markup = await (await page.request.get('/e2e/open')).text();
    expect(markup).toMatch(/<html[^>]*\sclass=""/);

    await page.reload();

    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await expect(
        page.getByRole('button', {
            name: 'Overschakelen naar de donkere weergave',
        }),
    ).toBeVisible();
});
