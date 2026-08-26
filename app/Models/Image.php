<?php

namespace App\Models;

use App\Models\Concerns\StoredOnPrivateDisk;
use App\Support\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones. The three `*_count`
 * lines are the exception: aggregates present only when a query asked for
 * them with withCount(), which the media library screen uses to derive
 * "shown on a page" and "offered as a download".
 *
 * @property int $id
 * @property string $ulid
 * @property string $path
 * @property string $alt_text
 * @property int|null $width
 * @property int|null $height
 * @property int $size_bytes
 * @property string $mime
 * @property string $original_filename
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Page> $heroForPages
 * @property-read Collection<int, PageDownload> $pageDownloads
 * @property-read int|null $page_references_count
 * @property-read int|null $page_downloads_count
 * @property-read int|null $hero_for_pages_count
 */
#[Fillable(['ulid', 'path', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename'])]
class Image extends Model
{
    use StoredOnPrivateDisk;

    public const SVG_MIME = 'image/svg+xml';

    /**
     * SVG is XML and can carry script.
     *
     * It is loaded through <img>, which never executes it, but a visitor
     * navigating straight at the URL would render it as a document in this
     * origin. Both routes that can hand out an image answer that the same
     * way — never inline, plus the sandbox below — and asking here rather
     * than restating the MIME at each of them is what keeps the two in step
     * now that an image can also be a download.
     */
    public function isSvg(): bool
    {
        return $this->mime === self::SVG_MIME;
    }

    /**
     * @return array<string, string>
     */
    public static function svgSandboxHeaders(): array
    {
        return ['Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox"];
    }

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Pages using this image as their banner.
     *
     * @return HasMany<Page, $this>
     */
    public function heroForPages(): HasMany
    {
        return $this->hasMany(Page::class, 'hero_image_id');
    }

    /**
     * Pages that offer this image in their downloads section.
     *
     * A poster or a scanned worksheet is an `images` row — the library is
     * decided by sniffing the bytes — so this mirrors
     * MediaFile::pageDownloads(), and App\Support\MediaAccess reads both
     * through one arm rather than asking which library it is holding.
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
     * The shape the id-addressed pickers draw a thumbnail from: by id, not
     * ULID, because these fields (a page's `hero_image_id`, the branding
     * settings) write a foreign key — the page editor addresses media by
     * ULID and never receives this shape. One definition because three
     * screens and MediaSearchController all need to agree on it.
     *
     * @return array{id: int, alt: string, filename: string, url: string}
     */
    public function toPickerOption(): array
    {
        return [
            'id' => $this->id,
            'alt' => $this->alt_text,
            'filename' => $this->original_filename,
            'url' => route('images.show', $this),
        ];
    }

    /**
     * The shape the page editor's node views draw an embed from.
     *
     * ULID-addressed like toPageMediaItem() below, and separate from it
     * because the two answer different questions: that one is what a reader
     * is shown, this one is what the editor needs to draw a block the owner
     * is holding. One definition because the edit payload and the version
     * preview both send it, and an embed that resolved on one screen and not
     * the other reads as "these images no longer exist".
     *
     * @return array{ulid: string, alt_text: string, original_filename: string, url: string}
     */
    public function toEditorLibraryEntry(): array
    {
        return [
            'ulid' => $this->ulid,
            'alt_text' => $this->alt_text,
            'original_filename' => $this->original_filename,
            'url' => route('images.show', $this),
        ];
    }

    /**
     * The shape components/content/rich-text.tsx looks an embed up in.
     *
     * ULID-addressed, unlike toPickerOption() above — a document names its
     * media by ULID and must never learn an id. One definition because two
     * things build this map from two different starting points: the public
     * page from its derived `page_media_references` rows, and the version
     * preview in the page editor from the stored document itself, which has
     * no reference rows of its own. A preview that described an image
     * differently from the page would be a preview of something else.
     *
     * @return array{type: string, url: string, alt: string, width: int|null, height: int|null}
     */
    public function toPageMediaItem(): array
    {
        return [
            'type' => 'image',
            'url' => route('images.show', $this),
            'alt' => $this->alt_text,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * Uses that are not body embeds: downloads, page banners, and branding.
     * The download and hero FKs are `restrictOnDelete` too, but this says
     * *which* pages rather than a bare constraint violation. Branding has no
     * FK at all — the id lives inside a jsonb value — so this is the only
     * guard against a deleted logo.
     *
     * @return array<string, array<int, string>>
     */
    protected function extraDependents(): array
    {
        $dependents = [];

        $offeredOn = $this->pageDownloads()
            ->with('page:id,title')
            ->get()
            ->map(fn (PageDownload $download) => $download->page->title)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($offeredOn !== []) {
            $dependents[__('admin.downloads.offered_on')] = $offeredOn;
        }

        $pages = $this->heroForPages()->pluck('title')->all();

        if ($pages !== []) {
            $dependents[__('admin.dependents.banner_on')] = $pages;
        }

        if (in_array($this->id, SiteSettings::brandingImageIds(), true)) {
            $dependents[__('admin.dependents.used_by')] = [__('admin.dependents.site_settings')];
        }

        return $dependents;
    }
}
