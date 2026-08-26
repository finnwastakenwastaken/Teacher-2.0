import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * Two things a request cannot see.
 *
 * The PHP suite already asserts both payloads — that the dashboard reports a
 * page carrying an unpublished concept, and that every uploader is handed the
 * accepted formats. What it cannot answer is whether either reached the
 * screen, and the accepted-format list has a second failure mode a controller
 * test is blind to: it is a run of twenty extensions inside a drop zone, and
 * the obvious way to draw it pushes a phone sideways.
 *
 * The concept half also has to be *made* rather than seeded, because the thing
 * being tested is the whole loop — the editor's autosave writes a concept, and
 * two screens the owner has not opened say so afterwards. A fixture with a
 * draft column already set would prove only the rendering.
 */

const PAGE_TITLE = 'Open page';

/**
 * The page's own row in the content tree.
 *
 * `.last()` is required rather than tidy: these lists nest, so the topic's row
 * is a listitem containing this one. Filtering on the drag handle rather than
 * on the text is the same reason downloads.spec.ts does — it names exactly one
 * item, where a text filter also matches every ancestor. The ancestor comes
 * first in document order, so the last match is the page.
 */
function pageRow(page: Page): Locator {
    return page
        .getByRole('listitem')
        .filter({
            has: page.getByRole('button', {
                name: `Verplaatsen: ${PAGE_TITLE}`,
            }),
        })
        .last();
}

async function openPageEditor(page: Page) {
    await page.goto('/admin/topics');
    await pageRow(page).getByRole('link', { name: 'Bewerken' }).click();
    await expect(page).toHaveURL(/\/admin\/pages\/\d+\/edit/);
}

test.describe('an unpublished concept is visible from outside the page', () => {
    test('typing in the editor puts the page on the dashboard and badges it in the tree', async ({
        page,
    }) => {
        await useAdminSession(page);
        await page.goto('/admin/topics');

        // Nothing is holding a concept before this spec makes one. Scoped to
        // the row rather than the page, so an unrelated fixture that grows a
        // draft later cannot make this pass or fail for the wrong reason.
        await expect(pageRow(page).getByText('Concept klaar')).toHaveCount(0);

        await openPageEditor(page);

        const body = page.locator('.ProseMirror');

        await body.click();
        await body.pressSequentially('Een concept van de docent.');

        // The autosave is debounced on the document going quiet, so this waits
        // for the editor's own report rather than for a duration.
        await expect(page.getByText(/Concept bewaard om/)).toBeVisible({
            timeout: 15_000,
        });

        // The dashboard, which the owner has not touched.
        await page.goto('/dashboard');

        const card = page
            .locator('[data-slot="card"]')
            .filter({
                has: page.getByRole('heading', {
                    name: 'Niet-gepubliceerde concepten',
                }),
            })
            .first();

        await expect(card).toBeVisible();
        await expect(
            card.getByRole('link', { name: PAGE_TITLE }),
        ).toBeVisible();

        // And the tree, beside the page it belongs to.
        await page.goto('/admin/topics');
        await expect(pageRow(page).getByText('Concept klaar')).toBeVisible();

        // Put the fixture back. The specs share one site and a stray concept
        // would make the "before" assertion above fail on the next run — the
        // sort of failure that reads as a product bug and is not one.
        await openPageEditor(page);
        await page
            .getByRole('button', { name: 'Terug naar de gepubliceerde versie' })
            .click();
        await page
            .getByRole('alertdialog')
            .getByRole('button', { name: 'Concept weggooien' })
            .click();

        await page.goto('/admin/topics');
        await expect(pageRow(page).getByText('Concept klaar')).toHaveCount(0);
    });
});

test.describe('the uploader says what it accepts', () => {
    test('the video formats are on screen before a file is chosen', async ({
        page,
    }) => {
        await useAdminSession(page);
        await page.goto('/admin/media');

        // The list the server actually judges an upload against, rendered
        // from App\Support\MediaFormats. Naming the extensions here rather
        // than reading them back from the page is deliberate: this is the
        // assertion that would fail if the derivation started lying.
        await expect(page.getByText('Video’s: mp4, webm, mov')).toBeVisible();
        await expect(page.getByText(/Documenten: pdf, .*zip/)).toBeVisible();
        await expect(
            page.getByText(/Afbeeldingen: jpg, jpeg, .*heif/),
        ).toBeVisible();
    });

    test('twenty extensions do not push a phone sideways', async ({ page }) => {
        await useAdminSession(page);
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/admin/media');

        await expect(page.getByText('Video’s: mp4, webm, mov')).toBeVisible();

        expect(await horizontalOverflow(page)).toBe(0);
    });

    test('the drop zone inside the page editor says it too', async ({
        page,
    }) => {
        await useAdminSession(page);
        await page.setViewportSize({ width: 375, height: 812 });

        await openPageEditor(page);

        // The downloads section's uploader is compact and sits inside a
        // column, which is where a long list is most likely to break out.
        await expect(
            page.getByText('Video’s: mp4, webm, mov').first(),
        ).toBeVisible();

        expect(await horizontalOverflow(page)).toBe(0);
    });
});

function horizontalOverflow(page: Page): Promise<number> {
    return page.evaluate(
        () =>
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
    );
}
