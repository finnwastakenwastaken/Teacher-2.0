<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One file offered for download on one page, tagged with the tracks it is
 * meant for.
 */
#[Fillable(['page_id', 'media_file_id', 'label', 'sort_order'])]
class PageDownload extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $download): void {
            if (blank($download->ulid)) {
                $download->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function educationLevels(): BelongsToMany
    {
        return $this->belongsToMany(EducationLevel::class)->orderBy('sort_order');
    }

    /**
     * What the download card is called. The owner's label wins; otherwise
     * the file's own name, which is at least always something.
     */
    public function displayLabel(): string
    {
        return filled($this->label) ? $this->label : $this->mediaFile->original_filename;
    }

    /**
     * Bump the tally.
     *
     * A raw atomic increment rather than a read-modify-write: thirty students
     * clicking at once must not lose counts to a lost update, and this must
     * not touch updated_at — the tally changing is not the attachment being
     * edited.
     */
    public function recordDownload(): void
    {
        DB::table($this->getTable())
            ->where('id', $this->getKey())
            ->increment('downloads_count');
    }
}
