import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * Education levels, driven through the admin screen at `/admin/levels`.
 *
 * These write paths were covered at the HTTP layer only until now. What a
 * feature test cannot see is the merge flow's UI state machine — a level
 * "in use" swaps its delete button for a target picker instead of firing a
 * request straight away — and the consequence of a merge on the *public*
 * page a student actually reads, which is what these specs check.
 */
test.describe('education levels', () => {
    test.beforeEach(async ({ page }) => {
        await useAdminSession(page);
    });

    test('creating a level adds it to the list', async ({ page }) => {
        await page.goto('/admin/levels');

        await page.getByLabel('Nieuw niveau').fill('E2E-Nieuw');
        await page.getByRole('button', { name: 'Toevoegen' }).click();

        const handle = page.getByRole('button', {
            name: 'Verplaatsen: E2E-Nieuw',
        });
        await expect(handle).toBeVisible();

        // Clean up through the UI rather than leaving it for the seeder: the
        // level was never attached to a download, so the plain delete path
        // applies (see the merge spec below for the other one). A confirm()
        // dialog guards it, which Playwright auto-dismisses unless told
        // otherwise.
        page.once('dialog', (dialog) => dialog.accept());
        await page
            .getByRole('listitem')
            .filter({ has: handle })
            .getByRole('button', { name: 'Verwijderen' })
            .click();

        await expect(handle).toHaveCount(0);
    });

    /**
     * Retiring a level that is in use, via merge — the only way to delete
     * one, because a plain delete would strip the tag off every download
     * carrying it. The fixture (`SeedBrowserTestFixtures::seedLevelsFixture`)
     * carries the case the technical reference specifically calls out: one download tagged
     * with the source level alone, and one that already carries *both* the
     * source and the target. Merging the second must land on one tag, not
     * collide with the pivot's unique `[page_download_id, education_level_id]`
     * pair — a bug there would either 500 mid-transaction (leaving the level
     * undeleted, which the first assertion below would catch) or silently
     * drop the tag (which the last one would).
     *
     * Not self-restoring, unlike passwords.spec.ts and content-language.spec.ts:
     * the merge deletes E2E-Bron outright, and rebuilding it plus both
     * downloads' exact tag combinations through the UI would mostly just
     * re-implement `SeedBrowserTestFixtures::seedLevelsFixture`. Same
     * exception ordering.spec.ts already relies on for the same reason — a
     * second run in a row needs `e2e:seed` between the two, which is what
     * makes the level and its downloads exist again.
     */
    test('merging a level retires it and re-tags its downloads, including one that already carried both', async ({
        page,
    }) => {
        // Baseline, read off the public page rather than the admin one: a
        // download tagged for two tracks is listed under both headings, by
        // design (the technical reference, "page_downloads") — so "Both doc" appears twice
        // before the merge and "Solo doc" once.
        await page.goto('/e2e/levels/merge');

        await expect(
            page.getByRole('heading', { name: 'E2E-Bron', level: 3 }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'E2E-Doel', level: 3 }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Solo doc' })).toHaveCount(
            1,
        );
        await expect(page.getByRole('link', { name: 'Both doc' })).toHaveCount(
            2,
        );

        await page.goto('/admin/levels');

        const bronRow = page.getByRole('listitem').filter({
            has: page.getByRole('button', { name: 'Verplaatsen: E2E-Bron' }),
        });

        // In use, so this swaps the row into the merge picker rather than
        // deleting straight away — no confirm() dialog on this path.
        await bronRow.getByRole('button', { name: 'Verwijderen' }).click();

        // The select's content is portalled to the document body, so the
        // option is not a descendant of bronRow — only the trigger is.
        await bronRow.getByLabel('Samenvoegen met').click();
        await page.getByRole('option', { name: 'E2E-Doel' }).click();

        await bronRow
            .getByRole('button', { name: 'Samenvoegen en verwijderen' })
            .click();

        await expect(
            page.getByRole('button', { name: 'Verplaatsen: E2E-Bron' }),
        ).toHaveCount(0);

        // The consequence: E2E-Bron's heading is gone from the public page,
        // and E2E-Doel now carries both downloads — each exactly once, not
        // duplicated and not dropped.
        await page.goto('/e2e/levels/merge');

        await expect(
            page.getByRole('heading', { name: 'E2E-Bron', level: 3 }),
        ).toHaveCount(0);
        await expect(
            page.getByRole('heading', { name: 'E2E-Doel', level: 3 }),
        ).toBeVisible();
        await expect(page.getByRole('link', { name: 'Solo doc' })).toHaveCount(
            1,
        );
        await expect(page.getByRole('link', { name: 'Both doc' })).toHaveCount(
            1,
        );
    });
});
