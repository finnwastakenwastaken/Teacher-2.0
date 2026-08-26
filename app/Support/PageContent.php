<?php

namespace App\Support;

/**
 * Server-side allow-list for TipTap JSON page bodies: any node type not
 * named below is dropped, and surviving nodes keep only their listed
 * attributes. The document arrives from the browser as untrusted input, so
 * without this whitelist an attacker — or a client bug — could persist a
 * node type the renderer doesn't expect. A node type added to the editor but
 * forgotten here silently stops saving — the safe failure direction.
 * Combined with a renderer that never uses dangerouslySetInnerHTML, this is
 * what removes stored XSS as a category, not the allow-list alone.
 */
class PageContent
{
    /**
     * type => list of attributes that survive sanitising.
     * A node type with no entry here is removed entirely.
     */
    private const NODES = [
        'doc' => [],
        'paragraph' => ['textAlign'],
        'text' => [],
        'hardBreak' => [],
        'heading' => ['level', 'textAlign'],
        'bulletList' => [],
        'orderedList' => ['start'],
        'listItem' => [],
        'blockquote' => [],
        // Tables. `colwidth` is a list of pixel widths written by the column
        // resizer, not something anyone types.
        'table' => [],
        'tableRow' => [],
        'tableHeader' => ['colspan', 'rowspan', 'colwidth'],
        'tableCell' => ['colspan', 'rowspan', 'colwidth'],
        // The embed blocks.
        'fileEmbed' => ['ulid'],
        'youtubeEmbed' => ['videoId'],
        // A TikTok or an Instagram Reel. Only the platform and the post id
        // are stored; the embed URL is rebuilt from them, so a pasted link's
        // tracking parameters have nothing to ride along in.
        'socialEmbed' => ['platform', 'postId'],
        'imageGallery' => ['ulids'],
        // One image the running text flows around. `side` and `size` are
        // enumerations the renderer turns into fixed classes, never numbers
        // and never a style string.
        'imageAside' => ['ulid', 'side', 'size'],
    ];

    /**
     * The embed blocks, named once: every node the simple editor does not
     * offer and sanitiseWithoutEmbeds() therefore strips. Most publish a
     * media file — reachability is decided by walking from the file to the
     * *pages* showing it, so one on a topic introduction or the homepage
     * renders for the owner and 403s for every student. socialEmbed has no
     * local file and cannot fail that way; it is here because this list
     * guards paragraph editors, and a block arriving in one was not authored
     * there. collectReferences() names its media node types separately,
     * since it needs to know which attribute on each carries the ULID —
     * adding a media block means editing both, or the two silently drift.
     */
    private const EMBED_NODES = [
        'fileEmbed',
        'youtubeEmbed',
        'socialEmbed',
        'imageGallery',
        'imageAside',
    ];

    /**
     * The platforms a socialEmbed may name, and the shape of a post id on
     * each. Mirrors resources/js/lib/social-embed.ts, which extracts the id
     * from whatever the owner pasted; this is what decides whether to believe
     * it. The value is interpolated into a URL, so both patterns are anchored
     * and neither admits a slash, a dot or a colon.
     */
    private const SOCIAL_ID_PATTERNS = [
        'tiktok' => '/^\d{10,25}$/',
        'instagram' => '/^[A-Za-z0-9_-]{5,20}$/',
    ];

    private const MARKS = [
        'bold' => [],
        'italic' => [],
        // H₂O and m/s². Physics and chemistry material is unwritable without
        // these, which is why they are marks rather than a formatting nicety.
        'subscript' => [],
        'superscript' => [],
        'link' => ['href'],
    ];

    /** Headings start at 2 — the page title is the only h1. */
    private const HEADING_LEVELS = [2, 3, 4];

    private const TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    /** Which side of the running text an imageAside sits on. */
    private const ASIDE_SIDES = ['left', 'right'];

    /**
     * How much of the column an imageAside takes.
     *
     * A named bucket rather than a number: the renderer maps it to one of
     * three compiled classes, so there is no arithmetic and nothing that can
     * become a style string.
     */
    private const ASIDE_SIZES = ['small', 'medium', 'large'];

    /**
     * Attributes for which an explicit null is a real value ("nobody set
     * this"), not a malformed one. Elsewhere in sanitiseAttrs() null means
     * "drop the node"; for these it means drop the key and keep the node.
     * `imageAside`'s side/size are deliberately excluded — they declare a
     * real default, so a bad value falls back to it instead of rejecting.
     */
    private const NULLABLE_ATTRS = ['textAlign', 'colwidth'];

    /**
     * A table cell cannot span more than this. Purely a sanity bound: the
     * value is arithmetic the renderer hands to colSpan, and an absurd number
     * is never something the editor produced.
     */
    private const MAX_CELL_SPAN = 100;

    /**
     * Return a copy of $document containing only whitelisted nodes, marks and
     * attributes. Structure is preserved; anything unrecognised is dropped.
     *
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>|null
     */
    public static function sanitise(?array $document): ?array
    {
        if ($document === null) {
            return null;
        }

        $root = self::sanitiseNode($document);

        // A document that sanitises away to nothing is stored as an empty
        // doc rather than null, so "saved but blank" stays distinguishable
        // from "never written".
        return $root ?? ['type' => 'doc', 'content' => []];
    }

    /**
     * The same whitelist, minus every embed block (see EMBED_NODES). Used
     * for the homepage introduction: MediaAccess publishes a file by walking
     * to the *pages* showing it, and the homepage is not a page row, so an
     * embed there would render for the owner and 403 for everyone else.
     *
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>|null
     */
    public static function sanitiseWithoutEmbeds(?array $document): ?array
    {
        $clean = self::sanitise($document);

        return $clean === null ? null : self::stripEmbeds($clean);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function stripEmbeds(array $node): array
    {
        if (! isset($node['content']) || ! is_array($node['content'])) {
            return $node;
        }

        $node['content'] = array_values(array_map(
            fn (array $child) => self::stripEmbeds($child),
            array_filter(
                $node['content'],
                fn (mixed $child) => is_array($child) && ! in_array(
                    $child['type'] ?? null,
                    self::EMBED_NODES,
                    true,
                ),
            ),
        ));

        return $node;
    }

    /**
     * Flatten a document to plain text for the search vector (task 8) and for
     * excerpts. Derived server-side and never accepted from the client — it
     * has to describe what is actually stored.
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function toPlainText(?array $document): string
    {
        if ($document === null) {
            return '';
        }

        $pieces = [];
        self::collectText($document, $pieces);

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $pieces)) ?? '');
    }

    /**
     * Every media item the document references, by ULID. Drives both
     * MediaAccess's "is this file published" check and "still in use" on
     * delete.
     *
     * @param  array<string, mixed>|null  $document
     * @return array{images: list<string>, files: list<string>}
     */
    public static function references(?array $document): array
    {
        $images = [];
        $files = [];

        self::collectReferences($document ?? [], $images, $files);

        return [
            'images' => array_values(array_unique($images)),
            'files' => array_values(array_unique($files)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function sanitiseNode(mixed $node): ?array
    {
        if (! is_array($node) || ! isset($node['type']) || ! is_string($node['type'])) {
            return null;
        }

        $type = $node['type'];

        if (! array_key_exists($type, self::NODES)) {
            return null;
        }

        $clean = ['type' => $type];

        if ($type === 'text') {
            // A text node without a string payload is malformed, not empty.
            if (! isset($node['text']) || ! is_string($node['text'])) {
                return null;
            }

            $clean['text'] = $node['text'];
        }

        $attrs = self::sanitiseAttrs($type, $node['attrs'] ?? []);

        if ($attrs === null) {
            return null;
        }

        if ($attrs !== []) {
            $clean['attrs'] = $attrs;
        }

        $marks = self::sanitiseMarks($node['marks'] ?? []);

        if ($marks !== []) {
            $clean['marks'] = $marks;
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $children = [];

            foreach ($node['content'] as $child) {
                $cleanChild = self::sanitiseNode($child);

                if ($cleanChild !== null) {
                    $children[] = $cleanChild;
                }
            }

            $clean['content'] = $children;
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>|null Null means the node itself is invalid.
     */
    private static function sanitiseAttrs(string $type, mixed $attrs): ?array
    {
        if (! is_array($attrs)) {
            return [];
        }

        $allowed = self::NODES[$type];
        $clean = [];

        foreach ($allowed as $name) {
            if (! array_key_exists($name, $attrs)) {
                continue;
            }

            $value = $attrs[$name];

            // See NULLABLE_ATTRS: for these, no value is a value.
            if ($value === null && in_array($name, self::NULLABLE_ATTRS, true)) {
                continue;
            }

            $clean[$name] = match ($name) {
                'level' => in_array($value, self::HEADING_LEVELS, true) ? $value : 2,
                'start' => is_int($value) && $value > 0 ? $value : 1,
                'textAlign' => in_array($value, self::TEXT_ALIGNMENTS, true) ? $value : 'left',
                'colspan', 'rowspan' => self::cellSpan($value),
                'colwidth' => self::sanitiseColumnWidths($value),
                // See NULLABLE_ATTRS: a bad value falls back rather than
                // taking the image with it.
                'side' => in_array($value, self::ASIDE_SIDES, true) ? $value : 'right',
                'size' => in_array($value, self::ASIDE_SIZES, true) ? $value : 'medium',
                // A ULID that is not a ULID cannot resolve to anything, and
                // is the sort of value that ends up interpolated somewhere
                // later. Reject the whole node rather than keep it.
                'ulid' => self::isUlid($value) ? $value : null,
                'ulids' => self::sanitiseUlidList($value),
                // Both halves of a socialEmbed are kept as-is here and
                // checked together after the loop: whether a post id is
                // valid depends on which platform it belongs to, and a rule
                // that sees one attribute at a time cannot ask that.
                'platform', 'postId' => is_string($value) ? $value : null,
                // The default arm below is deliberately unreachable, which
                // makes this the last arm that can match.
                // @phpstan-ignore match.alwaysTrue
                'videoId' => self::isYouTubeId($value) ? $value : null,
                // Unreachable today, but kept: an attribute added to an
                // allow-list without a matching arm here fails closed (drops
                // the node) instead of passing through unchecked.
                default => null,
            };

            if ($clean[$name] === null) {
                // Nullable attrs: drop the key, keep the node. Otherwise the
                // node itself cannot be trusted.
                if (in_array($name, self::NULLABLE_ATTRS, true)) {
                    unset($clean[$name]);

                    continue;
                }

                return null;
            }
        }

        // A socialEmbed's two attributes only mean anything together, which
        // the per-attribute loop above cannot see. Checked here so neither
        // key can arrive without the other, and so an Instagram shortcode
        // cannot be stored under `platform: tiktok` and then interpolated
        // into a tiktok.com URL. Malformed rejects the whole node, exactly as
        // a bad ULID on a fileEmbed does — a broken embed is a hole in the
        // lesson either way, and there is no sensible value to fall back to.
        if ($type === 'socialEmbed' && ! self::isSocialPost($clean['platform'] ?? null, $clean['postId'] ?? null)) {
            return null;
        }

        return $clean;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sanitiseMarks(mixed $marks): array
    {
        if (! is_array($marks)) {
            return [];
        }

        $clean = [];

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! isset($mark['type']) || ! is_string($mark['type'])) {
                continue;
            }

            $type = $mark['type'];

            if (! array_key_exists($type, self::MARKS)) {
                continue;
            }

            if ($type !== 'link') {
                $clean[] = ['type' => $type];

                continue;
            }

            $href = self::sanitiseHref($mark['attrs']['href'] ?? null);

            // A link whose target is refused loses the mark, not the text —
            // the words stay readable, they just stop being a link.
            if ($href !== null) {
                $clean[] = ['type' => 'link', 'attrs' => ['href' => $href]];
            }
        }

        return $clean;
    }

    /**
     * Only schemes that cannot execute. `javascript:` is the obvious one, but
     * `data:` is equally dangerous in an href — it can carry a whole HTML
     * document — and unknown schemes can hand off to a native handler.
     */
    private static function sanitiseHref(mixed $href): ?string
    {
        if (! is_string($href) || $href === '' || mb_strlen($href) > 2048) {
            return null;
        }

        $trimmed = trim($href);

        // Relative links stay in-site. Second slash checked as either slash:
        // browsers normalise `/\evil.example` to `//evil.example` before
        // navigating, so a lone backslash check would miss it.
        if (str_starts_with($trimmed, '/') && ! preg_match('#^/[/\\\\]#', $trimmed)) {
            return $trimmed;
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $trimmed : null;
    }

    /**
     * A cell's colspan or rowspan. Anything that is not a sane positive
     * integer becomes 1 rather than rejecting the cell — a merged cell drawn
     * unmerged is a cosmetic loss; a dropped cell is a hole in the table.
     */
    private static function cellSpan(mixed $value): int
    {
        if (! is_int($value) || $value < 1 || $value > self::MAX_CELL_SPAN) {
            return 1;
        }

        return $value;
    }

    /**
     * The column resizer's pixel widths.
     *
     * A list of positive integers, one per column the cell spans. Anything
     * else is discarded in favour of no explicit widths at all, which renders
     * as an evenly divided table rather than as nothing.
     *
     * @return list<int>|null Null drops the attribute; the node survives.
     */
    private static function sanitiseColumnWidths(mixed $value): ?array
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $widths = [];

        foreach ($value as $width) {
            if (! is_int($width) || $width < 1 || $width > 10000) {
                return null;
            }

            $widths[] = $width;
        }

        return $widths;
    }

    /**
     * @return list<string>|null
     */
    private static function sanitiseUlidList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];

        foreach ($value as $ulid) {
            if (! self::isUlid($ulid)) {
                return null;
            }

            $clean[] = $ulid;
        }

        return $clean;
    }

    private static function isUlid(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $value) === 1;
    }

    private static function isYouTubeId(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1;
    }

    /**
     * Whether a platform/post-id pair is one this site will embed.
     *
     * Both halves together, deliberately: the platform decides which pattern
     * applies, so checking them apart would let a value valid for one be
     * stored against the other.
     */
    private static function isSocialPost(mixed $platform, mixed $postId): bool
    {
        if (! is_string($platform) || ! is_string($postId)) {
            return false;
        }

        $pattern = self::SOCIAL_ID_PATTERNS[$platform] ?? null;

        return $pattern !== null && preg_match($pattern, $postId) === 1;
    }

    /**
     * @param  list<string>  $pieces
     */
    private static function collectText(mixed $node, array &$pieces): void
    {
        if (! is_array($node)) {
            return;
        }

        if (isset($node['text']) && is_string($node['text'])) {
            $pieces[] = $node['text'];
        }

        foreach ($node['content'] ?? [] as $child) {
            self::collectText($child, $pieces);
        }
    }

    /**
     * @param  array<int, string>  $images
     * @param  array<int, string>  $files
     */
    private static function collectReferences(mixed $node, array &$images, array &$files): void
    {
        if (! is_array($node)) {
            return;
        }

        $type = $node['type'] ?? null;

        // The is_string checks are not ceremony: this walks documents that
        // are already in the database, including ones written before a given
        // attribute rule existed. A non-string here would be carried into a
        // whereIn() against the ulid column.
        if ($type === 'fileEmbed' && is_string($node['attrs']['ulid'] ?? null)) {
            $files[] = $node['attrs']['ulid'];
        }

        // An imageAside shows exactly one image, and that reference is what
        // publishes it. Forgetting this line renders the picture for the
        // logged-in owner and 403s it for every student — a failure that is
        // invisible from the admin panel.
        if ($type === 'imageAside' && is_string($node['attrs']['ulid'] ?? null)) {
            $images[] = $node['attrs']['ulid'];
        }

        if ($type === 'imageGallery' && is_array($node['attrs']['ulids'] ?? null)) {
            foreach ($node['attrs']['ulids'] as $ulid) {
                if (is_string($ulid)) {
                    $images[] = $ulid;
                }
            }
        }

        foreach ($node['content'] ?? [] as $child) {
            self::collectReferences($child, $images, $files);
        }
    }
}
