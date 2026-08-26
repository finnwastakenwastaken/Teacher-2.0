<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin setup token
    |--------------------------------------------------------------------------
    |
    | When set, required by the claim screen before it creates the admin
    | account — closes the first-come-first-served claim window.
    |
    */

    'setup_token' => env('ADMIN_SETUP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Pre-seeded admin account
    |--------------------------------------------------------------------------
    |
    | Alternative to the claim screen: set ADMIN_EMAIL and ADMIN_PASSWORD
    | before first boot. `admin:seed` runs on every start and creates the
    | account once, from these values, if none exists yet.
    |
    */

    'seed' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
