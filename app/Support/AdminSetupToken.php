<?php

namespace App\Support;

/**
 * Optional second factor on the first-run claim screen.
 *
 * The claim window is first-come-first-served by design (see the technical reference) —
 * whoever loads the site first can claim the only admin account. Setting
 * ADMIN_SETUP_TOKEN closes that window: the claim form then also requires
 * this value, so a stranger who finds the URL before the owner does cannot
 * take the account.
 */
class AdminSetupToken
{
    public static function isConfigured(): bool
    {
        return filled(config('admin.setup_token'));
    }

    /**
     * Constant-time comparison so a wrong guess of the right length can't be
     * distinguished from any other wrong guess by response timing.
     */
    public static function matches(?string $candidate): bool
    {
        if (! static::isConfigured()) {
            return true;
        }

        return hash_equals((string) config('admin.setup_token'), (string) $candidate);
    }
}
