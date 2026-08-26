<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property string $from_path
 * @property string $redirectable_type
 * @property int $redirectable_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Topic|Page|null $redirectable
 */
#[Fillable(['from_path', 'redirectable_type', 'redirectable_id'])]
class SlugRedirect extends Model
{
    /** @return MorphTo<Model, $this> */
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
