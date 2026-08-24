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
        'empty' => 'Het bestand lijkt leeg te zijn.',
        'too_large' => 'Dit bestand is te groot (:size). Het maximum is :maximum. Gebruik voor grotere bestanden "php artisan media:import".',
        'expired' => 'Deze upload is verlopen. Probeer het opnieuw.',
        'bad_chunk_number' => 'Ongeldig chunknummer.',
        'chunk_wrong_size' => 'Deel :index heeft een onverwachte grootte (:actual bytes in plaats van :expected).',
        'chunk_missing' => 'De upload is onvolledig: deel :index van :total ontbreekt.',
        'merge_failed' => 'Kon het bestand niet samenvoegen.',
        'chunk_unreadable' => 'Kon een deel van de upload niet lezen.',
        'wrong_size' => 'De samengevoegde upload heeft niet de verwachte grootte.',
        'unsupported_type' => 'Bestandstype ":mime" wordt niet ondersteund.',
        'svg_disabled' => 'SVG-bestanden zijn uitgeschakeld.',
        'size_unknown' => 'Kon de bestandsgrootte niet bepalen.',
        'store_failed' => 'Kon het bestand niet opslaan.',
        'alt_required' => 'Een afbeelding heeft alt-tekst nodig.',
    ],

    'image' => [
        // Refused rather than stored: a HEIC that will not decode is useless
        // to every visitor, so keeping it would mean an image on the public
        // page that can never be displayed.
        'undisplayable' => 'Deze afbeelding kon niet worden omgezet naar een formaat dat browsers kunnen tonen (:reason).',
    ],

];
