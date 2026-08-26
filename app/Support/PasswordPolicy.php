<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The one declaration of what makes an acceptable password. `describe()` is
 * the source and `rule()` is built from it, so the on-screen checklist can
 * never disagree with what the validator actually requires. This site has
 * one account and no e-mail recovery, hence the strict production policy.
 */
class PasswordPolicy
{
    /**
     * Deliberately weaker outside production. `uncompromised()` calls Have I
     * Been Pwned's range API on every password change — fine in production,
     * but it would make every factory-made test user wait on the network.
     * The rest is relaxed alongside it so a developer can type "password".
     *
     * @var array<string, int|bool>
     */
    private const DEVELOPMENT = [
        'min' => 8,
        'letters' => false,
        'mixedCase' => false,
        'numbers' => false,
        'symbols' => false,
        'uncompromised' => false,
    ];

    /** @var array<string, int|bool> */
    private const PRODUCTION = [
        'min' => 12,
        'letters' => true,
        'mixedCase' => true,
        'numbers' => true,
        'symbols' => true,
        'uncompromised' => true,
    ];

    /**
     * What is required right now, for the front end to render as a checklist.
     *
     * @return array<string, int|bool>
     */
    public static function describe(): array
    {
        return app()->isProduction() ? self::PRODUCTION : self::DEVELOPMENT;
    }

    /**
     * The same policy as a validation rule.
     *
     * Registered as Password::defaults() in AppServiceProvider, so every rule
     * set that asks for Password::default() — the claim screen, the settings
     * screen, both Artisan commands — gets this without knowing about it.
     */
    public static function rule(): Password
    {
        $policy = self::describe();

        $rule = Password::min((int) $policy['min']);

        if ($policy['letters']) {
            $rule->letters();
        }

        if ($policy['mixedCase']) {
            $rule->mixedCase();
        }

        if ($policy['numbers']) {
            $rule->numbers();
        }

        if ($policy['symbols']) {
            $rule->symbols();
        }

        if ($policy['uncompromised']) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
