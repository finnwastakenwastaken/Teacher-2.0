<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

/**
 * A starting set of Dutch secondary tracks, in the order a teacher lists them.
 *
 * These are a default, not a fixture. The owner can rename, reorder, add and
 * remove them from the admin panel, so nothing may branch on any of these
 * existing. Idempotent, so re-running the seeder on a live install does not
 * resurrect a level the owner deliberately deleted or undo a rename.
 */
class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'VMBO-BK', 'slug' => 'vmbo-bk'],
            ['name' => 'VMBO-T', 'slug' => 'vmbo-t'],
            ['name' => 'HAVO', 'slug' => 'havo'],
            ['name' => 'VWO', 'slug' => 'vwo'],
        ];

        if (EducationLevel::query()->exists()) {
            return;
        }

        foreach ($levels as $index => $level) {
            EducationLevel::query()->create([...$level, 'sort_order' => $index]);
        }
    }
}
