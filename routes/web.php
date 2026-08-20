<?php

use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UnlockController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name('home');

// Gated media. Public routes by URL, but every request is authorised by
// App\Support\MediaAccess before a single byte is released — this is the only
// way uploaded files are ever served. See the technical reference.
Route::get('images/{image}', [MediaController::class, 'image'])->name('images.show');
Route::get('media/{mediaFile}', [MediaController::class, 'file'])->name('media.show');

// A file offered in a page's downloads section. Same authorisation as the
// route above, through the same App\Support\MediaAccess decision; separate
// only so the per-attachment tally can be kept.
Route::get('downloads/{pageDownload}', [DownloadController::class, 'show'])->name('downloads.show');

// Dutch public URL, like the rest of the visitor-facing site.
Route::get('zoeken', [SearchController::class, 'show'])->name('search');

// What crawlers are told. Both are generated rather than static files: the
// Sitemap: line needs an absolute URL, and behind the tunnel the domain is
// whatever Cloudflare forwards. English names because they are protocol,
// not copy — a crawler looks for these exact paths.
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('sitemap.xml', [SitemapController::class, 'show'])->name('sitemap');

// Entering a password for protected content. Takes the path the visitor was
// trying to reach rather than a password id, so it cannot be used to probe
// which password guards what. Rate limited per IP per password inside.
Route::post('unlock', [UnlockController::class, 'store'])->name('unlock.store');

// No 'verified' middleware anywhere in this application. Email verification is
// disabled (see config/fortify.php), so the single admin account never has a
// verified_at timestamp — gating on it would lock the only user out of the
// only account.
Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';

// Public content catch-all. Must be registered last: Laravel matches routes
// in registration order, so every specific route above (including the admin
// ones) wins over this wildcard.
Route::get('/{path}', [ContentController::class, 'show'])->where('path', '.*')->name('content.show');
