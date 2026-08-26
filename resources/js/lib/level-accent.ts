/*
 * A colour per education level, for the downloads section.
 *
 * Downloads are already grouped by level; giving each group a consistent
 * colour is what makes the grouping scannable rather than something you have
 * to read. It is the one idea worth keeping from the design references,
 * because it is functional rather than decorative.
 *
 * **Written out, never interpolated.** `bg-level-${n}` emits a class Tailwind
 * never compiled, and the failure is silent — the element simply has no
 * colour and the source looks correct. The technical reference records that this has
 * already caught two features, which is why the map is explicit.
 *
 * **The colour reinforces a label; it is never the label.** Every group
 * carries its level's name in text right beside the accent, so nothing is
 * conveyed by colour alone — a rail and a dot only, never a fill with text on
 * it. That is also what keeps these roles out of the contrast gate: give one
 * a foreground colour and it belongs in those twenty pairs instead.
 */

type LevelAccent = {
    /** The group card's left edge. */
    rail: string;
    /** A small swatch beside the level's name. */
    dot: string;
};

const ACCENTS: LevelAccent[] = [
    { rail: 'border-l-level-1', dot: 'bg-level-1' },
    { rail: 'border-l-level-2', dot: 'bg-level-2' },
    { rail: 'border-l-level-3', dot: 'bg-level-3' },
    { rail: 'border-l-level-4', dot: 'bg-level-4' },
    { rail: 'border-l-level-5', dot: 'bg-level-5' },
];

/**
 * The group that is not a level.
 *
 * Untagged downloads render under a leading "for everyone" heading, which
 * applies regardless of track. Colouring it would say it were a sixth level.
 */
const NEUTRAL: LevelAccent = { rail: 'border-l-border', dot: 'bg-muted' };

export const UNTAGGED_GROUP = 'all';

/**
 * Assign a colour to every group, in the owner's order.
 *
 * Built from the order the server sent, **before** the "my level" preference
 * reorders anything — otherwise choosing a level would repaint the whole
 * section, and a student would learn that the colours mean nothing.
 *
 * Beyond five levels the rotation wraps and two groups share a colour. The
 * seeded set is four and the colour is never the only signal, so that is a
 * fair trade against maintaining more accents than the palette has distinct
 * categorical entries.
 */
export function accentsFor(keys: string[]): Map<string, LevelAccent> {
    const map = new Map<string, LevelAccent>();
    let index = 0;

    for (const key of keys) {
        if (key === UNTAGGED_GROUP) {
            map.set(key, NEUTRAL);

            continue;
        }

        map.set(key, ACCENTS[index % ACCENTS.length]);
        index++;
    }

    return map;
}

export function accentOr(
    accents: Map<string, LevelAccent>,
    key: string,
): LevelAccent {
    return accents.get(key) ?? NEUTRAL;
}
