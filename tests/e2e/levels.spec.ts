import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * Education levels via `/admin/levels`. Covers what an HTTP-layer test
 * can't: the merge flow's UI state machine (a level "in use" swaps its
 * delete button for a target picker instead of firing straight away) and the
 * consequence of a merge on the *public* page a student reads.
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

        // Never attached to a download, so the plain delete path applies (see
        // the merge spec below for the other one).
        //
        // The guard is a real dialog, not the browser's. This used to accept a
        // native confirm() via page.once('dialog'); when the dialog became a
        // React one that handler simply never fired — the level was never
        // deleted, yet the spec still passed, because Radix marks the rest of
        // the page aria-hidden while a modal is open, so getByRole found no
        // handle and toHaveCount(0) was satisfied by the dialog being *open*.
        // Asserting the row is gone is therefore not enough on its own; the
        // confirming click has to be scoped to the dialog, because the row's
        // own button carries the same label.
        const confirmation = page.getByRole('alertdialog');

        await page
            .getByRole('listitem')
            .filter({ has: handle })
            .getByRole('button', { name: 'Verwijderen' })
            .click();

        await expect(confirmation).toBeVisible();
        await confirmation.getByRole('button', { name: 'Verwijderen' }).click();

        await expect(confirmation).toHaveCount(0);
        await expect(handle).toHaveCount(0);
    });

    /**
     * Merging is the only way to retire a level in use — a plain delete would
     * strip the tag off every download carrying it. The fixture includes a
     * download already tagged with *both* source and target — the case
     * The technical reference specifically calls out; merging it must land on one tag
     * rather than collide with the pivot's unique
     * `[page_download_id, education_level_id]` pair (a bug there either 500s
     * mid-transaction or silently drops the tag).
     *
     * Not self-restoring: the merge deletes E2E-Bron outright, so a second
     * run needs `e2e:seed` in between, same as ordering.spec.ts.
     */
    test('merging a level retires it and re-tags its downloads, including one that already carried both', async ({
        page,
    }) => {
        // Read off the public page: a download tagged for two tracks lists
        // under both headings by design (the technical reference, "page_downloads"), so
        // "Both doc" appears twice.
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

        // In use, so this swaps into the merge picker instead of deleting
        // straight away — no confirm() dialog on this path.
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

        // E2E-Doel now carries both downloads, each exactly once.
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
