<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Safe to run on a live install (the installer does). Deliberately creates no
 * user — the starter kit's seeded test@example.com would occupy the single
 * admin slot before the owner reached the claim screen. The only account
 * comes from the claim screen or `php artisan admin:seed`.
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
