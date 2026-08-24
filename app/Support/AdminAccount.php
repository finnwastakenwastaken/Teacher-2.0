<?php

namespace App\Support;

use App\Exceptions\AdminAlreadyClaimedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralises the "exactly one admin account, ever" invariant.
 *
 * Two independent paths can create the account: the browser claim screen
 * (App\Http\Controllers\Auth\ClaimController) and the boot-time
 * `admin:seed` console command. Both call claim() so the existence check and
 * the insert happen atomically.
 *
 * Without the advisory lock, two near-simultaneous callers (two browser tabs,
 * or a browser claim racing the boot-time seed command) could both pass an
 * exists() check before either had inserted a row, producing two accounts on
 * a site that must only ever have one. A plain database transaction does not
 * prevent this by itself — there is no row to lock until after the first
 * insert — so this uses a PostgreSQL session-level advisory lock instead,
 * which serialises on the key regardless of whether any row exists yet.
 * (Tests run against real PostgreSQL specifically so this path is exercised —
 * see phpunit.xml.)
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
     * The three keys are required, but the shape is written loosely on
     * purpose: both callers arrive from a validator, which is typed as
     * returning a plain array. Declaring the exact shape here only moved the
     * complaint to the call site, where the guarantee is the validation rules
     * rather than anything the analyser can see.
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
