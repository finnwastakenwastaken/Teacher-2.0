<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Columns below are for PHPStan; keep them in step with the migrations or
 * the analyser misses typos and believes stale ones.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $original_filename
 * @property string|null $declared_mime
 * @property int $total_bytes
 * @property int $chunk_bytes
 * @property int $total_chunks
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['ulid', 'user_id', 'original_filename', 'declared_mime', 'total_bytes', 'chunk_bytes', 'total_chunks', 'expires_at'])]
class MediaUpload extends Model
{
    protected function casts(): array
    {
        return [
            'total_bytes' => 'integer',
            'chunk_bytes' => 'integer',
            'total_chunks' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MediaUpload $upload): void {
            if (blank($upload->ulid)) {
                $upload->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Where this upload's chunks live, relative to the private disk root.
     * Derived entirely from the ULID — no client-supplied value ever reaches
     * a filesystem path.
     */
    public function chunkDirectory(): string
    {
        return config('media.directories.chunks').'/'.$this->ulid;
    }

    /**
     * No null check: `expires_at` is NOT NULL. A guard would imply "never
     * expires" as a fallback, the wrong default for a row whose whole
     * purpose is to be cleaned up.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', Carbon::now());
    }
}
