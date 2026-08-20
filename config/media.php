<?php

return [

    /*
    |--------------------------------------------------------------------------
    | X-Accel-Redirect
    |--------------------------------------------------------------------------
    |
    | When true, the gated media controller authorises the request and then
    | hands the actual byte-pushing to nginx via an X-Accel-Redirect header,
    | pointing at the `internal` /__media/ location in docker/nginx/app.conf.
    | This is the whole reason the stack runs nginx + PHP-FPM instead of
    | FrankenPHP: streaming a 500 MB video through a PHP worker would occupy
    | that worker for the entire playback, and a class of thirty students
    | would exhaust the pool.
    |
    | Turning this off makes PHP stream the file itself. That path exists so
    | the test suite can assert on real response bodies (there is no nginx in
    | front of the test runner) and so a bare `artisan serve` is not silently
    | broken. It is NOT supported in production — see the technical reference.
    |
    | Authorisation happens before this branch either way, so the two paths
    | can never disagree about who is allowed to see a file.
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
    | Keyed by the MIME type detected *server-side* from the assembled file's
    | contents. The client's declared type and the client's filename
    | extension are both untrusted and never used to choose a storage path —
    | the extension below is what gets written to disk.
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
    | SVG
    |--------------------------------------------------------------------------
    |
    | SVG is XML and can carry <script>. These files are only ever served
    | from the gated controller with Content-Disposition: attachment and a
    | restrictive CSP, never inlined into a page, so a hostile SVG cannot run
    | in the site's origin. Set to false to refuse them outright.
    |
    */

    'allow_svg' => env('MEDIA_ALLOW_SVG', true),

    /*
    |--------------------------------------------------------------------------
    | Images: conversion and compression
    |--------------------------------------------------------------------------
    |
    | Every raster image is re-encoded to WebP as it enters the library, by
    | App\Services\ImageOptimiser. Two reasons, and the first one is not
    | optional: a photo off an iPhone is HEIC, which no browser can display,
    | so a page carrying one would simply show nothing. The second is size —
    | a teacher uploads what the camera produced, and a 4 MB photo behind a
    | Cloudflare tunnel is a slow page on a school wifi.
    |
    | SVG is left alone (it is vector, and re-encoding it to a raster format
    | would be a downgrade), and so is an animated GIF, because flattening it
    | to a single frame would silently destroy it.
    |
    | An image larger than `max_bytes` after conversion is downscaled and
    | re-encoded at stepping quality until it fits or `min_quality` is
    | reached. Nothing is ever enlarged.
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

        // Ceiling for ImageMagick's pixel cache, in bytes. A 48 MP photo
        // decodes to roughly 200 MB of pixels.
        //
        // This is *not* governed by PHP's memory_limit: the cache is
        // allocated by ImageMagick in C and PHP never counts it. So the
        // limit is what keeps a large photo from taking the container's RAM
        // instead — past it, ImageMagick spills to disk and gets slow rather
        // than failing. Budget it against the number of PHP-FPM workers, not
        // against memory_limit: two simultaneous uploads can each want this
        // much, on a box the install guide only asks to have 4 GB.
        'memory_limit_bytes' => (int) env('MEDIA_IMAGE_MEMORY_BYTES', 512 * 1024 * 1024),
    ],

];
