import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

/**
 * The image the running text flows around.
 *
 * Three things about it were measured by hand when it shipped and had no
 * regression guard, and all three are invisible to the PHP suite because they
 * are layout rather than markup: that the text actually wraps, that the float
 * is contained rather than running out over whatever follows the body, and
 * that a heading after a tall picture clears instead of sitting in the gutter
 * beside it.
 *
 * They are also invisible to a DOM simulator — jsdom has no layout engine, so
 * every rectangle below would be zero and each assertion would be measuring
 * the test's own fixture. That is the same reason this project has no jsdom
 * half at all.
 *
 * The fixture image is 400×300 rather than a placeholder pixel, deliberately:
 * a 1×1 image proves a float exists and never that anything wraps around it,
 * which is the part worth protecting.
 */

const ALT = 'Een blauw vlak van 400 bij 300';
const HEADING = 'Kop na de afbeelding';
const FIRST_PARAGRAPH = /Deze alinea loopt om de afbeelding heen/;

type Box = { x: number; y: number; width: number; height: number };

async function boxOf(locator: Locator): Promise<Box> {
    const box = await locator.boundingBox();

    expect(box, 'the element must be laid out to be measured').not.toBeNull();

    return box as Box;
}

/**
 * The width of the paragraph's *first line*, which is the only thing that
 * actually narrows when a float intrudes. The paragraph's own box does not:
 * a float changes where line boxes are drawn, not the width of the block
 * around them, so measuring the element would assert nothing.
 */
async function firstLineWidth(paragraph: Locator): Promise<number> {
    return paragraph.evaluate((element) => {
        const range = document.createRange();
        range.selectNodeContents(element);

        return range.getClientRects()[0]?.width ?? 0;
    });
}

async function settled(page: Page): Promise<void> {
    // The image is lazy-loaded and its intrinsic size decides the float's
    // height, so a measurement taken before it decodes is a measurement of
    // an empty box.
    await page
        .getByAltText(ALT)
        .evaluate((img: HTMLImageElement) =>
            img.complete ? undefined : img.decode().catch(() => undefined),
        );
}

test.describe('an image beside the text', () => {
    test('wraps the text at desktop width, and the next heading clears it', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/e2e/aside');
        await settled(page);

        const figure = page.locator('figure', {
            has: page.getByAltText(ALT),
        });
        const paragraph = page.getByText(FIRST_PARAGRAPH).first();
        const heading = page.getByRole('heading', { name: HEADING });

        const image = await boxOf(figure);
        const text = await boxOf(paragraph);

        // Beside, not above: they share a horizontal band.
        expect(image.y).toBeLessThan(text.y + text.height);
        expect(text.y).toBeLessThan(image.y + image.height);

        // And on the right, which is what the node's `side` attribute says.
        expect(image.x).toBeGreaterThan(text.x);

        // The wrap itself. The paragraph's box still spans the column, so
        // this compares the first line against that box rather than against
        // a number — a line narrowed by roughly the float's width.
        const line = await firstLineWidth(paragraph);

        expect(line).toBeGreaterThan(0);
        expect(line).toBeLessThan(text.width - image.width / 2);

        // A heading after a tall picture must clear. Without it the heading
        // sits in the gutter beside the image, which reads as broken rather
        // than as a layout.
        const afterwards = await boxOf(heading);

        expect(afterwards.y).toBeGreaterThanOrEqual(image.y + image.height - 1);
    });

    test('the float is contained by the body, not left running out of it', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/e2e/aside');
        await settled(page);

        // Measured against the float's own containing block rather than
        // against whatever happens to follow on this page: a float escapes
        // its parent unless something establishes a formatting context, and
        // the symptom is the picture painting over the downloads section of
        // a page that has one. `flow-root` is applied only when a body
        // contains an aside — applying it always would stop the first and
        // last child's margins collapsing out and shift every page on the
        // site by about 16px.
        const overflow = await page
            .getByAltText(ALT)
            .evaluate((img: HTMLImageElement) => {
                const figure = img.closest('figure');
                const container = figure?.parentElement;

                if (!figure || !container) {
                    return null;
                }

                return (
                    figure.getBoundingClientRect().bottom -
                    container.getBoundingClientRect().bottom
                );
            });

        expect(
            overflow,
            'the figure and its container must both exist',
        ).not.toBeNull();
        expect(overflow as number).toBeLessThanOrEqual(1);
    });

    test('stacks above the text on a phone, matching the document order', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/e2e/aside');
        await settled(page);

        const figure = page.locator('figure', {
            has: page.getByAltText(ALT),
        });
        const paragraph = page.getByText(FIRST_PARAGRAPH).first();

        const image = await boxOf(figure);
        const text = await boxOf(paragraph);

        // Above, and entirely: at 375px the column is about 343px, so a third
        // of it is too small to read a diagram in and leaves the text a
        // measure of some twenty-five characters. Both halves come out worse
        // than either alone, so below `sm` there is no "beside".
        expect(image.y + image.height).toBeLessThanOrEqual(text.y + 1);

        // Above rather than below, because a float only ever changes where a
        // box paints and never where it sits in the document — a screen
        // reader always announces the image first. Matching the phone's
        // visual order to the document order is what makes the two agree at
        // every width, so this asserts the document order too.
        const precedes = await page
            .getByAltText(ALT)
            .evaluate((img, selector) => {
                const figureElement = img.closest('figure');
                const paragraphElement = [
                    ...document.querySelectorAll('p'),
                ].find((element) => element.textContent?.includes(selector));

                if (!figureElement || !paragraphElement) {
                    return null;
                }

                return Boolean(
                    figureElement.compareDocumentPosition(paragraphElement) &
                    Node.DOCUMENT_POSITION_FOLLOWING,
                );
            }, 'Deze alinea loopt om de afbeelding heen');

        expect(precedes).toBe(true);

        // Nothing intrudes on the line any more, so the text runs the full
        // width of its column.
        const line = await firstLineWidth(paragraph);

        expect(line).toBeGreaterThan(text.width * 0.9);
    });
});
