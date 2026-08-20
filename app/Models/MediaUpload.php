<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', Carbon::now());
    }
}
