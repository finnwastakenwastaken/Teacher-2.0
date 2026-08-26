<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where archives live
    |--------------------------------------------------------------------------
    |
    | Its own disk and volume, deliberately not inside the private media
    | disk: nesting would make every backup contain previous ones, and
    | MediaStream serves anything under the media disk's library
    | directories — an archive (whole DB, password hashes) must never be
    | reachable that way. Its own internal nginx location and auth-gated
    | controller instead.
    |
    */

    'disk' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many archives `backup:run --prune` keeps. Nothing is pruned without
    | that flag. `--keep=N` overrides the number for one run.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 7),

    /*
    |--------------------------------------------------------------------------
    | Archive format
    |--------------------------------------------------------------------------
    |
    | Written into every manifest and checked on restore. An archive from a
    | future version is refused with an explanation rather than half-restored
    | — a partly-restored site is worse than one that never started.
    |
    */

    'format' => 1,

    'name_prefix' => 'teacher-backup-',

    /*
    |--------------------------------------------------------------------------
    | External tools
    |--------------------------------------------------------------------------
    |
    | Installed in the application image (see the Dockerfile). The client
    | major version must match the database server: pg_dump refuses a server
    | newer than itself.
    |
    */

    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'psql' => env('BACKUP_PSQL', 'psql'),
    'tar' => env('BACKUP_TAR', 'tar'),

    // How long a dump or restore may run, in seconds. A big media volume is
    // slow to compress and the default 60s Process timeout is nowhere near.
    'timeout' => (int) env('BACKUP_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Downloading an archive
    |--------------------------------------------------------------------------
    |
    | Same mechanism as gated media — nginx streams the bytes from an
    | `internal` location — but a *different* location, reached only from a
    | controller behind `auth`. See docker/nginx/app.conf.
    |
    */

    'x_accel_prefix' => '/__backup/',

    // Whether to hand bytes to nginx or stream from PHP. Defaults from the
    // media setting but is a separate key — previously shared, which meant
    // toggling media's X-Accel also silently changed backup transport.
    'x_accel' => env('BACKUP_X_ACCEL', env('MEDIA_X_ACCEL', true)),

];
