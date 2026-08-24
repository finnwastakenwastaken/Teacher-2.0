<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Topic;

/**
 * Is this item allowed to be *found*, as opposed to opened?
 *
 * Two features ask this question — full-text search and the sitemap — and
 * before this class existed they answered it differently. The sitemap walked
 * up the tree; search filtered `pages.is_hidden` and stopped there. So hiding
 * a topic removed it from navigation while every page underneath stayed fully
 * searchable, title and snippet included, which is precisely what hiding a
 * retired subject is meant to prevent. Both files carried a long comment
 * explaining their reasoning and the reasoning still drifted apart. One
 * function is the fix; the comments below are the reasoning, once.
 *
 * Discoverability is not access. A hidden page still renders at its direct
 * URL — see App\Support\MediaAccess on why that is deliberate — and this
 * class has no opinion about that. It only decides whether something may be
 * *listed* to someone who did not already have the link.
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
     * Discoverable, and not behind a password — asked without reference to
     * who is looking.
     *
     * This is the sitemap's question, and the distinction from
     * AccessControl::allows() is the whole point. `allows()` answers "may
     * *this visitor* open it", honouring the admin session and unlock
     * cookies. A sitemap is not about a visitor: it is a public document that
     * anything may fetch, cache and pass on, so asking `allows()` would write
     * every protected path into the file the moment the owner loaded it while
     * logged in.
     *
     * Search deliberately does NOT use this one. There, a protected page
     * *should* appear once the visitor has unlocked it — they have already
     * proved they may read it, and hiding it from their own search would be
     * theatre. Search therefore pairs isDiscoverable() with allows().
     */
    public static function isPubliclyListable(Topic|Page $node): bool
    {
        return self::isDiscoverable($node)
            && AccessControl::effectivePasswordId($node) === null;
    }
}
