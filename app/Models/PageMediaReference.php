<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['page_id', 'referenceable_type', 'referenceable_id'])]
class PageMediaReference extends Model
{
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
