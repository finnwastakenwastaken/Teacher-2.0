import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The regression this whole runner exists for.
 *
 * A list holding exactly one item, nested inside a list holding several, used
 * to render a drag handle on the lone row and register it as a drop target —
 * so it swallowed the drop meant for the list around it, `handleDragEnd` found
 * the id in neither list, and the reorder silently did nothing. 270 passing
 * PHP tests could not see it, because nothing about it reaches the server.
 *
 * It cannot be seen in jsdom either: dnd-kit decides what you dropped on from
 * rectangles, and jsdom has no layout, so every rectangle is zero. This is why
 * the runner drives a real browser.
 */
test.describe('drag-and-drop ordering', () => {
    test.beforeEach(async ({ page }) => {
        await useAdminSession(page);
        await page.goto('/admin/topics');
    });

    test('a list of one renders no drag handle', async ({ page }) => {
        // The rows in a list of several all have a handle. This goes first
        // because it is the only half that can wait for anything: an absence
        // asserted before React has rendered is an absence of the whole page.
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

        // evaluateAll does not wait for elements: before React renders it
        // resolves to an empty array rather than retrying, so this polls.
        await expect.poll(order).toEqual(['Page A', 'Page B', 'Page C']);

        // Space picks up, arrow moves, space drops — the keyboard sensor is
        // not optional in this project, so it is what the test uses.
        //
        // Each key waits for the announcement the last one caused, and that is
        // load-bearing rather than tidy: dnd-kit measures the droppable
        // rectangles after a drag starts, so an arrow pressed in the same tick
        // finds nothing to move to, the second space drops the item where it
        // began, and `handleDragEnd` returns early on `active.id === over.id`.
        // The reorder then silently does nothing — which is the exact symptom
        // of the bug this file exists for, arrived at from the other end. No
        // human types three keys inside a frame; the test should not either.
        //
        // It also asserts the Dutch announcements, which are the only thing a
        // screen-reader user gets out of this control.
        //
        // The region holds one message at a time, so "opgepakt" is not what to
        // wait for: dnd-kit overwrites it with the first "staat nu op" as soon
        // as it knows what the item is over. That is the better signal anyway,
        // because knowing what it is over means the rectangles have been
        // measured — which is exactly the precondition the arrow key needs.
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
        // A move that changed a path would have written a slug redirect, and
        // the page would answer 301 from its old address. Reordering must not.
        const response = await page.request.get('/e2e/ordering/page-a', {
            maxRedirects: 0,
        });

        expect(response.status()).toBe(200);
    });
});
