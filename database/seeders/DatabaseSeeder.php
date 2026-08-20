<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe to run on a live install, and the installer does run it.
 *
 * Deliberately creates no user. The starter kit seeded a test@example.com
 * account here; on a real deploy that would occupy the single admin slot
 * before the owner ever reached the claim screen, locking them out of their
 * own site behind a publicly known e-mail address. The only account is
 * created by the claim screen or by `php artisan admin:seed` — see
 * The technical reference.
 */
class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: models here rely on their own creating hooks
    // (generated ULIDs, derived columns) and silently suppressing those
    // would seed rows that are invalid in ways nothing checks.
    public function run(): void
    {
        $this->call([
            EducationLevelSeeder::class,
        ]);
    }
}
