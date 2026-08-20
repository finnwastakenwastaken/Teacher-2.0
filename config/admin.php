<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin setup token
    |--------------------------------------------------------------------------
    |
    | When set, the first-run claim screen also requires this value before it
    | will create the admin account. This closes the "first-come-first-served"
    | claim window: without it, whoever loads the site first — not necessarily
    | its owner — can claim the only admin account.
    |
    */

    'setup_token' => env('ADMIN_SETUP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Pre-seeded admin account
    |--------------------------------------------------------------------------
    |
    | Alternative to the browser claim screen: set both ADMIN_EMAIL and
    | ADMIN_PASSWORD before the first boot. The container entrypoint runs
    | `php artisan admin:seed` on every start, which creates the account from
    | these values if none exists yet and otherwise does nothing. ADMIN_NAME
    | is optional.
    |
    */

    'seed' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
