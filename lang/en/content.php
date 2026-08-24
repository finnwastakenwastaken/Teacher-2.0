<?php

/*
 * The public, student-facing site.
 *
 * Only the chrome the application writes. Page bodies, titles, download
 * labels and education-level names are the owner's and are never translated.
 */

return [

    'downloads' => [
        // The leading group for downloads carrying no level tag. A handout
        // meant for everybody should not require ticking every box.
        'everyone' => 'For everyone',
    ],

    'unlock' => [
        'password_required' => 'Enter the password.',
        'incorrect' => 'That password is not correct.',
        'throttled' => 'Too many attempts. Try again in :seconds seconds.',
    ],

];
