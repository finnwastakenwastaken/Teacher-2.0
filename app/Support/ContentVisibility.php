<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Topic;

/**
 * Is this item allowed to be *found*, as opposed to opened? Search and the
 * sitemap both ask this and must agree — search used to filter only its own
 * `is_hidden`, so hiding a topic left every page underneath fully
 * searchable, which is exactly what hiding a subject is meant to prevent.
 * Discoverability is not access: a hidden page still renders at its direct
 * URL (see MediaAccess); this class only decides what may be *listed* to
 * someone without the link.
 */
class ContentVisibility
{
    /**
     * Hidden covers descendants, not just the item itself.
     *
     * A page under a hidden topic is reachable by direct link, so surfacing
     * it in search results or writing its URL into the sitemap routes around
     * the only thing hiding does.
     */
    public static function isDiscoverable(Topic|Page $node): bool
    {
        if ($node->is_hidden) {
            return false;
        }

        $ancestor = $node instanceof Page ? $node->topic : $node->parent;

        while ($ancestor !== null) {
            if ($ancestor->is_hidden) {
                return false;
            }

            $ancestor = $ancestor->parent;
        }

        return true;
    }

    /**
     * Discoverable and not behind a password — asked without reference to
     * who is looking. This is the sitemap's question: a sitemap is public
     * and cacheable, so asking AccessControl::allows() instead would write
     * every protected path into the file the moment the owner loaded it
     * logged in. Search does NOT use this — a protected page should appear
     * once the visitor has unlocked it, so search pairs isDiscoverable()
     * with allows() directly instead.
     */
    public static function isPubliclyListable(Topic|Page $node): bool
    {
        return self::isDiscoverable($node)
            && AccessControl::effectivePasswordId($node) === null;
    }
}
