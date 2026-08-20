<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The documented recovery path referenced throughout the technical reference and the
 * update & security guide: `php artisan admin:reset-password`, run on the
 * server. There is no e-mail-based reset — see config/fortify.php for why.
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password';

    protected $description = "Reset the single admin account's password";

    /**
     * Deliberately has no --password option. Passwords must never travel
     * through automation as plaintext CLI arguments — they end up in shell
     * history and are visible to any other user on the box who can run `ps`.
     * This command always prompts interactively instead.
     */
    public function handle(): int
    {
        $user = User::query()->first();

        if (! $user) {
            $this->components->error(
                'No admin account exists yet. Visit the site to claim it through the '
                .'browser, or set ADMIN_EMAIL / ADMIN_PASSWORD and restart the application.'
            );

            return self::FAILURE;
        }

        $this->components->info("Resetting the password for {$user->email}.");

        $password = $this->secret('New password');
        $confirmation = $this->secret('Confirm new password');

        if ($password !== $confirmation) {
            $this->components->error('Passwords did not match. Nothing was changed.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save();

        $this->components->info('Password updated.');

        return self::SUCCESS;
    }
}
