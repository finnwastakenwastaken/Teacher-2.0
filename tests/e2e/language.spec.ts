import { expect, test } from '@playwright/test';

/**
 * The interface is Dutch or English and the visitor chooses; content is never
 * translated. That split is the load-bearing rule, so the test asserts both
 * halves at once: the chrome changes and the teacher's own words do not.
 */
test('switching language changes the interface and leaves the content alone', async ({
    page,
}) => {
    await page.goto('/e2e/open');

    // Decided server-side and rendered by Blade, so it is right before
    // hydration — there is no first-paint flash to catch.
    await expect(page.locator('html')).toHaveAttribute('lang', 'nl');

    const title = await page.getByRole('heading', { level: 1 }).innerText();

    await page.getByLabel('Taal').selectOption('en');

    // Switching sets a cookie and reloads the document, because the dictionary
    // arrives with it rather than as a shared prop.
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    // The chrome is English now...
    await expect(page.getByLabel('Language')).toBeVisible();

    // ...and the owner's words are untouched.
    expect(await page.getByRole('heading', { level: 1 }).innerText()).toBe(
        title,
    );
});

test('an unsupported locale is refused rather than stored', async ({
    request,
}) => {
    // The cookie has to be collected first and echoed back as the header, or
    // the CSRF middleware answers 419 and the allow-list is never reached —
    // which would make this test pass for the wrong reason if it asserted
    // "not 200". It is percent-encoded in the cookie and not in the header.
    await request.get('/e2e/open');

    const { cookies } = await request.storageState();
    const token = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value;

    expect(token, 'the site should have set an XSRF-TOKEN cookie').toBeTruthy();

    const response = await request.post('/taal', {
        form: { locale: 'de' },
        headers: {
            'X-XSRF-TOKEN': decodeURIComponent(token as string),
            Accept: 'application/json',
        },
        failOnStatusCode: false,
    });

    expect(response.status()).toBe(422);
});

test('a hand-written locale cookie is ignored, because the real one is encrypted', async ({
    browser,
}) => {
    const context = await browser.newContext({
        extraHTTPHeaders: { 'Accept-Language': 'nl' },
    });

    await context.addCookies([
        {
            name: 'locale',
            value: 'en',
            url: process.env.E2E_BASE_URL ?? 'http://web',
        },
    ]);

    const page = await context.newPage();
    await page.goto('/e2e/open');

    await expect(page.locator('html')).toHaveAttribute('lang', 'nl');

    await context.close();
});
