<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the generated icon catalogue.
 *
 * Read through App\Support\IconCatalogue, not directly — that class owns
 * name normalisation, which is what keeps icon values stored before the
 * catalogue existed (a bare `atom`) working.
 */
#[Fillable(['key', 'library', 'name', 'nodes'])]
class Icon extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
        ];
    }
}
