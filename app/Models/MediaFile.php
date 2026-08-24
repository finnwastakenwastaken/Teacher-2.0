<?php

namespace App\Models;

use App\Models\Concerns\StoredOnPrivateDisk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The columns, for static analysis.
 *
 * Eloquent resolves these at runtime, so nothing here changes behaviour —
 * but without them every `$model->column` is an undefined property to
 * PHPStan, and a genuine typo becomes indistinguishable from a hundred
 * false ones. Keep in step with the migrations: a column added without a
 * line here is invisible to the analyser, and a line here without a column
 * is a lie it will believe.
 *
 * @property int $id
 * @property string $ulid
 * @property string $path
 * @property string $kind
 * @property string $mime
 * @property int $size_bytes
 * @property string $original_filename
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, PageDownload> $pageDownloads
 */
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_DOCUMENT);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
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
     *
     * @return HasMany<PageDownload, $this>
     */
    public function pageDownloads(): HasMany
    {
        return $this->hasMany(PageDownload::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function extraDependents(): array
    {
        $pages = $this->pageDownloads()
            ->with('page:id,title')
            ->get()
            ->map(fn (PageDownload $download) => $download->page->title)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $pages === [] ? [] : [__('admin.downloads.offered_on') => $pages];
    }
}
