<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where archives live
    |--------------------------------------------------------------------------
    |
    | Its own disk, on its own Docker volume, deliberately *not* inside the
    | private media disk. Two reasons, and both bite:
    |
    |  - the media volume is one of the things an archive contains, so keeping
    |    archives inside it means every backup contains the previous ones and
    |    the volume grows geometrically;
    |  - App\Support\MediaStream aliases the private disk root and serves
    |    anything under the two library directories. An archive holds the
    |    whole database, password hashes included, and must never be reachable
    |    by that path. It has its own internal nginx location and its own
    |    auth-gated controller.
    |
    */

    'disk' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many archives `backup:run --prune` keeps. Nothing is pruned unless
    | that flag asks for it: silently deleting the only copy of a year's work
    | because a default said seven is not a trade this application makes.
    | `--keep=N` overrides the number for one run.
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

];
