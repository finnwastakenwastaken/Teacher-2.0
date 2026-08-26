import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The regression this runner exists for: a one-item list nested inside a
 * larger one used to render a drag handle and register as a drop target,
 * swallowing drops meant for the list around it — `handleDragEnd` found the
 * id in neither list and silently did nothing. Invisible to the PHP suite
 * (nothing reaches the server) and to jsdom (no layout engine, so dnd-kit's
 * rectangle-based hit testing sees only zeroes).
 */
test.describe('drag-and-drop ordering', () => {
    test.beforeEach(async ({ page }) => {
        await useAdminSession(page);
        await page.goto('/admin/topics');
    });

    test('a list of one renders no drag handle', async ({ page }) => {
        // Goes first because it's the only half that can wait for anything —
        // an absence asserted before React renders proves nothing.
        for (const title of ['Page A', 'Page B', 'Page C']) {
            await expect(
                page.getByRole('button', { name: `Verplaatsen: ${title}` }),
            ).toBeVisible();
        }

        // ...while topic "Solo", which holds exactly one page, has none.
        await expect(
            page.getByRole('button', { name: 'Verplaatsen: Only child' }),
        ).toHaveCount(0);
    });

    test('the keyboard reorders, and the order survives a reload', async ({
        page,
    }) => {
        const order = async () =>
            page
                .getByRole('button', { name: /^Verplaatsen: Page [ABC]$/ })
                .evaluateAll((handles) =>
                    handles.map((handle) =>
                        (handle.getAttribute('aria-label') ?? '').replace(
                            'Verplaatsen: ',
                            '',
                        ),
                    ),
                );

        // evaluateAll doesn't wait for elements — resolves to [] before React
        // renders rather than retrying — so this polls.
        await expect.poll(order).toEqual(['Page A', 'Page B', 'Page C']);

        // Space picks up, arrow moves, space drops — the keyboard sensor is
        // not optional in this project.
        //
        // Each key waits for the announcement the last one caused: dnd-kit
        // measures droppable rectangles only after the drag starts, so an
        // arrow pressed in the same tick finds nothing to move to and the
        // reorder silently no-ops (`handleDragEnd` returns early on
        // `active.id === over.id`) — the exact bug this file exists for,
        // reached from the other end. No human presses three keys in one
        // frame; the test shouldn't either.
        //
        // Waits for "staat nu op" rather than "opgepakt": the live region
        // holds one message at a time and dnd-kit overwrites it as soon as it
        // knows what the item is over — which is also proof the rectangles
        // have been measured, the precondition the next arrow key needs.
        const announcement = page
            .getByRole('status')
            .filter({ hasText: 'Page A' });

        const handle = page.getByRole('button', {
            name: 'Verplaatsen: Page A',
        });
        await handle.focus();
        await expect(handle).toBeFocused();

        await page.keyboard.press('Space');
        await expect(announcement).toContainText(
            'Page A staat nu op plek 1 van 3.',
        );

        await page.keyboard.press('ArrowDown');
        await expect(announcement).toContainText(
            'Page A staat nu op plek 2 van 3.',
        );

        await page.keyboard.press('Space');
        await expect(announcement).toContainText(
            'Page A neergezet op plek 2 van 3.',
        );

        await expect(async () => {
            expect(await order()).toEqual(['Page B', 'Page A', 'Page C']);
        }).toPass();

        await page.reload();
        await expect.poll(order).toEqual(['Page B', 'Page A', 'Page C']);
    });

    test('reordering leaves no redirect behind, because order is not a URL', async ({
        page,
    }) => {
        // A path-changing move would write a slug redirect; reordering must not.
        const response = await page.request.get('/e2e/ordering/page-a', {
            maxRedirects: 0,
        });

        expect(response.status()).toBe(200);
    });
});
