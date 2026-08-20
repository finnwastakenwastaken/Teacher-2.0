import { createElement } from 'react';

/*
 * Renders an icon from catalogue geometry supplied by the server.
 *
 * The public site used to draw icons with lucide's `DynamicIcon`, which works
 * by shipping a name-to-chunk map of every icon in the catalogue — 48.7 KB
 * gzipped for 1,760 icons, on the critical path of every student-facing topic
 * page, and growing linearly with the catalogue. With ~15,000 icons that map
 * would have been a quarter of a megabyte. The server sends the handful of
 * shapes a page actually shows instead, and this file draws them.
 *
 * Nodes are structured `[tag, attributes]` pairs, never markup. That is the
 * same reason `rich-text.tsx` builds elements rather than setting innerHTML:
 * nothing derived from stored data is ever injected as HTML.
 */

export type IconNode = [string, Record<string, string>];

export type IconData = {
    library: string;
    nodes: IconNode[];
};

/** Icons drawn as filled shapes rather than strokes. Mirrors IconCatalogue. */
const FILLED_LIBRARIES = new Set(['mdi', 'tabler-filled']);

/**
 * Defence in depth. The catalogue is generated and already filtered, but this
 * component is the last thing between stored data and the DOM, so it refuses
 * anything it does not recognise rather than passing it through.
 */
const ALLOWED_TAGS = new Set([
    'path',
    'circle',
    'ellipse',
    'line',
    'polyline',
    'polygon',
    'rect',
    'g',
]);

/** SVG attributes React insists on in camelCase. */
const ATTRIBUTE_NAMES: Record<string, string> = {
    'fill-rule': 'fillRule',
    'clip-rule': 'clipRule',
    'stroke-width': 'strokeWidth',
    'stroke-linecap': 'strokeLinecap',
    'stroke-linejoin': 'strokeLinejoin',
};

function toReactProps(attributes: Record<string, string>) {
    const props: Record<string, string> = {};

    for (const [name, value] of Object.entries(attributes)) {
        props[ATTRIBUTE_NAMES[name] ?? name] = value;
    }

    return props;
}

type IconProps = {
    icon: IconData | null | undefined;
    className?: string;
    /**
     * Icons are decorative by default — they sit beside a visible label
     * everywhere in this application. Pass a label only where the icon is
     * genuinely the only thing carrying the meaning.
     */
    label?: string;
};

export function Icon({ icon, className, label }: IconProps) {
    if (!icon || icon.nodes.length === 0) {
        return null;
    }

    const filled = FILLED_LIBRARIES.has(icon.library);

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            width="24"
            height="24"
            fill={filled ? 'currentColor' : 'none'}
            stroke={filled ? undefined : 'currentColor'}
            strokeWidth={filled ? undefined : 2}
            strokeLinecap={filled ? undefined : 'round'}
            strokeLinejoin={filled ? undefined : 'round'}
            className={className}
            role={label ? 'img' : undefined}
            aria-label={label}
            aria-hidden={label ? undefined : true}
        >
            {icon.nodes.map(([tag, attributes], index) =>
                ALLOWED_TAGS.has(tag)
                    ? createElement(tag, {
                          key: index,
                          ...toReactProps(attributes),
                      })
                    : null,
            )}
        </svg>
    );
}
