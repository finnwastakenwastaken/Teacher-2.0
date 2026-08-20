<?php

namespace App\Models;

use App\Models\Concerns\StoredOnPrivateDisk;
use App\Support\SiteSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     */
    public function heroForPages(): HasMany
    {
        return $this->hasMany(Page::class, 'hero_image_id');
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
     * @return array<string, list<string>>
     */
    protected function extraDependents(): array
    {
        $dependents = [];

        $pages = $this->heroForPages()->pluck('title')->all();

        if ($pages !== []) {
            $dependents['Bannerafbeelding op'] = $pages;
        }

        if (in_array($this->id, SiteSettings::brandingImageIds(), true)) {
            $dependents['In gebruik bij'] = ['Instellingen van de site'];
        }

        return $dependents;
    }
}
