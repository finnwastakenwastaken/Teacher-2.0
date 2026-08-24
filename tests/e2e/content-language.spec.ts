import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The content-language setting, at `/admin/instellingen`.
 *
 * Not the interface locale (App\Support\Locale, which the visitor picks) —
 * this is which PostgreSQL text-search configuration `pages.search_vector`
 * is stemmed with (App\Support\ContentLanguage), and it matters because the
 * trigger only fires on a write. Changing the setting and stopping there
 * would leave every already-stored page stemmed by the old rules: nothing
 * looks broken, search just quietly stops finding words it plainly
 * contains. `SiteSettingsController::update()` covers for that by calling
 * `search:reindex` itself, synchronously, the moment the setting changes.
 *
 * The fixture page (`SeedBrowserTestFixtures::seedSearchFixture`) is chosen
 * for a stemming difference that was checked directly against Postgres, not
 * guessed: `to_tsvector('dutch', 'running')` leaves the token unchanged, so
 * a search for "run" cannot match it, while `to_tsvector('english',
 * 'running')` reduces it to "run". That gives a clean before/after: search
 * for "run" fails under the default Dutch setting and succeeds once the
 * setting is Engels and the existing page has been re-indexed — which is
 * the one behaviour worth asserting here, not that a flash message appears.
 */
test('changing the content language re-indexes pages already in the database', async ({
    page,
}) => {
    await useAdminSession(page);

    // Baseline: under the default Dutch configuration, the fixture is not
    // found by its English inflection.
    await page.goto('/zoeken?q=run');
    await expect(page.getByText('Stemtest')).toHaveCount(0);

    await page.goto('/admin/instellingen');
    await page.getByLabel('Taal van je lesmateriaal').click();
    await page.getByRole('option', { name: 'Engels' }).click();

    // The Select's own state updates the instant an option is clicked —
    // before the form has even submitted — so reading it back is not proof
    // the server saw the change. Waiting for the response is: the settings
    // screen shows no toast on save (SiteSettingsEdit does not call
    // useStatusToasts, unlike the levels and passwords screens), so this is
    // the only signal that the round trip — and with it the synchronous
    // `search:reindex` inside the controller — actually completed before the
    // next navigation. Without it, `page.goto` below can tear down the page
    // mid-request and race the very thing this spec exists to prove.
    //
    // The wire method is POST, not PUT: Wayfinder's `update.form()` spoofs
    // PUT with a `_method` field for Inertia's <Form>, the way every browser
    // form for a non-GET/POST verb does — the update route only accepts PUT,
    // but nothing on the wire ever says so.
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/admin/instellingen') &&
                response.request().method() === 'POST',
        ),
        page.getByRole('button', { name: 'Opslaan' }).click(),
    ]);

    // The consequence: the fixture page, stored long before this request,
    // is findable now — proving the vector was rebuilt for existing rows,
    // not only for pages saved from this point on.
    await page.goto('/zoeken?q=run');
    await expect(page.getByRole('link', { name: 'Stemtest' })).toBeVisible();

    // Switch back, so the suite's baseline is Dutch again for anything that
    // runs after this file, and to prove the reindex is not one-directional.
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
