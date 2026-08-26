<?php

use App\Http\Controllers\Auth\ClaimController;
use Illuminate\Support\Facades\Route;

/*
 * The first-run claim screen. `guest` bounces a logged-in admin to the
 * dashboard; `admin.unclaimed` bounces a guest away once claimed by anyone —
 * together guaranteeing exactly one account ever gets created. Future
 * admin routes under /admin inherit this gate for free via the login
 * redirect in FortifyServiceProvider::configureViews().
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
