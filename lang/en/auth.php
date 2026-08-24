<?php

/*
 * Logging in, and the one-time claim of the single admin account.
 *
 * The `failed`, `password` and `throttle` keys are resolved by Fortify
 * itself, so this file is the only way to reach them — there is no controller
 * in this application that emits a failed-login message.
 */

return [

    'failed' => 'We do not recognise that combination of email address and password.',
    'password' => 'The password you entered is incorrect.',
    'throttle' => 'Too many login attempts. Try again in :seconds seconds.',

    'claim' => [
        'already_completed' => 'Setup has already been completed. Sign in below.',
        'setup_token_invalid' => 'That setup code is not correct.',
    ],

];
