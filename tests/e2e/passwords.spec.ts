import { expect, test } from '@playwright/test';

import { COOKIE_TEST_PASSWORD, useAdminSession } from './support/admin';

/**
 * Changing an access password, at `/admin/passwords`. The unlock cookie
 * carries a fingerprint of the *current* hash, so changing the password
 * invalidates every cookie issued under the old one — the only revocation
 * mechanism this system has, since there's no session list to sign anyone
 * out of. A feature test can see the cookie get rejected; only this can show
 * a real browser holding one get asked again.
 */
test('changing a password asks a browser holding the old unlock cookie again', async ({
    page,
    browser,
}) => {
    // Must stay the same browser context throughout for the assertion to
    // mean anything.
    await page.goto('/e2e/cookie-test');

    await expect(page.getByLabel('Wachtwoord')).toBeVisible();
    await page.getByLabel('Wachtwoord').fill(COOKIE_TEST_PASSWORD);
    await page.getByRole('button', { name: 'Ontgrendelen' }).click();

    await expect(page.getByText('Inhoud na ontgrendelen.')).toBeVisible();
    await expect(page.getByLabel('Wachtwoord')).toHaveCount(0);

    // Proves the cookie itself works, not just the unlock POST's redirect.
    await page.reload();
    await expect(page.getByText('Inhoud na ontgrendelen.')).toBeVisible();

    // A separate context: login is already spent once for the whole suite
    // (auth.setup.ts), and this must not touch the visitor's own cookies.
    const adminContext = await browser.newContext({
        locale: 'nl-NL',
        extraHTTPHeaders: { 'Accept-Language': 'nl' },
    });
    const adminPage = await adminContext.newPage();
    await useAdminSession(adminPage);

    await adminPage.goto('/admin/passwords');

    // Scoped only for the click that opens the row: editing swaps its name
    // (the filter's match target) for an `<input>` carrying it as a *value*,
    // not text, so re-filtering on it as visible text afterward would hang.
    const row = adminPage
        .getByRole('listitem')
        .filter({ has: adminPage.getByText('E2E-Cookie', { exact: true }) });

    await row.getByRole('button', { name: 'Bewerken' }).click();

    // Unscoped: only one password is ever mid-edit at a time, so "Nieuw
    // wachtwoord" is unique on the page regardless of row.
    await adminPage
        .getByLabel('Nieuw wachtwoord')
        .fill('e2e-cookie-changed-2026');
    await adminPage.getByRole('button', { name: 'Opslaan' }).click();

    // Back to view mode — "E2E-Cookie" is text again, so `row` is safe to
    // re-query — signals the update round-tripped.
    await expect(row.getByRole('button', { name: 'Bewerken' })).toBeVisible();

    // Same browser, same cookie value, but its fingerprint no longer matches
    // the new hash — nothing touched this browser's cookies directly.
    await page.reload();
    await expect(page.getByLabel('Wachtwoord')).toBeVisible();
    await expect(page.getByText('Inhoud na ontgrendelen.')).toHaveCount(0);

    // Restores the fixture's secret so this spec, like content-language.spec.ts,
    // doesn't need a reseed to run twice in a row. `e2e:seed` does the same
    // reset independently, belt and braces.
    await row.getByRole('button', { name: 'Bewerken' }).click();
    await adminPage.getByLabel('Nieuw wachtwoord').fill(COOKIE_TEST_PASSWORD);
    await adminPage.getByRole('button', { name: 'Opslaan' }).click();
    await expect(row.getByRole('button', { name: 'Bewerken' })).toBeVisible();

    await adminContext.close();
});
