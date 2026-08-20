<?php

namespace App\Console\Commands;

use App\Exceptions\AdminAlreadyClaimedException;
use App\Support\AdminAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The automation alternative to the browser claim screen (the technical reference).
 * Runs on every container boot (see docker/php/entrypoint.sh) and is a no-op
 * once an account exists, so it is always safe to re-run.
 */
class SeedAdminFromEnvironment extends Command
{
    protected $signature = 'admin:seed';

    protected $description = 'Create the admin account from ADMIN_* environment variables, if configured';

    public function handle(): int
    {
        if (AdminAccount::exists()) {
            $this->components->info('Admin account already exists — nothing to do.');

            return self::SUCCESS;
        }

        $email = config('admin.seed.email');
        $password = config('admin.seed.password');
        $name = config('admin.seed.name');

        if (blank($email) && blank($password)) {
            // Normal case: nothing pre-seeded. The browser claim screen
            // handles account creation instead.
            return self::SUCCESS;
        }

        if (blank($email) || blank($password)) {
            $this->components->error(
                'ADMIN_EMAIL and ADMIN_PASSWORD must both be set to pre-seed the admin account. '
                .'Set both, or leave both blank to use the browser claim screen instead.'
            );

            return self::FAILURE;
        }

        $validator = Validator::make(
            [
                'name' => blank($name) ? 'Docent' : $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            $this->components->error('ADMIN_* environment variables are invalid:');

            foreach ($validator->errors()->all() as $message) {
                $this->components->error("  {$message}");
            }

            return self::FAILURE;
        }

        try {
            AdminAccount::claim($validator->validated());
        } catch (AdminAlreadyClaimedException) {
            $this->components->info('Admin account already exists — nothing to do.');

            return self::SUCCESS;
        }

        $this->components->info("Admin account created for {$email} from environment variables.");

        return self::SUCCESS;
    }
}
