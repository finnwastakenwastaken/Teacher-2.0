import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The one confirmation dialog, driven through the levels screen.
 *
 * `components/ui/confirm-dialog.tsx` replaced eight native `window.confirm()`
 * calls. Radix ships a separate AlertDialog package that was deliberately not
 * added — a new dependency is an architectural decision here — so the three
 * things AlertDialog would have given are implemented by hand on the plain
 * Dialog primitive. **Hand-implemented means nothing else guards them**, and
 * all three are the kind of thing that regresses without a visible symptom:
 * the dialog still opens, still looks right, and quietly stops protecting
 * anything.
 *
 * So this spec exists for those three and nothing else:
 *
 *   1. it is announced as an alertdialog, not as a panel that happens to be open;
 *   2. clicking outside does not dismiss it, while Escape still cancels;
 *   3. focus lands on Cancel, so a stray Enter or Space destroys nothing.
 *
 * The levels screen is the vehicle because a level with no downloads attached
 * takes the plain delete path, and because the whole exercise is reversible in
 * two clicks. It uses the same throwaway level `levels.spec.ts` does
 * (`SeedBrowserTestFixtures::LEVEL_PROBE_NAME`), which the seeder clears on
 * every run — so a re-run needs `e2e:seed` between the two, exactly as the
 * ordering and levels specs already document.
 */

const PROBE = 'E2E-Nieuw';

test.describe('the confirmation dialog', () => {
    /*
     * Created only when it is not already there. Two of the three tests below
     * cancel, which is the whole point of them, so they leave the level
     * standing — and levels are unique by name, so an unconditional create
     * would fail the second test on a validation error that has nothing to do
     * with what it is testing.
     */
    test.beforeEach(async ({ page }) => {
        await useAdminSession(page);
        await page.goto('/admin/levels');

        const handle = page.getByRole('button', {
            name: `Verplaatsen: ${PROBE}`,
        });

        if ((await handle.count()) === 0) {
            await page.getByLabel('Nieuw niveau').fill(PROBE);
            await page.getByRole('button', { name: 'Toevoegen' }).click();
        }

        await expect(handle).toBeVisible();
    });

    test('it asks as an alert, and opens focused on Cancel so a stray Enter cancels', async ({
        page,
    }) => {
        await openConfirmation(page);

        // 1. Announced as requiring an answer. `role="alertdialog"` is a single
        //    prop on DialogContent and deleting it is silent — the dialog looks
        //    identical and is merely announced as an ordinary dialog.
        const confirmation = page.getByRole('alertdialog');
        await expect(confirmation).toBeVisible();

        // The description is the sentence that was the entire native confirm();
        // the title is new, and exists because a native confirm had nowhere to
        // put one but the site's own origin.
        await expect(confirmation).toContainText('Niveau verwijderen?');
        await expect(confirmation).toContainText(PROBE);

        // 3. Focus starts on the way out, not on the destructive button. This
        //    is `onOpenAutoFocus` being prevented; without it Radix focuses the
        //    first tabbable element and Enter deletes.
        await expect(
            confirmation.getByRole('button', { name: 'Annuleren' }),
        ).toBeFocused();

        // Which is what makes this safe: the key most likely to be pressed by
        // someone who was mid-flow when the dialog appeared cancels.
        await page.keyboard.press('Enter');
        await expect(confirmation).toHaveCount(0);

        await expectStillThere(page);
    });

    test('a click outside does not dismiss it, and Escape does', async ({
        page,
    }) => {
        await openConfirmation(page);

        const confirmation = page.getByRole('alertdialog');
        await expect(confirmation).toBeVisible();

        // Top-left corner is over the overlay and nowhere near the centred
        // panel. `onInteractOutside` is prevented, so this is ignored — an
        // ordinary Dialog would have closed here.
        await page.mouse.click(5, 5);
        await expect(confirmation).toBeVisible();

        // Worth knowing, and the reason the Enter assertion lives in the test
        // above rather than after this click: the outside click moves focus off
        // Annuleren and onto the dialog container. Enter then does nothing at
        // all — still safe, but safe for a different reason, and asserting
        // "Enter cancels" here would have been asserting something untrue.
        await page.keyboard.press('Escape');
        await expect(confirmation).toHaveCount(0);

        await expectStillThere(page);
    });

    test('confirming deletes, and this is what proves the rest were real refusals', async ({
        page,
    }) => {
        await openConfirmation(page);

        const confirmation = page.getByRole('alertdialog');

        // Scoped to the dialog: the row's own button carries the same label,
        // and an unscoped locator would resolve to two elements.
        await confirmation.getByRole('button', { name: 'Verwijderen' }).click();

        await expect(confirmation).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: `Verplaatsen: ${PROBE}` }),
        ).toHaveCount(0);

        // Asserting the row is gone is deliberately not the only assertion.
        // While a Radix modal is open the rest of the page is aria-hidden, so
        // getByRole finds nothing and a "the row disappeared" check passes for
        // an open dialog just as happily as for a completed delete. That is not
        // hypothetical: it is exactly how levels.spec.ts went on passing after
        // its native-confirm handler stopped firing, while the level it claimed
        // to delete sat in the database. Reloading is what tells the two apart.
        await page.reload();
        await expect(
            page.getByRole('button', { name: `Verplaatsen: ${PROBE}` }),
        ).toHaveCount(0);
    });
});

/** Press the row's delete button, which is what raises the confirmation. */
async function openConfirmation(page: Page) {
    await page
        .getByRole('listitem')
        .filter({
            has: page.getByRole('button', { name: `Verplaatsen: ${PROBE}` }),
        })
        .getByRole('button', { name: 'Verwijderen' })
        .click();
}

/**
 * The level survived. Checked after a reload rather than in place, for the
 * reason spelled out in the last test: with no modal open the aria-hidden
 * confound is gone, but reloading also proves no request was sent rather than
 * only that the list has not re-rendered yet.
 */
async function expectStillThere(page: Page) {
    await page.reload();
    await expect(
        page.getByRole('button', { name: `Verplaatsen: ${PROBE}` }),
    ).toBeVisible();
}
