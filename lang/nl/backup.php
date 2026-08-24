<?php

/*
 * Making and restoring archives.
 *
 * These surface in the admin panel as well as on the command line, which is
 * why they are translated where the Artisan commands around them are not.
 */

return [

    'missing_file' => 'Er staat geen bestand op :path.',
    'not_a_backup_name' => 'Dat is geen naam van een back-upbestand.',
    'not_found' => 'Er is geen back-up met de naam :name.',
    'no_database' => 'Deze back-up bevat geen database.',
    'no_manifest' => 'Dit bestand is geen back-up van deze site: er zit geen manifest.json in.',
    'unreadable_manifest' => 'Het manifest van deze back-up is onleesbaar.',
    // Refused rather than half-applied: an archive from a newer version may
    // hold a shape this code does not know how to put back.
    'newer_format' => 'Deze back-up is gemaakt met een nieuwere versie van de site (formaat :format). Werk de site eerst bij.',
    'unknown_error' => 'Onbekende fout.',

];
