<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One site-wide setting.
 *
 * Read through App\Support\SiteSettings, not directly — that class owns the
 * defaults, and a missing row here means "use the default", never "broken".
 *
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property string $key
 * @property mixed $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
