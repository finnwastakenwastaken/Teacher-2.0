import { expect, test } from '@playwright/test';

/**
 * The page that says what the site records. Worth a browser test rather than
 * only a feature test for two reasons: it has to be *reachable* — a statement
 * nobody can find answers nobody's question — and it is the one page where the
 * §1 language split is visible in a single view, because the statement is the
 * application's own words and any addition below it is the owner's.
 */
test('a visitor can reach the privacy page from any page, without an account', async ({
    page,
}) => {
    await page.goto('/e2e/open');

    // The footer link is the only route to it, so the test walks it rather
    // than navigating straight to /privacy.
    await page
        .getByRole('contentinfo')
        .getByRole('link', { name: /privacy/i })
        .click();

    await expect(page).toHaveURL(/\/privacy$/);

    await expect(
        page.getByRole('heading', { level: 1, name: /privacy/i }),
    ).toBeVisible();
});

/**
 * Every claim on the page is one the code has to keep, so the honest ones are
 * asserted too — the page is allowed to be uncomfortable, and a later edit
 * that quietly drops the awkward half should fail here.
 */
test('the statement covers what is kept as well as what is not', async ({
    page,
}) => {
    await page.goto('/privacy');

    const body = page.getByRole('article');

    // The reassuring half.
    await expect(body).toContainText(/geen account/i);
    await expect(body).toContainText(/geen analyseprogramma/i);

    // The half that is against interest, and the reason this page is
    // trustworthy at all: server logs, and YouTube seeing an IP address.
    await expect(body).toContainText(/logboek/i);
    await expect(body).toContainText(/YouTube/);
});

test('the statement is translated, because it is the application speaking', async ({
    page,
}) => {
    await page.goto('/privacy');

    await expect(page.locator('html')).toHaveAttribute('lang', 'nl');
    await expect(page.getByRole('article')).toContainText(/geen account/i);

    await page.getByLabel('Taal').selectOption('en');

    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByRole('article')).toContainText(/no account/i);
});

test('the page does not push a phone sideways', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/privacy');

    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    );

    expect(overflow).toBe(0);
});
