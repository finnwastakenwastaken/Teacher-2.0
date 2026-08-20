<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the dedicated test database.
     *
     * RefreshDatabase migrates and truncates whatever connection it is handed.
     * A misconfigured environment once pointed it at the development database,
     * which it happily wiped on every run while the suite reported green.
     *
     * This is a second line of defence behind tests/bootstrap.php: that file
     * makes the configuration correct, this one makes a silent regression
     * impossible. Fail loudly rather than destroy data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with((string) $database, '_testing')) {
            $this->fail(
                "Refusing to run tests against database [{$database}]. "
                .'The test suite truncates every table it touches and the '
                ."configured database is not a test database. Expected a name ending in '_testing'. "
                .'Check tests/bootstrap.php and phpunit.xml.'
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
