import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The version history: publish twice, look at the older body, put it back.
 *
 * The PHP suite already pins what gets recorded, what gets pruned and that a
 * restored body republishes its media. What it cannot see is the path a
 * person actually takes — the panel opening, a body being fetched and drawn by
 * the public renderer, the confirmation, and the editor coming back holding
 * something different from what it held a moment ago. Every one of those is a
 * step where the feature can be perfectly correct on the server and useless.
 *
 * The bodies carry a per-run marker. The fixture page keeps whatever history
 * earlier runs left it, capped at ten, so anything positional or literal
 * would be reading somebody else's run — but the *newest* entry is always the
 * body this run published first, whatever came before it.
 */

const HISTORY_PATH = '/e2e/geschiedenis';

/**
 * Reach the fixture page's editor through the tree, the way the owner does.
 * See the note on the same helper in downloads.spec.ts for why the row is
 * found by its drag handle and why `.last()` is required rather than tidy.
 */
async function openHistoryEditor(page: Page) {
    await page.goto('/admin/topics');

    await page
        .getByRole('listitem')
        .filter({
            has: page.getByRole('button', {
                name: 'Verplaatsen: Geschiedenis',
            }),
        })
        .last()
        .getByRole('link', { name: 'Bewerken' })
        .click();

    await expect(page).toHaveURL(/\/admin\/pages\/\d+\/edit/);
}

/**
 * Replace the whole body and publish it.
 *
 * **Waiting on the HTTP response here is not enough, and that cost a run.**
 * `waitForResponse` resolves when the headers arrive, while Inertia's
 * `onSuccess` — which is what clears the editor's dirty flag — runs a tick
 * later. Typing the next body in between meant the flag was set by the
 * keystrokes and then cleared by the previous publish landing, so the second
 * "Opslaan en publiceren" stayed disabled for the rest of the test with the
 * new text plainly on screen.
 *
 * So the barrier is the status line, which is the editor's own account of
 * both halves: it has to say there is something unsaved before the click and
 * has to have gone back to saying there is not after it.
 */
async function publish(page: Page, text: string) {
    const editor = page.getByRole('textbox', { name: 'Pagina-inhoud' });

    await editor.click();
    await page.keyboard.press('ControlOrMeta+a');
    await page.keyboard.type(text);

    await expect(
        page.getByText('Er zijn niet-opgeslagen wijzigingen.'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Opslaan en publiceren' }).click();

    await expect(
        page.getByText('Alle wijzigingen zijn opgeslagen.'),
    ).toBeVisible();
}

test('an older version can be previewed and put back on the site', async ({
    page,
}) => {
    const run = Date.now();
    const older = `Oudere versie ${run}`;
    const newer = `Nieuwere versie ${run}`;

    /*
     * Triples the 30 s budget, and it is needed rather than defensive: this
     * one test is two publishes, a preview, a restore and two public page
     * loads — eleven round trips through the real stack — and splitting it
     * would mean each half setting up the other half's state through the
     * same editor anyway.
     *
     * Worth knowing when this shows up again: an over-budget test reports the
     * *pending* assertion, so it reads exactly like the feature being broken.
     * One run died on a preview that had not been drawn yet and another on a
     * public page that had not been fetched, both with the application
     * behaving perfectly.
     */
    test.slow();

    await useAdminSession(page);
    await openHistoryEditor(page);

    // Two publishes. The first records whatever was on the page before; the
    // second records `older`, which is therefore the newest entry.
    await publish(page, older);
    await publish(page, newer);

    // The site is serving the second one.
    await page.goto(HISTORY_PATH);
    await expect(page.getByText(newer)).toBeVisible();

    await openHistoryEditor(page);

    // Collapsed by default: most sessions never need it, and an always-open
    // panel of ten dates under the editor would be noise on every page.
    await expect(page.getByRole('button', { name: 'Bekijken' })).toHaveCount(0);

    await page
        .getByRole('button', { name: 'Versiegeschiedenis tonen' })
        .click();

    // The newest entry, which is `older` — see the note at the top on why
    // this is the only position that means anything across runs.
    await page.getByRole('button', { name: 'Bekijken' }).first().click();

    // Drawn by components/content/rich-text.tsx, the public renderer. The
    // body is fetched on demand — the edit payload carries only timestamps —
    // so this is also the assertion that the fetch happened at all.
    await expect(page.getByText(older)).toBeVisible();

    // Restoring is only offered from the preview. A list of timestamps is not
    // enough to recognise a lesson by, and restoring blind is how the version
    // you had gets lost.
    await page.getByRole('button', { name: 'Terugzetten' }).click();

    const confirmation = page.getByRole('alertdialog');
    await expect(confirmation).toBeVisible();
    await expect(confirmation).toContainText('Deze versie terugzetten?');

    await confirmation
        .getByRole('button', { name: 'Versie terugzetten' })
        .click();

    /*
     * The barrier, and it has to be a change rather than a destination.
     *
     * The restore lands back on the screen it was started from, so waiting on
     * the URL proves nothing at all — it already matches — and the run that
     * did that went on to fetch the public page while the POST was still in
     * flight, reading the body it was about to replace.
     *
     * The editor holding the restored text is the real event, and it is also
     * the claim worth making: `useEditor` reads its document once, so an
     * editor still showing the replaced body while the site serves the
     * restored one is exactly the disagreement this feature exists to end.
     */
    await expect(
        page.getByRole('textbox', { name: 'Pagina-inhoud' }),
    ).toContainText(older);
    await expect(
        page.getByText(/De oude versie staat weer op de site/),
    ).toBeVisible();

    // The consequence, asserted on the public page rather than on a toast:
    // the older body is what a student now gets, and the newer one is gone.
    await page.goto(HISTORY_PATH);
    await expect(page.getByText(older)).toBeVisible();
    await expect(page.getByText(newer)).toHaveCount(0);

    // And the history is append-only: what the restore replaced is now the
    // newest entry, so this is undoable rather than a one-way door.
    await openHistoryEditor(page);
    await page
        .getByRole('button', { name: 'Versiegeschiedenis tonen' })
        .click();
    await page.getByRole('button', { name: 'Bekijken' }).first().click();

    await expect(page.getByText(newer)).toBeVisible();
});
