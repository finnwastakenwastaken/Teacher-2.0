<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One file offered for download on one page, tagged with the tracks it is
 * meant for.
 *
 * The file is either a media file or an image, never both and never neither —
 * a poster or a scanned worksheet is an `images` row, because the library a
 * file lands in is decided by sniffing its bytes. Two nullable foreign keys
 * rather than a polymorphic pair, so both still restrict on delete; a database
 * CHECK (page_downloads_exactly_one_source) is what makes "exactly one" true
 * rather than hoped for. offeredMedia() is the single place that resolves it.
 *
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property string $ulid
 * @property int $page_id
 * @property int|null $media_file_id
 * @property int|null $image_id
 * @property string|null $label
 * @property int $sort_order
 * @property int $downloads_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 * @property-read MediaFile|null $mediaFile
 * @property-read Image|null $image
 * @property-read Collection<int, EducationLevel> $educationLevels
 */
#[Fillable(['page_id', 'media_file_id', 'image_id', 'label', 'sort_order'])]
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

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    /** @return BelongsTo<Image, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    /** @return BelongsToMany<EducationLevel, $this> */
    public function educationLevels(): BelongsToMany
    {
        return $this->belongsToMany(EducationLevel::class)->orderBy('sort_order');
    }

    /**
     * Whichever library this attachment offers, resolved in one place.
     *
     * Every caller wants the same four things — path, mime, filename, size —
     * and both models carry all four, so the arms only have to be told apart
     * once. Doing it at each call site instead is how one of them ends up
     * checking only for a media file and quietly serving nothing.
     */
    public function offeredMedia(): Image|MediaFile
    {
        $media = $this->mediaFile ?? $this->image;

        if ($media === null) {
            // Unreachable while the CHECK constraint stands, and a loud
            // failure rather than a silent null if it ever does not: an
            // attachment that offers nothing is a broken download card, and
            // finding that out here beats finding it out in a stream.
            throw new RuntimeException("Page download {$this->ulid} points at neither library.");
        }

        return $media;
    }

    /**
     * What the download card is called. The owner's label wins; otherwise
     * the file's own name, which is at least always something.
     */
    public function displayLabel(): string
    {
        return filled($this->label) ? $this->label : $this->offeredMedia()->original_filename;
    }

    /**
     * What sort of thing this is, for the icon on the card.
     *
     * `images` has no `kind` column and does not need one — the library it
     * lives in is the answer. The front end's union widens by exactly this
     * one member (resources/js/types/media.ts).
     */
    public function kind(): string
    {
        $media = $this->offeredMedia();

        return $media instanceof Image ? 'image' : $media->kind;
    }

    /**
     * A raw atomic increment, not read-modify-write: concurrent clicks must
     * not lose counts, and this must not touch updated_at — the tally
     * changing is not the attachment being edited.
     */
    public function recordDownload(): void
    {
        DB::table($this->getTable())
            ->where('id', $this->getKey())
            ->increment('downloads_count');
    }
}
