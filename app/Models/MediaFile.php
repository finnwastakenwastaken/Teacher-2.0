<?php

namespace App\Models;

use App\Models\Concerns\StoredOnPrivateDisk;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ulid', 'path', 'kind', 'mime', 'size_bytes', 'original_filename'])]
class MediaFile extends Model
{
    use StoredOnPrivateDisk;

    public const KIND_DOCUMENT = 'document';

    public const KIND_VIDEO = 'video';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_DOCUMENT);
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_VIDEO);
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    /**
     * Pages that offer this file in their downloads section.
     *
     * Distinct from pageReferences(), which is derived from page bodies and
     * rebuilt on every save. These rows are authored and outlive body edits.
     */
    public function pageDownloads(): HasMany
    {
        return $this->hasMany(PageDownload::class);
    }

    protected function extraDependents(): array
    {
        $pages = $this->pageDownloads()
            ->with('page:id,title')
            ->get()
            ->map(fn (PageDownload $download) => $download->page?->title)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $pages === [] ? [] : ['Aangeboden als download op' => $pages];
    }
}
