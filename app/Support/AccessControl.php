<?php

namespace App\Support;

use App\Models\AccessPassword;
use App\Models\Page;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Which password guards a piece of content, and whether this visitor has
 * already satisfied it.
 *
 * Two rules, and they are separate on purpose:
 *
 *   - *resolution* — a page is guarded by its own password if it has one,
 *     otherwise by the nearest ancestor topic that has one. Nearest wins, so
 *     a single page inside a protected branch can carry a different password
 *     without the branch's leaking into it.
 *   - *unlocking* — proving one password unlocks everything that password
 *     guards, everywhere on the site, because the record is what is shared
 *     with a class, not the page.
 *
 * The cookie is bound to a fingerprint of the current hash, not just to the
 * record id. Changing a password therefore invalidates every cookie issued
 * under the old one on the next request — which is the whole point of being
 * able to change it after it has been passed around.
 */
class AccessControl
{
    /**
     * The id of the password guarding this node, or null if it is open.
     *
     * Deliberately uncached. The walk is at most three levels (the tree is
     * capped at depth 2) and this answer decides whether material is shown,
     * so a stale memo is a disclosure bug rather than a slow page. If this
     * ever shows up in a profile, denormalise it into a column maintained by
     * a trigger — do not add a static cache.
     */
    public static function effectivePasswordId(Topic|Page $node): ?int
    {
        if ($node->access_password_id !== null) {
            return (int) $node->access_password_id;
        }

        $topic = $node instanceof Page ? $node->topic : $node->parent;

        while ($topic !== null) {
            if ($topic->access_password_id !== null) {
                return (int) $topic->access_password_id;
            }

            $topic = $topic->parent;
        }

        return null;
    }

    /**
     * Whether this visitor may see this node's content right now.
     *
     * The single admin sees everything — they are the one who set the
     * passwords, and being locked out of previewing their own material would
     * just teach them to remove the protection.
     */
    public static function allows(Topic|Page $node, Request $request): bool
    {
        if ($request->user() !== null) {
            return true;
        }

        $passwordId = static::effectivePasswordId($node);

        return $passwordId === null || static::isUnlocked($passwordId, $request);
    }

    public static function isUnlocked(int $passwordId, Request $request): bool
    {
        $presented = $request->cookie(static::cookieName($passwordId));

        if (! is_string($presented) || $presented === '') {
            return false;
        }

        $password = AccessPassword::query()->find($passwordId);

        if ($password === null) {
            return false;
        }

        return hash_equals(static::fingerprint($password), $presented);
    }

    /**
     * The cookie proving this password was entered.
     *
     * Not httpOnly-exempt, not readable by script, and it carries no
     * information about who the visitor is — it is a fingerprint of a hash
     * the server already holds. Nothing about the visitor is recorded
     * anywhere as a result of unlocking.
     */
    public static function unlockCookie(AccessPassword $password): SymfonyCookie
    {
        return Cookie::make(
            name: static::cookieName($password->id),
            value: static::fingerprint($password),
            minutes: (int) config('access.unlock_days') * 24 * 60,
            httpOnly: true,
        );
    }

    /**
     * Derived from the stored hash, so it changes whenever the password does.
     *
     * Deliberately not the hash itself: the cookie is encrypted at rest by
     * Laravel, but a bcrypt hash is still the crown jewel and there is no
     * reason to hand a copy of it to every visitor.
     */
    private static function fingerprint(AccessPassword $password): string
    {
        return substr(hash('sha256', $password->password_hash), 0, 32);
    }

    private static function cookieName(int $passwordId): string
    {
        return 'unlock_'.$passwordId;
    }
}
