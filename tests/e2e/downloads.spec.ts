import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { useAdminSession } from './support/admin';

/** The alt text of the one image fixture, shared with image-picker.spec.ts. */
const IMAGE_ALT = 'Een blauw vlak van 400 bij 300';

/**
 * Reach a fixture page's editor the way the owner does, through the tree.
 *
 * A row is found by its drag handle, because that is the only thing on it
 * carrying the page's title in an accessible name — the title itself is plain
 * text, and every row's link says "Bewerken".
 *
 * `.last()` is the innermost match, and it is required rather than tidy: these
 * lists nest, so once there is more than one top-level topic the topic's own
 * row is a listitem too, and it contains this handle along with every
 * "Bewerken" in its subtree. The ancestor comes first in document order, so
 * the last match is the page's own row. The same nesting is what caused the
 * drop-target bug ordering.spec.ts covers.
 */
async function openPageEditor(page: Page, title: string) {
    await page.goto('/admin/topics');

    await page
        .getByRole('listitem')
        .filter({
            has: page.getByRole('button', { name: `Verplaatsen: ${title}` }),
        })
        .last()
        .getByRole('link', { name: 'Bewerken' })
        .click();

    await expect(page).toHaveURL(/\/admin\/pages\/\d+\/edit/);
}

/**
 * Attaching a download is a search dialog, not a dropdown of every unattached
 * file. Worth a browser test because the failure modes — a dialog that never
 * opens, a selection that doesn't stick, an attach whose Inertia visit
 * remounts the page underneath it — are invisible from the server.
 */
test('a download is attached through the picker dialog', async ({ page }) => {
    await useAdminSession(page);
    await openPageEditor(page, 'Page A');

    const attached = page.getByText('Werkblad uit de test');
    await expect(attached).toHaveCount(0);

    await page.getByRole('button', { name: 'Kies uit de bibliotheek' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    await dialog.getByLabel('Zoeken op naam').fill('open-handout');

    await dialog.getByRole('button', { name: /open-handout/ }).click();

    // Disabled until a choice is made, which is what stops an empty attachment.
    const add = dialog.getByRole('button', { name: 'Toevoegen' });
    await expect(add).toBeEnabled();

    await dialog.getByLabel('Naam op de pagina').fill('Werkblad uit de test');
    await add.click();

    await expect(dialog).toBeHidden();
    await expect(attached.first()).toBeVisible();

    // Confirms it's a real attachment, not just a row drawn client-side.
    await page.reload();
    await expect(attached.first()).toBeVisible();
});

/**
 * A poster, a scanned worksheet or a diagram can be handed out.
 *
 * The PHP suite already asserts the row, the publication and the tally; what
 * it cannot see is the half this test is for — that the dialog offers the
 * image library at all, that switching to it drops a choice made in the other
 * one, and that the attachment comes back drawn as a picture rather than as a
 * generic file icon. A regression in any of those leaves every server-side
 * assertion green.
 */
test('an image is offered as a download through the same dialog', async ({
    page,
}) => {
    await useAdminSession(page);
    await openPageEditor(page, 'Page B');

    const attached = page.getByText('Poster uit de test');
    await expect(attached).toHaveCount(0);

    await page.getByRole('button', { name: 'Kies uit de bibliotheek' }).click();

    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();

    // The dialog opens on documents and videos; the image library is a switch
    // away, because a document is recognised by its name and a picture by
    // looking at it.
    await dialog.getByRole('radio', { name: 'Afbeeldingen' }).click();

    const thumbnail = dialog.getByAltText(IMAGE_ALT);
    await expect(thumbnail).toBeVisible();

    await thumbnail.click();
    await dialog.getByLabel('Naam op de pagina').fill('Poster uit de test');
    await dialog.getByRole('button', { name: 'Toevoegen' }).click();

    await expect(dialog).toBeHidden();

    await page.reload();
    await expect(attached.first()).toBeVisible();

    // Drawn as the picture it is. The row sends a thumbnail URL only for an
    // offered image, so this is also the assertion that the attachment kept
    // its library.
    await expect(
        page
            .getByRole('listitem')
            .filter({ has: page.getByText('Poster uit de test') })
            .locator('img'),
    ).toBeVisible();

    // And a student gets it: the same picture, on the public page, in the
    // downloads section rather than as an embed.
    await page.goto('/e2e/ordering/page-b');
    await expect(
        page.getByRole('link', { name: /Poster uit de test/ }),
    ).toBeVisible();
});

/**
 * A 24×16 JPEG, built here rather than committed as a fixture.
 *
 * It has to be a real one: the library a file lands in is decided by sniffing
 * the assembled bytes, and App\Services\ImageOptimiser then decodes it. A
 * hand-written placeholder would be refused for the right reason and fail this
 * test for the wrong one.
 */
// prettier-ignore
const JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDABQODxIPDRQSEBIXFRQYHjIhHhwcHj0sLiQySUBMS0dARkVQWnNiUFVtVkVGZIhlbXd7gYKBTmCNl4x9lnN+gXz/2wBDARUXFx4aHjshITt8U0ZTfHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHz/wAARCAAQABgDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAT/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFgEBAQEAAAAAAAAAAAAAAAAAAAME/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AiATYgAH/2Q==';

const JPEG = Buffer.from(JPEG_BASE64, 'base64');

/**
 * Dropping a photo into the downloads section attaches it.
 *
 * This is the complaint the whole feature came from: the owner dropped a JPEG
 * here, a toast told them an image cannot be a download, the file landed in
 * the media library, and *nothing was attached to the page*. Nothing on the
 * server could see that — the upload succeeded, and the attach request was
 * simply never made.
 *
 * Conversion is not the complaint and is not undone here: the file is still
 * WebP by the time it is stored, because a photo off a recent phone is HEIC
 * and no browser renders it. What changed is that the attachment can name the
 * `images` row it produced.
 */
test('a photo dropped into the downloads section is attached, not just filed', async ({
    page,
}) => {
    /*
     * This spec is the only one here that puts real bytes through the real
     * pipeline — begin/chunk/complete, an Imagick decode and re-encode to
     * WebP, then the attach — rather than attaching something already seeded.
     * That is why it, and only it, caught a storage-permission failure that
     * every other spec was blind to.
     *
     * It failed four CI runs in a row and was twice misdiagnosed as slowness:
     * first test.slow() (which raises the *test* budget and leaves an
     * assertion's own timeout alone), then a 45s cap (which it then spent in
     * full). Both were wrong. The upload was failing outright — `e2e:seed` ran
     * as root and left the month's image directory owned by root, so PHP-FPM
     * could not write into it — and the row the test waits for was never
     * coming. If this fails again, read the uploader's own status in the
     * captured page before touching a timeout: it reports "Mislukt" and the
     * reason, which is the answer the timeouts were hiding.
     */

    await useAdminSession(page);
    await openPageEditor(page, 'Page C');

    // Scoped by the heading rather than by an accessible name: the section is
    // a plain <section>, so it carries no role of its own.
    const downloads = page.locator('section').filter({
        has: page.getByRole('heading', { level: 2, name: 'Downloads' }),
    });
    const uploader = downloads.getByRole('button', {
        name: 'Bestanden kiezen',
    });

    // The visible button is the control; the input behind it only carries the
    // picker, so the file goes to the input and the button is what proves the
    // affordance is there at all.
    await expect(uploader).toBeVisible();
    await downloads.locator('input[type="file"]').setInputFiles({
        name: 'handout.jpg',
        mimeType: 'image/jpeg',
        buffer: JPEG,
    });

    // Every image needs alt text before a byte is sent — enforced by the
    // database, the service and the Form Request alike.
    const altDialog = page.getByRole('dialog');
    await expect(altDialog).toBeVisible();
    await altDialog.getByLabel('handout.jpg').fill('Een groen vlak');
    await altDialog.getByRole('button', { name: 'Uploaden starten' }).click();
    await expect(altDialog).toBeHidden();

    // The row the owner used to never get. Its name is the corrected one:
    // conversion rewrites the extension, because that is what the file a
    // student saves is called.
    const attached = page
        .getByRole('listitem')
        .filter({ has: page.getByText('handout.webp') });

    await expect(attached).toBeVisible({ timeout: 15000 });

    await page.reload();
    await expect(
        page
            .getByRole('listitem')
            .filter({ has: page.getByText('handout.webp') }),
    ).toBeVisible();

    await page.goto('/e2e/ordering/page-c');
    await expect(
        page.getByRole('link', { name: /handout\.webp/ }),
    ).toBeVisible();
});
