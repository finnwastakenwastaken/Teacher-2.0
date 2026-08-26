<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property int $page_id
 * @property string $referenceable_type
 * @property int $referenceable_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Page $page
 * @property-read Image|MediaFile|null $referenceable
 */
#[Fillable(['page_id', 'referenceable_type', 'referenceable_id'])]
class PageMediaReference extends Model
{
    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
