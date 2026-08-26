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
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones. The two `*_count` lines
 * are the exception: aggregates present only when a query asked for them
 * with withCount().
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
 * @property-read int|null $page_references_count
 * @property-read int|null $page_downloads_count
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
     * The shape the page editor's node views draw an embed from.
     *
     * See App\Models\Image::toEditorLibraryEntry(). The numeric id travels
     * with a file because the same list feeds the downloads section, whose
     * attach endpoint is a relational write; the editor itself addresses
     * embeds by ULID only.
     *
     * @return array{id: int, ulid: string, kind: string, mime: string, size_bytes: int, original_filename: string, url: string}
     */
    public function toEditorLibraryEntry(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'kind' => $this->kind,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'original_filename' => $this->original_filename,
            'url' => route('media.show', $this),
        ];
    }

    /**
     * The shape components/content/rich-text.tsx looks an embed up in.
     *
     * See App\Models\Image::toPageMediaItem() for why this is one definition:
     * the public page builds the map from its derived reference rows, the
     * page editor's version preview builds it from the stored document, and
     * the two must describe the same file the same way.
     *
     * @return array{type: string, url: string, kind: string, mime: string, filename: string, sizeBytes: int}
     */
    public function toPageMediaItem(): array
    {
        return [
            'type' => 'file',
            'url' => route('media.show', $this),
            'kind' => $this->kind,
            'mime' => $this->mime,
            'filename' => $this->original_filename,
            'sizeBytes' => $this->size_bytes,
        ];
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
