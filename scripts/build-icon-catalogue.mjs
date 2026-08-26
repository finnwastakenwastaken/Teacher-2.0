/**
 * Generates the icon catalogue consumed by `php artisan icons:sync`. Every
 * name and path comes from the packages' own exported data, never a
 * hand-written list (v1's picker rendered blank tiles from guessed names).
 *
 * Output maps "library:name" to child nodes in lucide's [tag, attributes]
 * shape, which the React renderer turns into real elements — structured
 * data, never SVG markup, so icons can't reopen the HTML-injection hole
 * PageContent closes elsewhere.
 *
 * Run: npm run icons:build
 */
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const outputPath = resolve(root, 'database/data/icons.json');

/**
 * Attributes allowed on a generated node; anything else is dropped. The
 * renderer trusts this file, so the whitelist is enforced here at generation
 * time rather than at render time.
 */
const ALLOWED_ATTRIBUTES = new Set([
    'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'x2', 'y1', 'y2',
    'width', 'height', 'points', 'transform', 'fill', 'fill-rule',
    'clip-rule', 'stroke', 'stroke-width', 'stroke-linecap',
    'stroke-linejoin', 'opacity',
]);

const ALLOWED_TAGS = new Set([
    'path', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'rect', 'g',
]);

function cleanNode([tag, attributes]) {
    if (!ALLOWED_TAGS.has(tag)) {
        return null;
    }

    const kept = {};

    for (const [key, value] of Object.entries(attributes ?? {})) {
        // lucide ships a React `key` in its node data; it is a rendering
        // detail of their component, not geometry.
        if (key === 'key' || !ALLOWED_ATTRIBUTES.has(key)) {
            continue;
        }

        kept[key] = String(value);
    }

    return Object.keys(kept).length > 0 ? [tag, kept] : null;
}

const skipped = [];

function addIcons(catalogue, library, entries) {
    let added = 0;

    for (const [name, nodes] of entries) {
        if (!Array.isArray(nodes)) {
            skipped.push(`${library}:${name}`);
            continue;
        }

        const cleaned = nodes.map(cleanNode).filter(Boolean);

        if (cleaned.length === 0) {
            continue;
        }

        catalogue[`${library}:${name}`] = cleaned;
        added += 1;
    }

    return added;
}

// --- lucide ---------------------------------------------------------------
// Each icon module exports `__iconNode`; the name list comes from the same
// package's `iconNames`. About 200 names are aliases (re-export another
// icon's default, no `__iconNode`) — followed through the module source
// rather than dropped, since dropping would silently delete real icons a
// site may already use. Throws if the alias target ever moves.
async function lucideEntries() {
    const { iconNames } = await import('lucide-react/dynamic');
    const moduleDir = resolve(root, 'node_modules/lucide-react/dist/esm/icons');
    const entries = [];

    for (const name of iconNames) {
        let { __iconNode: nodes } = await import(`lucide-react/icons/${name}`);

        if (!nodes) {
            const source = readFileSync(resolve(moduleDir, `${name}.js`), 'utf8');
            const target = source.match(/export \{ default \} from '\.\/([^']+)\.js'/)?.[1];

            if (!target) {
                throw new Error(`lucide:${name} has no node data and is not an alias.`);
            }

            ({ __iconNode: nodes } = await import(`lucide-react/icons/${target}`));
        }

        entries.push([name, nodes]);
    }

    return entries;
}

// --- tabler ---------------------------------------------------------------
// `@tabler/icons` ships the node data as JSON in exactly this shape.
// Read straight from node_modules: the package's `exports` map rewrites
// bare subpaths into ./icons/, so neither require() nor require.resolve()
// can reach these two files.
function tablerEntries(file) {
    const path = resolve(root, 'node_modules/@tabler/icons', file);

    return Object.entries(JSON.parse(readFileSync(path, 'utf8')));
}

// --- material design icons ------------------------------------------------
// `@mdi/js` exports one path string per icon, named mdiFooBar.
function mdiEntries() {
    const mdi = require('@mdi/js');

    return Object.entries(mdi)
        .filter(([key, value]) => key.startsWith('mdi') && typeof value === 'string')
        .map(([key, d]) => [
            key
                .replace(/^mdi/, '')
                .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
                .toLowerCase(),
            [['path', { d }]],
        ]);
}

const catalogue = {};
const counts = {};

counts.lucide = addIcons(catalogue, 'lucide', await lucideEntries());
counts.tabler = addIcons(catalogue, 'tabler', tablerEntries('tabler-nodes-outline.json'));
counts['tabler-filled'] = addIcons(catalogue, 'tabler-filled', tablerEntries('tabler-nodes-filled.json'));
counts.mdi = addIcons(catalogue, 'mdi', mdiEntries());

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, JSON.stringify(catalogue));

const total = Object.keys(catalogue).length;
const megabytes = (JSON.stringify(catalogue).length / 1048576).toFixed(2);

for (const [library, count] of Object.entries(counts)) {
    console.log(`  ${library.padEnd(14)} ${String(count).padStart(6)}`);
}

console.log(`  ${'total'.padEnd(14)} ${String(total).padStart(6)}  (${megabytes} MB)`);
console.log(`  written to database/data/icons.json`);

if (skipped.length) {
    console.log(`  skipped ${skipped.length} entries without node data: ${skipped.slice(0, 5).join(', ')}${skipped.length > 5 ? ' ...' : ''}`);
}
