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
        'everyone' => 'Voor iedereen',
    ],

    'unlock' => [
        'password_required' => 'Vul het wachtwoord in.',
        'incorrect' => 'Dit wachtwoord klopt niet.',
        'throttled' => 'Te veel pogingen. Probeer het over :seconds seconden opnieuw.',
    ],

];
