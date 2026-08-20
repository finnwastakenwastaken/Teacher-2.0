<?php

use App\Http\Controllers\Admin\AccessPasswordController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\EducationLevelController;
use App\Http\Controllers\Admin\IconController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PageDownloadController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Admin\UploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // The *site's* settings — branding and the homepage introduction. The
    // account's settings live in routes/settings.php.
    Route::get('instellingen', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
    Route::put('instellingen', [SiteSettingsController::class, 'update'])->name('site-settings.update');

    // Icon search for the picker. Read-only JSON, and the only place the
    // ~15,000-icon catalogue is ever queried from the browser.
    Route::get('icons', [IconController::class, 'index'])->name('icons.index');

    // Drag-and-drop ordering. These reorder siblings and nothing else — a
    // move between parents still goes through the edit form, which is where
    // the depth cap, slug uniqueness and 301 redirects are handled. Declared
    // before the resource routes so `topics/reorder` is not swallowed by
    // `topics/{topic}`.
    Route::post('topics/reorder', [TopicController::class, 'reorder'])->name('topics.reorder');
    Route::post('pages/reorder', [PageController::class, 'reorder'])->name('pages.reorder');
    Route::post('levels/reorder', [EducationLevelController::class, 'reorder'])->name('levels.reorder');

    Route::resource('topics', TopicController::class)->except('show');
    Route::resource('pages', PageController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
    Route::put('pages/{page}/content', [PageController::class, 'updateContent'])->name('pages.content.update');
    Route::post('pages/{page}/duplicate', [PageController::class, 'duplicate'])->name('pages.duplicate');

    // Named, reusable access passwords. Applied to a topic branch or a page
    // from the topic/page forms; managed as records here.
    Route::resource('passwords', AccessPasswordController::class)->only(['index', 'store', 'update', 'destroy']);

    // Education levels. Seeded, but entirely the owner's to reshape — a
    // level in use can only be removed by merging it into another one.
    Route::resource('levels', EducationLevelController::class)->only(['index', 'store', 'update', 'destroy']);

    // A page's downloads section. Attaching a file here publishes it; see
    // App\Support\MediaAccess.
    Route::post('pages/{page}/downloads', [PageDownloadController::class, 'store'])->name('pages.downloads.store');
    Route::patch('downloads/{pageDownload}', [PageDownloadController::class, 'update'])->name('downloads.update');
    Route::delete('downloads/{pageDownload}', [PageDownloadController::class, 'destroy'])->name('downloads.destroy');

    // Back-ups. The download streams through its own `internal` nginx
    // location, never the media one — an archive is the whole database, and
    // the rules that govern media let anonymous visitors through for anything
    // a public page shows. See docker/nginx/app.conf.
    //
    // `{name}` is matched loosely here and validated hard by
    // App\Services\BackupArchive::resolve(); the default segment pattern
    // would reject the dots in `.tar.gz`.
    Route::get('back-ups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('back-ups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('back-ups/{name}', [BackupController::class, 'download'])
        ->where('name', '[A-Za-z0-9._-]+')
        ->name('backups.download');
    Route::delete('back-ups/{name}', [BackupController::class, 'destroy'])
        ->where('name', '[A-Za-z0-9._-]+')
        ->name('backups.destroy');

    Route::get('media', [MediaLibraryController::class, 'index'])->name('media.index');
    Route::patch('media/images/{image}', [MediaLibraryController::class, 'updateImage'])->name('media.images.update');
    Route::delete('media/images/{image}', [MediaLibraryController::class, 'destroyImage'])->name('media.images.destroy');
    Route::delete('media/files/{mediaFile}', [MediaLibraryController::class, 'destroyFile'])->name('media.files.destroy');

    // Chunked upload. Throttled generously rather than tightly: a single
    // 2 GB video is already ~100 sequential chunk requests, and the only
    // person who can reach these is the site owner. The limit is here to
    // stop a runaway client loop, not to police anybody.
    Route::middleware('throttle:uploads')->group(function () {
        Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
        Route::post('uploads/{upload}/chunks/{index}', [UploadController::class, 'chunk'])
            ->whereNumber('index')
            ->name('uploads.chunk');
        Route::post('uploads/{upload}/complete', [UploadController::class, 'complete'])->name('uploads.complete');
        Route::delete('uploads/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
    });
});
