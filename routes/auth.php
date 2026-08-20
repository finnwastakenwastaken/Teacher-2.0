<?php

use App\Http\Controllers\Auth\ClaimController;
use Illuminate\Support\Facades\Route;

/*
 * The first-run claim screen. `guest` bounces an already-logged-in admin back
 * to the dashboard; `admin.unclaimed` bounces a guest away once the account
 * has been claimed by anyone. Together they guarantee this ever creates
 * exactly one account — see the technical reference and App\Support\AdminAccount.
 *
 * Future admin-panel routes belong under the same /admin prefix. They will
 * inherit this same gate for free: the `auth` middleware redirects a guest to
 * `login`, and the login view itself redirects to here when unclaimed (see
 * FortifyServiceProvider::configureViews()).
 */
Route::middleware(['guest', 'admin.unclaimed'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('claim', [ClaimController::class, 'create'])->name('claim.create');

        Route::post('claim', [ClaimController::class, 'store'])
            ->middleware('throttle:claim')
            ->name('claim.store');
    });
