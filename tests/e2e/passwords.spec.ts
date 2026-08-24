import { expect, test } from '@playwright/test';

import { COOKIE_TEST_PASSWORD, useAdminSession } from './support/admin';

/**
 * Changing an access password, at `/admin/passwords`.
 *
 * The security-relevant behaviour is not that the form saves — it is that
 * the unlock cookie carries a fingerprint of the *current* hash
 * (App\Support\AccessControl), so changing the password invalidates every
 * cookie issued under the old one on its next use. That is the only
 * revocation mechanism this system has: there is no session list to sign
 * someone out of, only a hash to change. A feature test can see the cookie
 * gets rejected; it cannot see a real browser hold one, get asked again, and
 * fail to get back in without the new password — which is the failure mode
 * that actually matters to the owner.
 */
test('changing a password asks a browser holding the old unlock cookie again', async ({
    page,
    browser,
}) => {
    // The visitor unlocks the fixture page and the cookie persists for the
    // rest of the test, in the same browser context throughout — this half
    // has to stay genuinely the same browser for the assertion to mean
    // anything, the way `useAdminSession` never touches this page.
    await page.goto('/e2e/cookie-test');

    await expect(page.getByLabel('Wachtwoord')).toBeVisible();
    await page.getByLabel('Wachtwoord').fill(COOKIE_TEST_PASSWORD);
    await page.getByRole('button', { name: 'Ontgrendelen' }).click();

    await expect(page.getByText('Inhoud na ontgrendelen.')).toBeVisible();
    await expect(page.getByLabel('Wachtwoord')).toHaveCount(0);

    // A second visit proves the cookie itself is doing the work, not just
    // the redirect the unlock POST ends with.
    await page.reload();
    await expect(page.getByText('Inhoud na ontgrendelen.')).toBeVisible();

    // The admin changes the password, in a context of its own — logging in
    // was already spent once for the whole suite (see auth.setup.ts), and
    // this must not touch the visitor's own cookies.
    const adminContext = await browser.newContext({
        locale: 'nl-NL',
        extraHTTPHeaders: { 'Accept-Language': 'nl' },
    });
    const adminPage = await adminContext.newPage();
    await useAdminSession(adminPage);

    await adminPage.goto('/admin/passwords');

    // Scoped to find the right row, but only for the click that opens it:
    // editing replaces this row's name — the thing the filter matches on —
    // with an <input> carrying it as a *value*, not as text, so a locator
    // still filtering on "E2E-Cookie" as visible text would find nothing
    // and hang waiting for a match that can only stop existing once entered.
    const row = adminPage
        .getByRole('listitem')
        .filter({ has: adminPage.getByText('E2E-Cookie', { exact: true }) });

    await row.getByRole('button', { name: 'Bewerken' }).click();

    // Unscoped from here: only one password is ever mid-edit at a time, so
    // "Nieuw wachtwoord" is unique on the page regardless of which row it
    // belongs to.
    await adminPage
        .getByLabel('Nieuw wachtwoord')
        .fill('e2e-cookie-changed-2026');
    await adminPage.getByRole('button', { name: 'Opslaan' }).click();

    // Back to view mode — re-querying `row` is safe again now that
    // "E2E-Cookie" is back in the DOM as text — is the signal the update
    // round-tripped.
    await expect(row.getByRole('button', { name: 'Bewerken' })).toBeVisible();

    // The consequence: the same browser, holding the same cookie value it
    // had a moment ago, is asked again — the fingerprint it carries no
    // longer matches the new hash. Nothing was done to this browser's
    // cookies directly; only the password changed.
    await page.reload();
    await expect(page.getByLabel('Wachtwoord')).toBeVisible();
    await expect(page.getByText('Inhoud na ontgrendelen.')).toHaveCount(0);

    // Restore the secret to what the fixture promises, through the same
    // admin context — so this spec, like content-language.spec.ts, does not
    // depend on a reseed to run twice in a row. `e2e:seed` does the same
    // reset independently (belt and braces: whichever runs between two
    // executions of this file, the next run starts from the same secret).
    await row.getByRole('button', { name: 'Bewerken' }).click();
    await adminPage.getByLabel('Nieuw wachtwoord').fill(COOKIE_TEST_PASSWORD);
    await adminPage.getByRole('button', { name: 'Opslaan' }).click();
    await expect(row.getByRole('button', { name: 'Bewerken' })).toBeVisible();

    await adminContext.close();
});
