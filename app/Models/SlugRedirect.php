<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
