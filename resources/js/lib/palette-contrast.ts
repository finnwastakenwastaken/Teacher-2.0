/*
 * The contrast gate for the palette editor.
 *
 * Twenty semantic background/foreground pairs clear WCAG AA in both themes
 * today, and the owner is about to be handed a colour wheel. Without this they
 * can make their own site unreadable and never find out, because they are not
 * the ones who cannot read it.
 *
 * Why this is measured in a browser rather than computed on the server.
 * Several roles are a color-mix() of a palette entry toward or away from the
 * surface — precisely because the raw colour sits in a dead luminance band
 * where it fails both as a fill and as text. Reimplementing that mixing in
 * PHP would be reimplementing a colour space, and the project has already paid
 * for the alternative once: the first contrast pass estimated from the palette
 * and was wrong about five roles at once. So the rule became "measured in the
 * browser against the real rendered stack", and this is that rule applied
 * before the save rather than after the fact.
 *
 * How it works. A hidden probe holds one node per pair, styled with the real
 * custom properties. The candidate palette is set on the probe as inline
 * `--p-*` values, so the semantic roles re-resolve from it; getComputedStyle
 * then reports what the browser would actually paint. It is done twice, once
 * under `.theme-light` and once under `.dark`, because both are class
 * selectors and therefore re-declare the roles for their own subtree — which
 * is the only reason this can measure the dark theme without putting the live
 * page into it.
 *
 * That last part matters more than it looks. Components carry
 * `transition-[color,box-shadow]`, so reading a colour while the page is
 * toggling between themes returns a value from the middle of an animation.
 * That produced a page of confident, entirely fictional failures once already.
 * The probe therefore sets `transition: none` and never touches the document
 * element.
 */

import { t } from '@/lib/i18n';

/** WCAG 2.1 AA for normal-size text. Everything here is normal-size text. */
export const MINIMUM_RATIO = 4.5;

export type PaletteTheme = 'light' | 'dark';

export type ContrastFailure = {
    /** The pair's name, already translated. */
    pair: string;
    theme: PaletteTheme;
    /** The measured ratio, or null when the colour could not be read at all. */
    ratio: number | null;
};

type Pair = {
    /** The custom property naming the surface. */
    background: string;
    /** The custom property naming the text drawn on it. */
    foreground: string;
    /**
     * A function rather than a string: the dictionary is set per document, so
     * a constant built at import time would freeze whichever language loaded
     * first. Resolved at call time instead.
     */
    label: () => string;
};

/*
 * The twenty pairs.
 *
 * Fourteen are a fill and the label it carries. Six are the three roles that
 * are text and nothing else — link, error and muted-foreground — checked
 * against both surfaces, because in the dark theme the card is *lighter* than
 * the page and is therefore the harder of the two. That is exactly where the
 * original contrast pass missed two roles.
 *
 * The border and input roles are deliberately absent: they are
 * semi-transparent by design and are not text.
 */
const PAIRS: Pair[] = [
    {
        background: '--background',
        foreground: '--foreground',
        label: () => t('ui.theme.pairs.page'),
    },
    {
        background: '--card',
        foreground: '--card-foreground',
        label: () => t('ui.theme.pairs.card'),
    },
    {
        background: '--popover',
        foreground: '--popover-foreground',
        label: () => t('ui.theme.pairs.popover'),
    },
    {
        background: '--primary',
        foreground: '--primary-foreground',
        label: () => t('ui.theme.pairs.primary'),
    },
    {
        background: '--secondary',
        foreground: '--secondary-foreground',
        label: () => t('ui.theme.pairs.secondary'),
    },
    {
        background: '--muted',
        foreground: '--muted-foreground',
        label: () => t('ui.theme.pairs.muted'),
    },
    {
        background: '--accent',
        foreground: '--accent-foreground',
        label: () => t('ui.theme.pairs.accent'),
    },
    {
        background: '--destructive',
        foreground: '--destructive-foreground',
        label: () => t('ui.theme.pairs.destructive'),
    },
    {
        background: '--success',
        foreground: '--success-foreground',
        label: () => t('ui.theme.pairs.success'),
    },
    {
        background: '--warning',
        foreground: '--warning-foreground',
        label: () => t('ui.theme.pairs.warning'),
    },
    {
        background: '--info',
        foreground: '--info-foreground',
        label: () => t('ui.theme.pairs.info'),
    },
    {
        background: '--sidebar',
        foreground: '--sidebar-foreground',
        label: () => t('ui.theme.pairs.sidebar'),
    },
    {
        background: '--sidebar-primary',
        foreground: '--sidebar-primary-foreground',
        label: () => t('ui.theme.pairs.sidebar_primary'),
    },
    {
        background: '--sidebar-accent',
        foreground: '--sidebar-accent-foreground',
        label: () => t('ui.theme.pairs.sidebar_accent'),
    },
    {
        background: '--background',
        foreground: '--link',
        label: () => t('ui.theme.pairs.link_on_page'),
    },
    {
        background: '--card',
        foreground: '--link',
        label: () => t('ui.theme.pairs.link_on_card'),
    },
    {
        background: '--background',
        foreground: '--error',
        label: () => t('ui.theme.pairs.error_on_page'),
    },
    {
        background: '--card',
        foreground: '--error',
        label: () => t('ui.theme.pairs.error_on_card'),
    },
    {
        background: '--background',
        foreground: '--muted-foreground',
        label: () => t('ui.theme.pairs.muted_on_page'),
    },
    {
        background: '--card',
        foreground: '--muted-foreground',
        label: () => t('ui.theme.pairs.muted_on_card'),
    },
];

/** How many pairs are checked, for the screen's "all clear" line. */
export const PAIR_COUNT = PAIRS.length;

type Rgb = [number, number, number];

/*
 * Colour reading, via a 1×1 canvas.
 *
 * getComputedStyle returns whatever serialisation the engine chose —
 * `rgb(…)`, `rgba(…)`, `color(srgb …)` or an oklab mix — and writing a parser
 * for all of those is writing a colour library. The canvas already has one,
 * and painting the colour is also what composites any alpha over the surface
 * underneath it, which a parser would have to do by hand.
 */
let context: CanvasRenderingContext2D | null | undefined;

function canvasContext(): CanvasRenderingContext2D | null {
    if (context === undefined) {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        context = canvas.getContext('2d', { willReadFrequently: true });
    }

    return context;
}

/**
 * Paint `colour` over `backdrop` and read the pixel back.
 *
 * Returns null when the browser will not accept the colour. That is
 * fail-closed on purpose: an unreadable colour must block the save, because
 * the alternative is reporting a ratio computed from whatever fillStyle
 * happened to hold.
 */
function paint(colour: string, backdrop: string): Rgb | null {
    const ctx = canvasContext();

    if (!ctx) {
        return null;
    }

    // An invalid assignment to fillStyle is ignored rather than thrown, so the
    // sentinel is how an unreadable colour is detected at all.
    const sentinel = '#ff00ff';

    ctx.fillStyle = sentinel;
    ctx.fillStyle = backdrop;

    if (ctx.fillStyle === sentinel) {
        return null;
    }

    ctx.fillRect(0, 0, 1, 1);

    ctx.fillStyle = sentinel;
    ctx.fillStyle = colour;

    if (ctx.fillStyle === sentinel) {
        return null;
    }

    ctx.fillRect(0, 0, 1, 1);

    const data = ctx.getImageData(0, 0, 1, 1).data;

    return [data[0], data[1], data[2]];
}

/** WCAG 2.1 relative luminance. */
function luminance([red, green, blue]: Rgb): number {
    const channel = (value: number): number => {
        const scaled = value / 255;

        return scaled <= 0.03928
            ? scaled / 12.92
            : Math.pow((scaled + 0.055) / 1.055, 2.4);
    };

    return (
        0.2126 * channel(red) + 0.7152 * channel(green) + 0.0722 * channel(blue)
    );
}

function ratio(a: Rgb, b: Rgb): number {
    const first = luminance(a);
    const second = luminance(b);
    const lighter = Math.max(first, second);
    const darker = Math.min(first, second);

    return (lighter + 0.05) / (darker + 0.05);
}

type ThemeProbe = {
    /** Carries the theme's class, and the candidate palette as inline vars. */
    host: HTMLElement;
    /** The theme's own page colour, as a backdrop for anything with alpha. */
    base: HTMLElement;
    /** One node per pair, in the order of PAIRS. */
    nodes: HTMLElement[];
};

type Probe = {
    root: HTMLElement;
    themes: Record<PaletteTheme, ThemeProbe>;
};

let probe: Probe | null = null;

function buildTheme(root: HTMLElement, className: string): ThemeProbe {
    const host = document.createElement('div');
    host.className = className;
    host.style.transition = 'none';

    const base = document.createElement('div');
    base.style.transition = 'none';
    base.style.backgroundColor = 'var(--background)';
    host.appendChild(base);

    const nodes = PAIRS.map((pair) => {
        const node = document.createElement('div');
        node.style.transition = 'none';
        node.style.backgroundColor = `var(${pair.background})`;
        node.style.color = `var(${pair.foreground})`;
        host.appendChild(node);

        return node;
    });

    root.appendChild(host);

    return { host, base, nodes };
}

function buildProbe(): Probe {
    if (probe && probe.root.isConnected) {
        return probe;
    }

    const root = document.createElement('div');
    root.setAttribute('aria-hidden', 'true');
    // Rendered, but nowhere a person can see it. Deliberately not
    // `display: none` — colours would still compute, but keeping the probe in
    // the box tree removes a whole class of "does this engine still resolve
    // that" doubt for the price of one off-screen pixel.
    root.style.cssText =
        'position:fixed;left:-10000px;top:0;width:1px;height:1px;overflow:hidden;pointer-events:none;opacity:0;';

    probe = {
        root,
        themes: {
            // Both are class selectors, which is what makes this work at all:
            // a class re-declares the semantic roles for its own subtree, so
            // they resolve from the candidate palette set on the host rather
            // than from the one the live page is wearing.
            light: buildTheme(root, 'theme-light'),
            dark: buildTheme(root, 'dark'),
        },
    };

    document.body.appendChild(root);

    return probe;
}

/**
 * Measure every pair in both themes under `palette`, and report the ones that
 * do not clear AA.
 *
 * `palette` is the full candidate palette keyed without the `--p-` prefix, the
 * shape the server sends. Every entry is applied, not only the changed ones,
 * so the probe never half-inherits the palette the page is currently wearing.
 */
export function measurePalette(
    palette: Record<string, string>,
): ContrastFailure[] {
    // Unreachable: this project has no server-side rendering, and the screen
    // that calls this is behind `auth` in a browser. Kept as a guard rather
    // than as a promise — if that ever changes, an empty list here means
    // "everything passes", which is the wrong direction to fail, and this is
    // the line to revisit.
    if (typeof document === 'undefined') {
        return [];
    }

    const built = buildProbe();
    const failures: ContrastFailure[] = [];

    for (const theme of ['light', 'dark'] as const) {
        const { host, base, nodes } = built.themes[theme];

        for (const [key, value] of Object.entries(palette)) {
            host.style.setProperty(`--p-${key}`, value);
        }

        const backdrop = getComputedStyle(base).backgroundColor;

        nodes.forEach((node, index) => {
            const styles = getComputedStyle(node);
            const surface = paint(styles.backgroundColor, backdrop);
            // Composited over what the surface actually came out as, not over
            // its declared value — otherwise a role with any alpha in it is
            // measured against the wrong thing.
            const text = surface
                ? paint(styles.color, `rgb(${surface.join(',')})`)
                : null;

            if (!surface || !text) {
                failures.push({
                    pair: PAIRS[index].label(),
                    theme,
                    ratio: null,
                });

                return;
            }

            const measured = ratio(surface, text);

            if (measured < MINIMUM_RATIO) {
                failures.push({
                    pair: PAIRS[index].label(),
                    theme,
                    ratio: measured,
                });
            }
        });
    }

    return failures;
}

/** Drop the probe. Called when the editor unmounts. */
export function releaseProbe(): void {
    probe?.root.remove();
    probe = null;
}
