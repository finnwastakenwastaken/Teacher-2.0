<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['from_path', 'redirectable_type', 'redirectable_id'])]
class SlugRedirect extends Model
{
    public function redirectable(): MorphTo
    {
        return $this->morphTo();
    }
}
