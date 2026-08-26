import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The content-language setting (`/admin/instellingen`) picks the PostgreSQL
 * text-search config `pages.search_vector` is stemmed with — not the
 * interface locale. The stemming trigger only fires on write, so
 * `SiteSettingsController::update()` calls `search:reindex` synchronously to
 * re-stem pages already stored; otherwise the setting would appear to take
 * effect while search kept missing words in old pages.
 *
 * The fixture word ("running") is chosen because `to_tsvector('dutch', …)`
 * leaves it unstemmed while `to_tsvector('english', …)` reduces it to "run" —
 * verified against Postgres, giving a clean before/after for a search on "run".
 */
test('changing the content language re-indexes pages already in the database', async ({
    page,
}) => {
    await useAdminSession(page);

    // Baseline: under the default Dutch config the fixture is not found by
    // its English inflection.
    await page.goto('/zoeken?q=run');
    await expect(page.getByText('Stemtest')).toHaveCount(0);

    await page.goto('/admin/instellingen');
    await page.getByLabel('Taal van je lesmateriaal').click();
    await page.getByRole('option', { name: 'Engels' }).click();

    // The Select's own state updates the instant an option is clicked, before
    // the form submits, so reading it back proves nothing. The settings
    // screen shows no save toast, so waiting for this response is the only
    // signal the round trip (and the synchronous `search:reindex` inside it)
    // completed before `page.goto` below navigates away.
    //
    // Wire method is POST, not PUT: Wayfinder's `update.form()` spoofs PUT
    // with a `_method` field, as any HTML form does for a verb it can't send.
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/admin/instellingen') &&
                response.request().method() === 'POST',
        ),
        page.getByRole('button', { name: 'Opslaan' }).click(),
    ]);

    // Proves the vector was rebuilt for a pre-existing row, not just for
    // pages saved from now on.
    await page.goto('/zoeken?q=run');
    await expect(page.getByRole('link', { name: 'Stemtest' })).toBeVisible();

    // Switch back, restoring the suite's Dutch baseline and proving the
    // reindex isn't one-directional.
    await page.goto('/admin/instellingen');
    await page.getByLabel('Taal van je lesmateriaal').click();
    await page.getByRole('option', { name: 'Nederlands' }).click();

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/admin/instellingen') &&
                response.request().method() === 'POST',
        ),
        page.getByRole('button', { name: 'Opslaan' }).click(),
    ]);

    await page.goto('/zoeken?q=run');
    await expect(page.getByText('Stemtest')).toHaveCount(0);
});
