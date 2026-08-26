<?php

return [

    /*
    |--------------------------------------------------------------------------
    | X-Accel-Redirect
    |--------------------------------------------------------------------------
    |
    | When true, the controller authorises the request then hands streaming
    | to nginx via X-Accel-Redirect (the `internal` /__media/ location) — this
    | is why the stack runs nginx + PHP-FPM rather than FrankenPHP, since
    | streaming large video through a PHP worker would occupy it for the
    | whole playback and exhaust the pool.
    |
    | Off, PHP streams the file itself — only for the test suite (no nginx in
    | front of it) and `artisan serve`; NOT supported in production (the technical reference
    | section 4). Authorisation happens before this branch either way.
    |
    */

    'x_accel' => env('MEDIA_X_ACCEL', true),

    // Must match the `location` block in docker/nginx/app.conf.
    'x_accel_prefix' => '/__media/',

    /*
    |--------------------------------------------------------------------------
    | Storage layout
    |--------------------------------------------------------------------------
    |
    | All paths are relative to the `local` (private) disk root, which is
    | storage/app/private. Nothing here may ever move to the `public` disk —
    | that would bypass every access check. See security invariant 4.
    |
    */

    'disk' => 'local',

    'directories' => [
        'images' => 'images',
        'media' => 'media',
        'chunks' => 'chunks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    // Browser slices uploads to this size. Must stay well under Cloudflare's
    // 100 MB request body cap on Free/Pro, and under the nginx
    // client_max_body_size / PHP post_max_size ceilings.
    'chunk_bytes' => (int) env('MEDIA_CHUNK_BYTES', 20 * 1024 * 1024),

    // Largest single file accepted through the browser. Anything bigger is
    // registered from disk with `php artisan media:import` instead.
    'max_bytes' => (int) env('MEDIA_MAX_BYTES', 2 * 1024 * 1024 * 1024),

    // Abandoned chunk directories are deleted after this many hours by
    // `php artisan media:prune-uploads`, which runs on every container boot.
    'upload_ttl_hours' => (int) env('MEDIA_UPLOAD_TTL_HOURS', 24),

    // Directory scanned by `php artisan media:import`. In production this is
    // a bind mount so the operator can drop a large file in from the host.
    'import_path' => env('MEDIA_IMPORT_PATH', storage_path('app/import')),

    /*
    |--------------------------------------------------------------------------
    | Accepted types
    |--------------------------------------------------------------------------
    |
    | Keyed by the MIME type detected server-side from the assembled file's
    | contents — the client's declared type/filename are untrusted and never
    | used to choose a storage path.
    |
    */

    'types' => [
        // Images. Everything raster here is re-encoded to WebP on the way in
        // — see the `images` block below — so these extensions describe what
        // is accepted, not what ends up on disk.
        'image/jpeg' => ['kind' => 'image', 'extension' => 'jpg'],
        'image/png' => ['kind' => 'image', 'extension' => 'png'],
        'image/gif' => ['kind' => 'image', 'extension' => 'gif'],
        'image/webp' => ['kind' => 'image', 'extension' => 'webp'],
        'image/avif' => ['kind' => 'image', 'extension' => 'avif'],
        'image/svg+xml' => ['kind' => 'image', 'extension' => 'svg'],
        // A photo taken on any recent iPhone. No browser renders it, so it is
        // accepted only because it is converted; if conversion fails the
        // upload is refused rather than stored unusable.
        'image/heic' => ['kind' => 'image', 'extension' => 'heic'],
        'image/heif' => ['kind' => 'image', 'extension' => 'heif'],

        // Documents.
        'application/pdf' => ['kind' => 'document', 'extension' => 'pdf'],
        'application/msword' => ['kind' => 'document', 'extension' => 'doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['kind' => 'document', 'extension' => 'docx'],
        'application/vnd.ms-excel' => ['kind' => 'document', 'extension' => 'xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['kind' => 'document', 'extension' => 'xlsx'],
        'application/vnd.ms-powerpoint' => ['kind' => 'document', 'extension' => 'ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['kind' => 'document', 'extension' => 'pptx'],
        'text/plain' => ['kind' => 'document', 'extension' => 'txt'],
        'text/csv' => ['kind' => 'document', 'extension' => 'csv'],
        'application/zip' => ['kind' => 'document', 'extension' => 'zip'],

        // Video. H.264 MP4 is the only format guaranteed to play everywhere;
        // the others are accepted because they are common, not because they
        // are recommended. No transcoding happens (no ffmpeg in the image).
        'video/mp4' => ['kind' => 'video', 'extension' => 'mp4'],
        'video/webm' => ['kind' => 'video', 'extension' => 'webm'],
        'video/quicktime' => ['kind' => 'video', 'extension' => 'mov'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension aliases
    |--------------------------------------------------------------------------
    |
    | Extensions that are accepted but are not the one written to disk, so
    | they cannot be read off the table above. They matter in two places and
    | both have to agree: App\Services\MediaLibrary falls back to the filename
    | when content sniffing is ambiguous, and App\Support\MediaFormats tells
    | the uploader what a teacher may drop in. Listing `.jpeg` in only one of
    | those is how the screen ends up under-stating what the server takes.
    |
    */

    'extension_aliases' => [
        'jpeg' => 'image/jpeg',
    ],

    /*
    |--------------------------------------------------------------------------
    | SVG
    |--------------------------------------------------------------------------
    |
    | SVG is XML and can carry <script>, but these are only ever served with
    | Content-Disposition: attachment and a restrictive CSP, never inlined —
    | so a hostile SVG can't run in the site's origin. Set false to refuse.
    |
    */

    'allow_svg' => env('MEDIA_ALLOW_SVG', true),

    /*
    |--------------------------------------------------------------------------
    | Images: conversion and compression
    |--------------------------------------------------------------------------
    |
    | Every raster image is re-encoded to WebP on the way in, by
    | App\Services\ImageOptimiser. Not optional: an iPhone photo is HEIC,
    | which no browser displays. Also reduces size for slow school wifi.
    | SVG (vector) and animated GIF (would flatten to one frame) are left
    | alone. Oversized images are downscaled/re-encoded at stepping quality
    | until they fit or `min_quality` is reached; never enlarged.
    |
    */

    'images' => [
        // Long edge, in pixels. Generous: the widest a page ever renders an
        // image is about 900 CSS px, doubled for a high-density screen.
        'max_dimension' => (int) env('MEDIA_IMAGE_MAX_DIMENSION', 2560),

        // Above this, the image is compressed harder rather than stored as is.
        'max_bytes' => (int) env('MEDIA_IMAGE_MAX_BYTES', 2 * 1024 * 1024),

        // WebP quality to try first, and the floor beyond which the image is
        // stored as it is rather than degraded further.
        'quality' => (int) env('MEDIA_IMAGE_QUALITY', 82),
        'min_quality' => (int) env('MEDIA_IMAGE_MIN_QUALITY', 50),

        // Ceiling for ImageMagick's pixel cache (a 48 MP photo decodes to
        // ~200 MB). Not governed by PHP's memory_limit — ImageMagick
        // allocates this in C — so it's what keeps a large photo from
        // eating container RAM; past it, ImageMagick spills to disk instead
        // of failing. Budget against PHP-FPM worker count, not memory_limit:
        // simultaneous uploads each want this much on a 4 GB box.
        'memory_limit_bytes' => (int) env('MEDIA_IMAGE_MEMORY_BYTES', 512 * 1024 * 1024),
    ],

];
