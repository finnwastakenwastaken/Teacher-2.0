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
 * @property string $alt_text
 * @property int|null $width
 * @property int|null $height
 * @property int $size_bytes
 * @property string $mime
 * @property string $original_filename
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Page> $heroForPages
 */
#[Fillable(['ulid', 'path', 'alt_text', 'width', 'height', 'size_bytes', 'mime', 'original_filename'])]
class Image extends Model
{
    use StoredOnPrivateDisk;

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
     * The shape the id-addressed pickers draw a thumbnail from.
     *
     * By id, not ULID, because what these fields write is relational — a
     * page's `hero_image_id` and the three branding settings. The page
     * *editor* addresses media by ULID and never receives this shape.
     *
     * One definition because three screens send it and a fourth
     * (App\Http\Controllers\Admin\MediaSearchController) searches for it, and
     * a picker that renders a selection differently from a search result is
     * the kind of drift nothing would notice.
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
     * Uses that are not body embeds: page banners, and site branding.
     *
     * The hero foreign key is `restrictOnDelete` as well, so the database
     * would refuse that case regardless. This exists to say *which* pages,
     * because a bare constraint violation tells the owner nothing.
     *
     * Branding has no foreign key to lean on — the id lives inside a jsonb
     * value — so this is the only thing standing between deleting an image
     * and a site with a broken logo.
     *
     * @return array<string, array<int, string>>
     */
    protected function extraDependents(): array
    {
        $dependents = [];

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
