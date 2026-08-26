import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

/**
 * Clicking an image on the public site opens it full screen.
 *
 * Everything worth protecting here is invisible to the PHP suite, which sees
 * stored JSON and an Inertia payload and never a rendered page — and to a DOM
 * simulator, which has no layout engine and no focus model worth the name.
 * The four things below are the ones that would silently stop being true:
 *
 *   - the thing you press is a button with a name, not a click handler on an
 *     <img>, so a keyboard reaches it at all;
 *   - Escape closes and focus comes back to the picture on the page, rather
 *     than to the document body — which is where a student loses their place;
 *   - the alt text is carried into the enlarged view;
 *   - the gallery is a set the arrows move through, and it wraps.
 *
 * There is also one thing that must NOT be true, and it is a security
 * property rather than a nicety: nothing here may become a link. SVG is
 * served Content-Disposition: attachment on purpose, so a lightbox that
 * navigated to the image URL would hand a student a download instead of a
 * picture. The last test asserts the absence.
 */

const BANNER_ALT = 'Banner van de galerijpagina';
const GALLERY_ALTS = [
    'Eerste vlak, rood',
    'Tweede vlak, groen',
    'Derde vlak, geel',
];

/** The overlay's own image, as opposed to the thumbnail behind it. */
function enlarged(page: Page): Locator {
    return page.getByRole('dialog').getByRole('img');
}

function thumbnail(page: Page, alt: string): Locator {
    // Named by its alt text plus the sr-only word that says what pressing it
    // does, so this is deliberately a substring match: it fails if the alt
    // stops reaching the name, which is half of what the feature promises.
    return page.getByRole('button', { name: alt });
}

test.describe('opening an image full screen', () => {
    test('a gallery image opens, keeps its alt text, and closes on Escape', async ({
        page,
    }) => {
        await page.goto('/e2e/gallery');

        const trigger = thumbnail(page, GALLERY_ALTS[1]);

        await expect(trigger).toBeVisible();

        // Opened from the keyboard, not with a click: that is the whole
        // difference between a button and a click handler on an <img>, and a
        // click would pass either way.
        await trigger.focus();
        await page.keyboard.press('Enter');

        const dialog = page.getByRole('dialog');

        await expect(dialog).toBeVisible();

        // The alt travels twice over: it names the dialog, and it is the
        // enlarged image's own alt. Dropping either is the failure the queue
        // entry named.
        await expect(dialog).toHaveAccessibleName(GALLERY_ALTS[1]);
        await expect(enlarged(page)).toHaveAttribute('alt', GALLERY_ALTS[1]);

        // Focus is inside the overlay rather than left behind on the page.
        await expect(dialog.locator(':focus-within')).not.toHaveCount(0);

        await page.keyboard.press('Escape');

        await expect(dialog).toBeHidden();

        // And back on the picture, not on the body. This is the part that is
        // easy to get subtly wrong and impossible to notice by looking.
        await expect(trigger).toBeFocused();
    });

    test('the arrows move through the gallery and wrap at the end', async ({
        page,
    }) => {
        await page.goto('/e2e/gallery');

        await thumbnail(page, GALLERY_ALTS[2]).click();

        const dialog = page.getByRole('dialog');

        await expect(enlarged(page)).toHaveAttribute('alt', GALLERY_ALTS[2]);

        // Wrapping is the reason the fixture has three images and not two:
        // with a pair, "next" and "previous" land in the same place and the
        // test would pass whichever one ran.
        await page.keyboard.press('ArrowRight');
        await expect(enlarged(page)).toHaveAttribute('alt', GALLERY_ALTS[0]);

        await page.keyboard.press('ArrowLeft');
        await expect(enlarged(page)).toHaveAttribute('alt', GALLERY_ALTS[2]);

        // The visible controls, for a student on a touchscreen who has no
        // arrow keys at all.
        await dialog.getByRole('button', { name: 'Vorige afbeelding' }).click();
        await expect(enlarged(page)).toHaveAttribute('alt', GALLERY_ALTS[1]);

        await page.keyboard.press('Escape');

        // Focus returns to whichever image is showing, not to the one that
        // was pressed — after arrowing those differ, and landing on the
        // picture you were just looking at is what keeps the page put.
        await expect(thumbnail(page, GALLERY_ALTS[1])).toBeFocused();
    });

    test('a banner is a set of one, so it enlarges but draws no arrows', async ({
        page,
    }) => {
        await page.goto('/e2e/gallery');

        await thumbnail(page, BANNER_ALT).click();

        const dialog = page.getByRole('dialog');

        await expect(enlarged(page)).toHaveAttribute('alt', BANNER_ALT);

        await expect(
            dialog.getByRole('button', { name: 'Volgende afbeelding' }),
        ).toHaveCount(0);
        await expect(
            dialog.getByRole('button', { name: 'Vorige afbeelding' }),
        ).toHaveCount(0);
    });

    test('the overlay fits a phone instead of turning it into a scrolling page', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/e2e/gallery');

        await thumbnail(page, GALLERY_ALTS[0]).click();

        const dialog = page.getByRole('dialog');

        await expect(dialog).toBeVisible();

        // The picture decides how tall the middle of the overlay is, so a
        // measurement taken before it decodes is a measurement of an empty
        // box — which is how this test first failed, reporting a width of
        // zero for a layout that was correct.
        await expect(enlarged(page)).toHaveJSProperty('complete', true);

        // The project audited nineteen routes at 375/768/1280 in both themes
        // and found zero horizontal overflow. An overlay that does not
        // account for the viewport is the obvious way to reintroduce it.
        const measurements = await dialog.evaluate((element) => {
            const image = element.querySelector('img') as HTMLImageElement;

            return {
                documentOverflow:
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
                overlayScrollX: element.scrollWidth - element.clientWidth,
                overlayScrollY: element.scrollHeight - element.clientHeight,
                imageWidth: image.getBoundingClientRect().width,
                imageBottom: image.getBoundingClientRect().bottom,
                viewportHeight: window.innerHeight,
            };
        });

        expect(measurements.documentOverflow).toBe(0);
        expect(measurements.overlayScrollX).toBe(0);
        expect(measurements.overlayScrollY).toBe(0);

        // Bigger than the column it came from, which is the entire point,
        // and still inside the viewport.
        expect(measurements.imageWidth).toBeGreaterThan(300);
        expect(measurements.imageBottom).toBeLessThanOrEqual(
            measurements.viewportHeight + 1,
        );
    });

    test('nothing on the page navigates to an image URL', async ({ page }) => {
        await page.goto('/e2e/gallery');

        // The absence is the guard, the same way lib/social-embed.ts has no
        // function that could build an Instagram frame URL. SVG is served
        // Content-Disposition: attachment because it is XML that can carry
        // script, so a link — or a target="_blank" — hands a student a
        // download where a picture belongs.
        const links = await page.locator('a[href*="/images/"]').count();

        expect(links).toBe(0);

        await thumbnail(page, GALLERY_ALTS[0]).click();

        const anchors = await page.getByRole('dialog').locator('a').count();

        expect(anchors).toBe(0);
    });
});
