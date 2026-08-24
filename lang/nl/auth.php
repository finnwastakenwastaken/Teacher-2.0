<?php

/*
 * Logging in, and the one-time claim of the single admin account.
 *
 * The `failed`, `password` and `throttle` keys are resolved by Fortify
 * itself, so this file is the only way to reach them — there is no controller
 * in this application that emits a failed-login message.
 */

return [

    'failed' => 'Deze combinatie van e-mailadres en wachtwoord kennen we niet.',
    'password' => 'Het opgegeven wachtwoord is onjuist.',
    'throttle' => 'Te veel inlogpogingen. Probeer het over :seconds seconden opnieuw.',

    'claim' => [
        'already_completed' => 'De installatie is al voltooid. Log hieronder in.',
        'setup_token_invalid' => 'Deze installatiecode is onjuist.',
    ],

];
