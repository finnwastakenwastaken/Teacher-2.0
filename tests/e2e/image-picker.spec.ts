import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The banner and branding pickers search the server rather than being handed
 * the whole library. MediaSearchTest already covers the endpoint, so this
 * covers what only a browser shows: a dialog whose search never fires,
 * results that arrive after it's decided it's empty, or a choice that updates
 * the hidden input without updating the thumbnail.
 *
 * Also pins *when* search runs: three pickers render on the settings screen,
 * so the fetch is gated on the dialog opening, not on mount — otherwise
 * opening the screen alone would fire three unwanted requests.
 *
 * Nothing here is saved — the form is never submitted.
 */
const ALT = 'Een blauw vlak van 400 bij 300';

test('the branding picker searches the server and shows what was chosen', async ({
    page,
}) => {
    await useAdminSession(page);

    const searches: string[] = [];
    page.on('request', (request) => {
        if (request.url().includes('/admin/media/search/image-options')) {
            searches.push(request.url());
        }
    });

    await page.goto('/admin/instellingen');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Instellingen' }),
    ).toBeVisible();

    // Nothing fetched yet, with three pickers on screen.
    expect(searches).toHaveLength(0);

    const logo = page.getByRole('group', { name: 'Logo', exact: true });
    const dialog = page.getByRole('dialog');

    await logo
        .getByRole('button', { name: /Logo: (kiezen|vervangen)/ })
        .click();
    await expect(dialog).toBeVisible();

    // Asserts a request happened and its answer rendered, not that a prop
    // was filtered client-side.
    await expect(dialog.getByAltText(ALT)).toBeVisible();
    expect(searches.length).toBeGreaterThan(0);

    await dialog.getByAltText(ALT).click();
    await expect(dialog).toBeHidden();

    // The field keeps its own copy of the pick — the server only ever sent
    // whatever the *stored* setting pointed at.
    await expect(logo.getByAltText(ALT)).toBeVisible();

    // A term matching nothing must empty the grid, not leave stale results.
    await logo
        .getByRole('button', { name: /Logo: (kiezen|vervangen)/ })
        .click();
    await expect(dialog).toBeVisible();

    await dialog
        .getByLabel('Zoek een afbeelding')
        .fill('een-zoekterm-die-niets-oplevert-12345');

    await expect(dialog.getByAltText(ALT)).toBeHidden();
});
