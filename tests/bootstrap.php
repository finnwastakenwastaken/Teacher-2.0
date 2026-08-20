<?php

/*
|------------------------------------------------------------------------------
| Test bootstrap — forces the testing environment before Laravel boots.
|------------------------------------------------------------------------------
|
| Why this file exists.
|
| The application runs in Docker, where compose injects .env into the
| container's real environment. PHP exposes those as $_SERVER as well as
| $_ENV, and Dotenv's default adapter order reads $_SERVER *before* $_ENV.
| PHPUnit's <env force="true"> only writes $_ENV and putenv(), so the
| container's real values silently won every time.
|
| The consequences were not subtle:
|
|   * APP_ENV stayed "local", so Application::runningUnitTests() was false,
|     CSRF validation stayed switched on, and every POST/PATCH/DELETE test
|     failed with a 419 that looked like an application bug.
|
|   * DB_DATABASE stayed "teacher", so RefreshDatabase migrated and truncated
|     the DEVELOPMENT database on every run. Tests passed while quietly
|     destroying real content.
|
| Setting all three storage locations here, before the autoloader runs, is
| what makes the values in phpunit.xml actually take effect. TestCase adds a
| second, independent guard that refuses to run against a non-test database.
|
| Do not replace this with plain <env> entries in phpunit.xml.
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
    // Forced blank for the same reason as everything else here: a developer's
    // local .env may have these set for their own dev convenience, and that
    // must never leak into the test suite. Tests that need a setup token or a
    // pre-seed value set it explicitly with config(['admin....' => ...]).
    'ADMIN_SETUP_TOKEN' => '',
    'ADMIN_NAME' => '',
    'ADMIN_EMAIL' => '',
    'ADMIN_PASSWORD' => '',
    // There is no nginx in front of the test runner, so an X-Accel-Redirect
    // would produce a 200 with an empty body and assertions about file
    // contents would pass vacuously. With this off the controller streams the
    // bytes itself and tests can check what was actually served.
    // Authorisation runs before that branch, so both paths agree on access.
    'MEDIA_X_ACCEL' => 'false',
];

foreach ($forced as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
