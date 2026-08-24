<?php

/*
 * Making and restoring archives.
 *
 * These surface in the admin panel as well as on the command line, which is
 * why they are translated where the Artisan commands around them are not.
 */

return [

    'missing_file' => 'There is no file at :path.',
    'not_a_backup_name' => 'That is not the name of a backup file.',
    'not_found' => 'There is no backup named :name.',
    'no_database' => 'This backup contains no database.',
    'no_manifest' => 'This file is not a backup of this site: it contains no manifest.json.',
    'unreadable_manifest' => 'The manifest of this backup cannot be read.',
    // Refused rather than half-applied: an archive from a newer version may
    // hold a shape this code does not know how to put back.
    'newer_format' => 'This backup was made with a newer version of the site (format :format). Update the site first.',
    'unknown_error' => 'Unknown error.',

];
