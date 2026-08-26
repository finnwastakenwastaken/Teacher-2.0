import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

/**
 * The image the running text flows around. Three layout facts here are
 * invisible to the PHP suite (markup only) and to jsdom (no layout engine, so
 * every rectangle would read zero): that text actually wraps, that the float
 * is contained rather than running out over what follows, and that a heading
 * after a tall picture clears it. The fixture image is 400×300, not a 1×1
 * pixel, because only a real size proves anything wraps around it.
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
 * The paragraph's own box never narrows for a float — only its first line
 * does, since a float changes where line boxes are drawn, not the block's
 * width.
 */
async function firstLineWidth(paragraph: Locator): Promise<number> {
    return paragraph.evaluate((element) => {
        const range = document.createRange();
        range.selectNodeContents(element);

        return range.getClientRects()[0]?.width ?? 0;
    });
}

async function settled(page: Page): Promise<void> {
    // Lazy-loaded, and its intrinsic size decides the float's height — measure
    // before it decodes and you measure an empty box.
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

        // Compares the first line against the paragraph's own (unnarrowed)
        // box, since the box still spans the column.
        const line = await firstLineWidth(paragraph);

        expect(line).toBeGreaterThan(0);
        expect(line).toBeLessThan(text.width - image.width / 2);

        // Must clear the picture, or it sits in the gutter beside it.
        const afterwards = await boxOf(heading);

        expect(afterwards.y).toBeGreaterThanOrEqual(image.y + image.height - 1);
    });

    test('the float is contained by the body, not left running out of it', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/e2e/aside');
        await settled(page);

        // Measured against the float's own containing block, not whatever
        // follows on the page: a float escapes its parent unless something
        // establishes a formatting context, and the symptom is the picture
        // painting over the downloads section. `flow-root` is applied only
        // when a body contains an aside — applying it unconditionally would
        // stop margin collapsing and shift every page by ~16px.
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

        // Below `sm` there is no "beside": at 375px a third of the column is
        // too small for the diagram and leaves ~25 characters for the text.
        expect(image.y + image.height).toBeLessThanOrEqual(text.y + 1);

        // A float changes only where a box paints, never where it sits in the
        // document, so a screen reader always announces the image first —
        // hence stacking above matches document order at every width.
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
