<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),

            // Deliberately false. `serve => true` registers GET/PUT routes
            // at /storage/{path} gated only by a signed URL — a second path
            // to media that bypasses the gated controller (security
            // invariant 4). `temporaryUrl()` would mint a shareable link
            // that skips authorisation entirely; with serve off it throws
            // instead. MediaAccessTest asserts both routes stay absent.
            'serve' => false,

            // nginx must be able to read these files. PHP-FPM writes as
            // www-data, nginx reads as a different user — Flysystem's 0700
            // default for a private disk made nginx unable to traverse in,
            // so every X-Accel-Redirect 403'd while the test suite (which
            // reads files itself, as www-data) stayed green. Not a web
            // exposure risk: the disk root is outside the docroot and only
            // reachable via an `internal` nginx location. `visibility`
            // stays unset rather than "public" — that would also make
            // ServeFile skip its signature check.
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir' => ['public' => 0755, 'private' => 0755],
            ],

            'throw' => false,
            'report' => false,
        ],

        // Backup archives. Own volume, outside the private media disk — see
        // config/backup.php. `serve` false for the same reason as above: a
        // signed URL would hand out the whole database.
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'serve' => false,
            // nginx reads these as a different user, same reason as above.
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir' => ['public' => 0755, 'private' => 0755],
            ],
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
