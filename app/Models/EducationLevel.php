<?php

namespace App\Models;

use App\Exceptions\DependentRecordsExistException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An education track a download can be tagged with (VMBO-T, HAVO, VWO, …).
 *
 * Seeded rather than hardcoded — see the migration. Nothing may branch on a
 * particular level existing.
 */
#[Fillable(['name', 'slug', 'sort_order'])]
class EducationLevel extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $level): void {
            $count = $level->pageDownloads()->count();

            if ($count > 0) {
                // Deletes block and report rather than cascading. Cascading
                // here would silently strip the level tag off every download
                // carrying it, leaving files listed under no track at all —
                // data loss that looks like a rendering bug. The admin panel
                // offers merging into another level instead.
                throw new DependentRecordsExistException(
                    "Dit niveau is nog gekoppeld aan {$count} download(s) en kan niet worden verwijderd. "
                    .'Voeg het eerst samen met een ander niveau.'
                );
            }
        });
    }

    public function pageDownloads(): BelongsToMany
    {
        return $this->belongsToMany(PageDownload::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
