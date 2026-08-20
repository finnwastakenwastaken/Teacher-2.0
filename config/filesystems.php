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

            // Deliberately false. `serve => true` makes Laravel register
            // GET and PUT routes at /storage/{path} that read and write this
            // disk directly, gated only by a signed URL. That is a second
            // way to reach uploaded media, which security invariant 4
            // forbids: media is served *only* through the gated controller,
            // which is the single place that checks hidden state and
            // password access.
            //
            // A signature is not a substitute for that check. Any call to
            // Storage::disk('local')->temporaryUrl() would mint a working,
            // shareable URL that skips authorisation entirely — a student
            // could forward it to anyone. With serve disabled the routes do
            // not exist and temporaryUrl() throws instead of silently
            // producing a bypass.
            //
            // MediaAccessTest asserts both routes stay absent.
            'serve' => false,

            // nginx has to be able to read these files.
            //
            // Two containers share this volume: PHP-FPM writes as www-data
            // (uid 82) and nginx reads as nginx (uid 101). Flysystem's
            // default for a private disk creates directories 0700, which
            // nginx cannot even traverse — so every X-Accel-Redirect became
            // a 403 and no image, download or video served at all, while
            // the test suite stayed green because it runs with
            // MEDIA_X_ACCEL=false and reads the file as www-data itself.
            //
            // These modes are filesystem permissions inside the container,
            // not web exposure: the disk root sits outside the docroot,
            // there is no route that maps to it, and the only nginx location
            // that can reach it is marked `internal`. Aligning uids or
            // groups across two different base images instead would break
            // silently the next time either image renumbers its user.
            //
            // `visibility` is deliberately left unset (so it stays
            // "private"). Setting it to "public" would fix the modes too,
            // but it also makes Laravel's ServeFile skip its signature check
            // — a trap for anyone who ever flips `serve` back on.
            'permissions' => [
                'file' => ['public' => 0644, 'private' => 0644],
                'dir' => ['public' => 0755, 'private' => 0755],
            ],

            'throw' => false,
            'report' => false,
        ],

        // Backup archives. Its own volume, outside the private media disk —
        // see the long note at the top of config/backup.php for why that
        // separation is load-bearing rather than tidy.
        //
        // `serve` is false here for exactly the reason it is false above: a
        // signed URL to an archive would hand out the entire database,
        // password hashes included, to anyone the link reached.
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'serve' => false,
            // nginx reads these to stream a download, as a different user
            // from a read-only mount — the same reason the private disk
            // carries an explicit permissions block.
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
