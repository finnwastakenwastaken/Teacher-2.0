<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers `php artisan admin:reset-password`, the documented recovery path for
 * a forgotten password (there is no e-mail-based reset — see
 * config/fortify.php). Deliberately interactive only; see the command's own
 * doc block for why it has no --password option.
 */
class AdminResetPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_the_password_when_the_confirmation_matches()
    {
        $user = User::factory()->create(['password' => 'the-old-password']);

        $this->artisan('admin:reset-password')
            ->expectsQuestion('New password', 'a-new-strong-password')
            ->expectsQuestion('Confirm new password', 'a-new-strong-password')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('a-new-strong-password', $user->refresh()->password));
    }

    public function test_fails_when_the_confirmation_does_not_match()
    {
        $user = User::factory()->create(['password' => 'the-old-password']);
        $originalHash = $user->password;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('New password', 'a-new-strong-password')
            ->expectsQuestion('Confirm new password', 'a-different-password')
            ->assertExitCode(1);

        $this->assertSame($originalHash, $user->refresh()->password);
    }

    public function test_fails_when_the_new_password_is_too_short()
    {
        $user = User::factory()->create(['password' => 'the-old-password']);
        $originalHash = $user->password;

        $this->artisan('admin:reset-password')
            ->expectsQuestion('New password', 'short')
            ->expectsQuestion('Confirm new password', 'short')
            ->assertExitCode(1);

        $this->assertSame($originalHash, $user->refresh()->password);
    }

    public function test_fails_when_no_admin_account_exists_yet()
    {
        $this->artisan('admin:reset-password')->assertExitCode(1);
    }
}
