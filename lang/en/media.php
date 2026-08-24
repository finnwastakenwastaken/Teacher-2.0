<?php

/*
 * Uploading, converting and storing files.
 *
 * These reach the owner through the uploader in the admin panel, so they are
 * interface rather than operator output — an upload that fails has to say why
 * in a way the person holding the file can act on.
 */

return [

    'upload' => [
        'empty' => 'That file appears to be empty.',
        'too_large' => 'This file is too large (:size). The maximum is :maximum. For larger files use "php artisan media:import".',
        'expired' => 'This upload has expired. Please try again.',
        'bad_chunk_number' => 'Invalid chunk number.',
        'chunk_wrong_size' => 'Part :index is an unexpected size (:actual bytes instead of :expected).',
        'chunk_missing' => 'The upload is incomplete: part :index of :total is missing.',
        'merge_failed' => 'The file could not be reassembled.',
        'chunk_unreadable' => 'Part of the upload could not be read.',
        'wrong_size' => 'The reassembled upload is not the expected size.',
        'unsupported_type' => 'File type ":mime" is not supported.',
        'svg_disabled' => 'SVG files are disabled.',
        'size_unknown' => 'The file size could not be determined.',
        'store_failed' => 'The file could not be saved.',
        'alt_required' => 'An image needs alternative text.',
    ],

    'image' => [
        // Refused rather than stored: a HEIC that will not decode is useless
        // to every visitor, so keeping it would mean an image on the public
        // page that can never be displayed.
        'undisplayable' => 'This image could not be converted into a format browsers can display (:reason).',
    ],

];
