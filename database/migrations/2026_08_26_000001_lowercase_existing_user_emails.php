<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs an account that can no longer log in.
 *
 * `lowercase_usernames` is on in config/fortify.php, so a login canonicalises
 * the address before looking it up, while nothing used to lower-case it on the
 * way in. An owner who claimed as `Teacher@school.nl` was therefore locked out
 * of the only account the site has, with `admin:reset-password` unable to help
 * — the password was never what failed. `User::email()` stops it happening
 * again; this frees whoever it already happened to, who cannot be reached by a
 * code change alone.
 *
 * Safe as a blanket update precisely because this site has exactly one account
 * (§3): there is no second row for a lower-cased address to collide with.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->update(['email' => DB::raw('lower(email)')]);
    }

    /**
     * Deliberately irreversible. The original capitalisation is not recorded
     * anywhere, and restoring it would re-lock the account this migration
     * exists to unlock.
     */
    public function down(): void {}
};
