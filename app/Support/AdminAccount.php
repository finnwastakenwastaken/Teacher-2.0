<?php

namespace App\Support;

use App\Exceptions\AdminAlreadyClaimedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the "exactly one admin account, ever" invariant. Two paths can
 * create it — the browser claim screen and boot-time `admin:seed` — and both
 * call claim() so the exists-check and insert happen atomically. A plain
 * transaction can't prevent the race (no row to lock until after the first
 * insert), so this uses a PostgreSQL advisory lock, which serialises on the
 * key regardless of whether any row exists yet. Tests run against real
 * PostgreSQL specifically so this path is exercised.
 */
class AdminAccount
{
    /**
     * Arbitrary fixed key for the advisory lock. Any two processes calling
     * pg_advisory_xact_lock with this same key serialise against each other;
     * the lock releases automatically when the transaction ends.
     */
    private const LOCK_KEY = 847223119;

    public static function exists(): bool
    {
        return User::query()->exists();
    }

    /**
     * Shape is loose on purpose: both callers pass a validator's plain-array
     * output, and declaring an exact shape here would just move the
     * complaint to the call site.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws AdminAlreadyClaimedException
     */
    public static function claim(array $attributes): User
    {
        return DB::transaction(function () use ($attributes) {
            DB::statement('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            if (static::exists()) {
                throw new AdminAlreadyClaimedException;
            }

            return User::create($attributes);
        });
    }
}
