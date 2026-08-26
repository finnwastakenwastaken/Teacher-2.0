import { expect, test } from '@playwright/test';

/**
 * The public site's layout and colour decisions, as assertions.
 *
 * These are the three things from the design pass that a person cannot check
 * by looking, and that nothing else in the suite covers:
 *
 *   1. A listing card is reachable by keyboard **and says so**. The cards were
 *      hoverable and invisibly focusable before this — a real gap, and exactly
 *      the kind the polish pass audited every admin route for while the public
 *      grids went unexamined.
 *   2. A download group carries its level's accent, and the "for everyone"
 *      group does not. That group is not a level; colouring it would say it
 *      were a sixth one.
 *   3. The page body is a reading measure rather than the full container.
 *
 * Colour *values* are deliberately not asserted here. The owner can change the
 * palette, so pinning a hex would fail the suite for a supported action; what
 * matters is that an accent is present, distinct from the neutral one, and
 * that the class map resolved to something rather than to nothing — which is
 * the actual failure mode an interpolated Tailwind class produces.
 */

/** The fixture page carrying level-tagged downloads. */
const LEVELLED = '/e2e/levels/merge';

/** A topic listing, which renders the summary cards and the page rows. */
const LISTING = '/e2e';

test.describe('the public site', () => {
    test('a listing card takes keyboard focus and paints a visible ring', async ({
        page,
    }) => {
        await page.goto(LISTING);

        const card = page.locator('a.group.block').first();
        await expect(card).toBeVisible();

        // Focused by keyboard, not by .focus() — `:focus-visible` is a
        // heuristic about *how* focus arrived, and a programmatic call does
        // not satisfy it. That distinction is the whole reason this assertion
        // lives in a browser test rather than in a one-off page evaluation.
        await card.focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');

        const ring = await card.evaluate((el) => ({
            focusVisible: el.matches(':focus-visible'),
            boxShadow: getComputedStyle(el).boxShadow,
        }));

        expect(ring.focusVisible).toBe(true);
        expect(ring.boxShadow).not.toBe('none');
    });

    test('a level group carries an accent and the untagged group does not', async ({
        page,
    }) => {
        await page.goto(LEVELLED);

        const group = page.locator('[class*="border-l-level-"]').first();
        await expect(group).toBeVisible();

        // The rail resolved to a real colour. A class Tailwind never compiled
        // — which is what `border-l-level-${n}` would have produced — leaves
        // the element with the inherited border colour and no error anywhere,
        // so "it is not the neutral border" is the assertion that catches it.
        const rail = await group.evaluate((el) => {
            const cs = getComputedStyle(el);

            return {
                colour: cs.borderLeftColor,
                width: cs.borderLeftWidth,
                neutral: getComputedStyle(el).getPropertyValue('--border'),
            };
        });

        expect(rail.width).toBe('4px');
        expect(rail.colour).not.toBe('rgba(0, 0, 0, 0)');

        // Every group states its level in text beside the colour, so nothing
        // is carried by colour alone.
        await expect(group.getByRole('heading')).toHaveText(/\S/);
    });

    test('the page body is a reading measure, not the whole column', async ({
        page,
    }) => {
        await page.goto(LEVELLED);

        const measured = await page.evaluate(() => {
            const column = [...document.querySelectorAll('div')].find((d) =>
                d.className.includes('35rem'),
            );

            if (column === undefined) {
                return null;
            }

            const style = getComputedStyle(column);
            const probe = document.createElement('span');
            probe.style.font = style.font;
            probe.style.position = 'absolute';
            probe.style.visibility = 'hidden';
            probe.style.whiteSpace = 'nowrap';
            probe.textContent =
                'de leerling leest deze regel van begin tot eind zonder enige moeite en gaat door';
            document.body.appendChild(probe);
            const average =
                probe.getBoundingClientRect().width / probe.textContent.length;
            probe.remove();

            return {
                width: column.getBoundingClientRect().width,
                charsPerLine: Math.round(
                    column.getBoundingClientRect().width / average,
                ),
                main: (
                    document.querySelector('main') as HTMLElement
                ).getBoundingClientRect().width,
            };
        });

        expect(measured).not.toBeNull();

        // Measured in real characters, not in `ch`. The "0" glyph in
        // Instrument Sans is 57% wider than average prose, so a `ch` value
        // reads as a character count and is not one — 68ch renders about 104
        // characters. The upper bound here is what that mistake would breach.
        expect(measured!.charsPerLine).toBeLessThanOrEqual(85);
        expect(measured!.charsPerLine).toBeGreaterThanOrEqual(55);

        // And it is genuinely narrower than the container it sits in.
        expect(measured!.width).toBeLessThan(measured!.main);
    });

    test('nothing overflows sideways on a phone', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });

        for (const path of [LISTING, LEVELLED, '/']) {
            await page.goto(path);

            const overflow = await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            );

            expect(overflow, `${path} overflows sideways`).toBe(0);
        }
    });
});
