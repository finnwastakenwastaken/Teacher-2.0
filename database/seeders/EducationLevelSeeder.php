<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

/**
 * A starting set of Dutch secondary tracks — a default, not a fixture; the
 * owner can rename/reorder/remove them, so nothing may branch on any
 * existing. Idempotent, so re-running on a live install can't resurrect a
 * deleted level or undo a rename.
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
