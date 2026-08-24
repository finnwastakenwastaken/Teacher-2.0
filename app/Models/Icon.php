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
 *
 * The columns, for static analysis.
 *
 * Eloquent resolves these at runtime, so nothing here changes behaviour —
 * but without them every `$model->column` is an undefined property to
 * PHPStan, and a genuine typo becomes indistinguishable from a hundred
 * false ones. Keep in step with the migrations: a column added without a
 * line here is invisible to the analyser, and a line here without a column
 * is a lie it will believe.
 *
 * @property string $key
 * @property string $library
 * @property string $name
 * @property list<array{0: string, 1: array<string, string>}> $nodes
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
