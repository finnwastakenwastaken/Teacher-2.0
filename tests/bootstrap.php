<?php

/*
 * Forces the testing environment before Laravel boots. Compose injects .env
 * into $_SERVER as well as $_ENV, and Dotenv reads $_SERVER first, so
 * PHPUnit's <env force="true"> (which only sets $_ENV/putenv()) loses to it —
 * leaving APP_ENV=local (CSRF on, false 419s) and DB_DATABASE pointed at the
 * development database (RefreshDatabase truncates it). Setting all three
 * locations here, before the autoloader, is what makes phpunit.xml's values
 * stick. TestCase adds an independent guard against a non-test database.
 * Do not replace this with plain <env> entries in phpunit.xml.
 */

$forced = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'teacher_testing',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BCRYPT_ROUNDS' => '4',
    // Forced blank: a developer's local .env must not leak into the suite.
    // Tests needing a value set it explicitly via config(['admin....' => ...]).
    'ADMIN_SETUP_TOKEN' => '',
    'ADMIN_NAME' => '',
    'ADMIN_EMAIL' => '',
    'ADMIN_PASSWORD' => '',
    // No nginx in front of the test runner, so X-Accel-Redirect would return
    // an empty 200 and content assertions would pass vacuously. Off here, PHP
    // streams the bytes itself; authorisation runs before that branch either way.
    'MEDIA_X_ACCEL' => 'false',
];

foreach ($forced as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
