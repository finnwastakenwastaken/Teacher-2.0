<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The one declaration of what makes an acceptable password.
 *
 * It exists because the policy has to be stated twice — once as a rule the
 * server enforces, and once as a list the owner can read *before* submitting —
 * and two hand-written copies of the same five requirements drift. Here the
 * list is the source and the rule is built from it, so the checklist on screen
 * cannot claim something the validator does not require, or stay silent about
 * something it does.
 *
 * This site has exactly one account and no recovery by e-mail, so a weak or
 * already-breached password is the single worst thing that can happen to it.
 * That is why the production policy is as strict as it is.
 */
class PasswordPolicy
{
    /**
     * Deliberately weaker outside production.
     *
     * uncompromised() calls Have I Been Pwned's range API. It is k-anonymous
     * (five hash characters go out, never the password) and it fails open, but
     * it is an outbound HTTPS request on every password change — which in the
     * test suite means every factory-made user waits on the network.
     *
     * The rest is relaxed alongside it so a developer can type "password" into
     * a scratch install. min(8) and nothing else is exactly what Laravel's own
     * default is, which is what this environment had before this class existed.
     *
     * The divergence is the reason describe() exists rather than a hard-coded
     * list in the front end: whatever is actually in force is what the screen
     * shows, in either environment.
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
