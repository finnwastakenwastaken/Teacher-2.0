import { expect, test } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * Attaching a download used to mean picking from a dropdown of every
 * unattached file; it is a dialog now, the way inserting media in the editor
 * always was.
 *
 * Worth a browser test rather than a unit one for the usual reason: what can
 * go wrong here is a dialog that does not open, a selection that does not
 * stick, or an attach whose Inertia visit remounts the page underneath it —
 * none of which is visible from the server, and none of which a DOM simulator
 * would reproduce faithfully.
 */
test('a download is attached through the picker dialog', async ({ page }) => {
    await useAdminSession(page);
    await page.goto('/admin/topics');

    // Reach the editor the way the owner does, through the tree. A row is
    // found by its drag handle, because that is the only thing on it carrying
    // the page's title in an accessible name — the title itself is plain text,
    // and every row's link says "Bewerken".
    // `.last()` is the innermost match, and it is required rather than tidy:
    // these lists nest, so once there is more than one top-level topic the
    // topic's own row is a listitem too, and it contains this handle along
    // with every "Bewerken" in its subtree. The ancestor comes first in
    // document order, so the last match is the page's own row. The same
    // nesting is what caused the drop-target bug ordering.spec.ts covers.
    await page
        .getByRole('listitem')
        .filter({
            has: page.getByRole('button', { name: 'Verplaatsen: Page A' }),
        })
        .last()
        .getByRole('link', { name: 'Bewerken' })
        .click();

    await expect(page).toHaveURL(/\/admin\/pages\/\d+\/edit/);

    const attached = page.getByText('Werkblad uit de test');
    await expect(attached).toHaveCount(0);

    await page.getByRole('button', { name: 'Bestand kiezen' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    // The list is searchable, which is the point of replacing the dropdown.
    await dialog.getByLabel('Zoeken op naam').fill('open-handout');

    await dialog.getByRole('button', { name: /open-handout/ }).click();

    // Choosing reveals the label field and enables the confirm button; before
    // a choice it is disabled, which is what stops an empty attachment.
    const add = dialog.getByRole('button', { name: 'Toevoegen' });
    await expect(add).toBeEnabled();

    await dialog.getByLabel('Naam op de pagina').fill('Werkblad uit de test');
    await add.click();

    await expect(dialog).toBeHidden();
    await expect(attached.first()).toBeVisible();

    // It is a real attachment, not just a row drawn in the browser — and the
    // attach visit sets preserveState, so a remount here would be a bug of its
    // own rather than a cosmetic one.
    await page.reload();
    await expect(attached.first()).toBeVisible();
});
