<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `php artisan admin:seed`, the automation alternative to the browser
 * claim screen. Run automatically on every container boot — see
 * docker/php/entrypoint.sh — so it must be a safe no-op in the common case
 * (nothing configured, or already claimed).
 */
class AdminSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_nothing_when_no_admin_env_vars_are_set()
    {
        $this->artisan('admin:seed')->assertExitCode(0);

        $this->assertSame(0, User::query()->count());
    }

    public function test_creates_the_admin_account_when_email_and_password_are_set()
    {
        config([
            'admin.seed.name' => 'De Docent',
            'admin.seed.email' => 'docent@school.nl',
            'admin.seed.password' => 'a-strong-password',
        ]);

        $this->artisan('admin:seed')->assertExitCode(0);

        $this->assertSame(1, User::query()->count());

        $user = User::query()->sole();
        $this->assertSame('De Docent', $user->name);
        $this->assertSame('docent@school.nl', $user->email);
    }

    public function test_uses_a_default_name_when_admin_name_is_not_set()
    {
        config([
            'admin.seed.name' => null,
            'admin.seed.email' => 'docent@school.nl',
            'admin.seed.password' => 'a-strong-password',
        ]);

        $this->artisan('admin:seed')->assertExitCode(0);

        $this->assertNotEmpty(User::query()->sole()->name);
    }

    public function test_fails_when_only_email_is_set()
    {
        config([
            'admin.seed.email' => 'docent@school.nl',
            'admin.seed.password' => null,
        ]);

        $this->artisan('admin:seed')->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_fails_when_only_password_is_set()
    {
        config([
            'admin.seed.email' => null,
            'admin.seed.password' => 'a-strong-password',
        ]);

        $this->artisan('admin:seed')->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_fails_when_the_password_does_not_meet_policy()
    {
        config([
            'admin.seed.email' => 'docent@school.nl',
            'admin.seed.password' => 'short',
        ]);

        $this->artisan('admin:seed')->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_is_a_no_op_when_an_admin_already_exists()
    {
        User::factory()->create(['email' => 'existing@school.nl']);

        config([
            'admin.seed.email' => 'docent@school.nl',
            'admin.seed.password' => 'a-strong-password',
        ]);

        $this->artisan('admin:seed')->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
        $this->assertSame('existing@school.nl', User::query()->sole()->email);
    }
}
