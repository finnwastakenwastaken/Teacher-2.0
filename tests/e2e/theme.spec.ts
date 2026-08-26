import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

import { useAdminSession } from './support/admin';

/**
 * The palette editor at `/admin/kleuren`, and its contrast gate.
 *
 * This spec exists because the gate cannot be tested anywhere else. It is
 * measured in a browser on purpose — several semantic roles are a color-mix()
 * of a palette entry toward or away from the surface, and the project's rule
 * (the technical reference) is that contrast is measured against the real rendered
 * stack rather than estimated from the palette, because the pass that
 * estimated was wrong about five roles at once. The consequence is that the
 * PHP suite can prove a colour is a colour and can prove where the override
 * lands, and can prove nothing at all about whether the site stays readable.
 *
 * So the two things asserted here are the two things only a browser knows:
 *
 * - A palette entry that breaks a pair is refused, by name, in *both* themes.
 *   The dark half matters more than it looks: `--link` is a color-mix() of
 *   `--p-blue`, so a dark blue fails on a dark card while the light theme
 *   still passes. A gate that only checked the theme the admin happens to be
 *   wearing would wave that through.
 * - A colour that clears the gate reaches a visitor's first paint, from the
 *   <style> block app.blade.php writes — not from a repaint after hydration.
 *
 * A very dark blue rather than a very light one, deliberately: primary
 * buttons carry a dark navy label (white on the brand blue is 3.2:1 and fails
 * AA, which is why), so making the blue *lighter* improves that pair. Navy on
 * navy is what breaks it.
 */

const SHIPPED_BLUE = '#00a8ff';
const UNREADABLE_BLUE = '#0a1030';
const READABLE_BLUE = '#ff8c00';

type Stamped = Window & { __beforeSave?: true };

/**
 * Press save, and wait for the screen the save replaced.
 *
 * The request is a real PUT — this screen submits through `router.put` rather
 * than the <Form> component, because it has to block on the gate first, so
 * there is no `_method` spoof to match around. What needs care is what happens
 * next: on success the screen reloads the whole document, because the palette
 * is a <style> block from Blade and an Inertia swap would leave every colour
 * on the page saying what it said before. Anything driven at the page while
 * that reload is in flight loses to it, and the error lands on the *following*
 * line looking like anything but a race.
 *
 * Waiting on the reload's response is not enough — the response can arrive
 * before the new document commits. So this stamps the current document and
 * waits for a document without the stamp, which is the reload itself and
 * nothing else. Deliberately not `window.name`, which is one of the few things
 * that survives a navigation and would therefore never clear.
 */
async function save(page: Page) {
    await page.evaluate(() => {
        (window as Stamped).__beforeSave = true;
    });

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().includes('/admin/kleuren') &&
                response.request().method() === 'PUT',
        ),
        page.getByRole('button', { name: 'Opslaan' }).click(),
    ]);

    await page.waitForFunction(() => !(window as Stamped).__beforeSave);
    await page.waitForLoadState('load');
}

/** What the browser reports for `--primary` on the public homepage. */
async function paintedPrimary(page: Page) {
    return page.evaluate(() => {
        const probe = document.createElement('div');
        probe.style.backgroundColor = 'var(--primary)';
        document.body.appendChild(probe);
        const painted = getComputedStyle(probe).backgroundColor;
        probe.remove();

        return painted;
    });
}

test('a colour that breaks a pair is refused, in both themes, before it can be saved', async ({
    page,
}) => {
    await useAdminSession(page);
    await page.goto('/admin/kleuren');

    // The shipped palette clears every pair. This is also the assertion that
    // the measurement is running at all — without it a gate that silently
    // measured nothing would look exactly like a gate that passed.
    await expect(page.getByText(/Alle 20 combinaties/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();

    await page.getByLabel('Blauw', { exact: true }).fill(UNREADABLE_BLUE);

    const failures = page.getByRole('alert');

    await expect(failures).toContainText(
        'Met deze kleuren is de site niet meer goed leesbaar',
    );

    // Named, with the theme and the measured ratio — not a bare refusal. The
    // owner has to be able to act on it.
    await expect(
        failures.getByText(
            /Opschrift op een primaire knop haalt in de lichte modus \d,\d\d:1/,
        ),
    ).toBeVisible();
    await expect(
        failures.getByText(
            /Opschrift op een primaire knop haalt in de donkere modus \d,\d\d:1/,
        ),
    ).toBeVisible();

    // The dark-only one. `--link` is a color-mix() of the brand blue, so this
    // pair comes apart on a dark card while the light theme is still fine.
    await expect(
        failures.getByText(/Een link op een kaart haalt in de donkere modus/),
    ).toBeVisible();

    await expect(page.getByRole('button', { name: 'Opslaan' })).toBeDisabled();

    // And nothing was sent. The disabled button is the visible half of the
    // gate; the submit handler is the half that still holds if a keyboard
    // submit beats a re-render.
    let sent = false;
    page.on('request', (request) => {
        if (
            request.url().includes('/admin/kleuren') &&
            request.method() !== 'GET'
        ) {
            sent = true;
        }
    });

    await page.evaluate(() => {
        document
            .querySelector('form')
            ?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
    });

    await page.waitForTimeout(500);
    expect(sent).toBe(false);
});

test('a colour that clears the gate reaches a visitor on a cold load, and resetting takes it back', async ({
    page,
    browser,
}) => {
    await useAdminSession(page);

    /*
     * The public half is read from a separate, unauthenticated context rather
     * than by navigating the admin's own tab. Two reasons, and both matter:
     * the claim is about what a *visitor* is served, and the palette is one of
     * the few things on this site that is the same for everyone — so a page
     * that had just been logged in would be proving a weaker statement than
     * the one being made.
     */
    const visitor = await browser.newPage();

    const painted = async () => {
        await visitor.goto('/');

        return paintedPrimary(visitor);
    };

    // Counted by reading the document rather than with a locator: a <style>
    // element renders nothing, and Playwright's text matching is about text a
    // person could read.
    const overrideBlocks = async () =>
        visitor.evaluate(
            () =>
                Array.from(document.querySelectorAll('style')).filter((style) =>
                    style.textContent?.includes('--p-blue'),
                ).length,
        );

    expect(await painted()).toBe('rgb(0, 168, 255)');
    expect(await overrideBlocks()).toBe(0);

    await page.goto('/admin/kleuren');
    await page.getByLabel('Blauw', { exact: true }).fill(READABLE_BLUE);

    await expect(page.getByText(/Alle 20 combinaties/)).toBeVisible();

    await save(page);

    // A document that has never seen this screen: the colour has to come out
    // of Blade, or it is not there at first paint and every page on the site
    // flashes the shipped palette before repainting.
    expect(await painted()).toBe('rgb(255, 140, 0)');
    expect(await overrideBlocks()).toBe(1);

    // `exact`, or this also matches "Diepblauw terugzetten", "Marineblauw
    // terugzetten" and three more — every accessible name here ends the same
    // way and several contain this one.
    await page
        .getByRole('button', { name: 'Blauw terugzetten', exact: true })
        .click();
    await expect(page.getByLabel('Blauw', { exact: true })).toHaveValue(
        SHIPPED_BLUE,
    );

    await save(page);

    // Reset is a delete, so there is no override block left to emit at all —
    // not a block restating the shipped colour.
    expect(await painted()).toBe('rgb(0, 168, 255)');
    expect(await overrideBlocks()).toBe(0);

    await visitor.close();
});
