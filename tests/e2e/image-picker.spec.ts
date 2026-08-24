import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The banner and branding pickers search the server instead of being handed
 * the whole library.
 *
 * Worth a browser test rather than a controller one because the server half
 * is already covered — MediaSearchTest asserts the endpoint — and what can
 * actually break is the half no request can see: a dialog whose search never
 * fires, results that arrive after it decided it was empty, or a choice that
 * updates the hidden input without updating the thumbnail beside it. That
 * thumbnail is the only feedback the owner gets that they picked what they
 * meant to.
 *
 * It also pins *when* the search runs. Three of these render on the settings
 * screen, so searching on mount would be three requests for a library nobody
 * has asked to see; the effect is gated on the dialog being open, and nothing
 * else would notice if that gate were removed.
 *
 * Nothing here is saved: the form is never submitted, so the chosen logo
 * lives only in the component's state and the site is left as it was found.
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

    // The results come from the server, so this asserts a request happened
    // and its answer is on screen — not that a prop was filtered in the
    // browser.
    await expect(dialog.getByAltText(ALT)).toBeVisible();
    expect(searches.length).toBeGreaterThan(0);

    await dialog.getByAltText(ALT).click();
    await expect(dialog).toBeHidden();

    // Chosen, and drawn. The field keeps its own copy of what was picked,
    // because the only thing the server sent was whatever the *stored*
    // setting pointed at.
    await expect(logo.getByAltText(ALT)).toBeVisible();

    // Typing narrows it on the server too, and a term matching nothing must
    // empty the grid rather than leave the previous results standing.
    await logo
        .getByRole('button', { name: /Logo: (kiezen|vervangen)/ })
        .click();
    await expect(dialog).toBeVisible();

    await dialog
        .getByLabel('Zoek een afbeelding')
        .fill('een-zoekterm-die-niets-oplevert-12345');

    await expect(dialog.getByAltText(ALT)).toBeHidden();
});
