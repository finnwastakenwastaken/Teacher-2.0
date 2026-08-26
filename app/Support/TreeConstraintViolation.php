<?php

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * Turns a database refusal into something the owner can act on — but only
 * the two violations the tree triggers raise deliberately; everything else
 * stays the 500 it is, rather than every QueryException (connection
 * failure, disk-full, unrelated constraint) showing up as a slug error.
 * Recognition is by SQLSTATE, never message text — the messages are Dutch
 * and live in a migration, so matching on them breaks silently on a reword.
 * See 2026_08_21_000002_give_tree_triggers_stable_error_codes.
 */
class TreeConstraintViolation
{
    /** Topic depth cap exceeded. */
    private const DEPTH = 'TD001';

    /** Sibling slug already taken, across topics *and* pages. */
    private const SLUG = 'TS001';

    /**
     * The message to show, or null if this is not a violation we anticipated
     * — in which case the caller must rethrow rather than dress it up.
     */
    public static function message(QueryException $e): ?string
    {
        // The match picks a key and the translation happens once, below:
        // __() is typed as array|string|null because a key can resolve to a
        // whole group, so translating inside each arm would mean casting in
        // each arm too.
        $key = match (self::sqlState($e)) {
            self::DEPTH => 'admin.topics.max_depth',
            self::SLUG => 'admin.fields.slug_taken',
            // 23505 is unique_violation: the application-level check in
            // App\Rules\SiblingSlugIsUnique normally catches this first, so
            // reaching here means two requests raced.
            '23505' => 'admin.fields.slug_taken',
            default => null,
        };

        return $key === null ? null : (string) __($key);
    }

    private static function sqlState(QueryException $e): ?string
    {
        // errorInfo[0] is the SQLSTATE. PDO populates it; a wrapped exception
        // from somewhere else may not, hence the guard.
        $state = $e->errorInfo[0] ?? null;

        return is_string($state) ? $state : null;
    }
}
