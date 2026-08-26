<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Locale;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Pins that PasswordPolicy's checklist and its enforced rule cannot drift apart,
 * and that rejection messages are Dutch rather than the framework's English default.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * describe() (front end) and rule() (server) must agree on every requirement.
     */
    public function test_every_described_requirement_is_actually_enforced()
    {
        $policy = PasswordPolicy::describe();
        $rule = PasswordPolicy::rule();

        // A fully-compliant password, then one weakened in exactly one way per requirement.
        $strong = str_repeat('Aa1!', 8);

        $this->assertTrue(
            $this->passes($strong, $rule),
            'A password meeting every requirement was rejected.'
        );

        $weakenings = [
            'min' => str_repeat('Aa1!', 1),
            'letters' => str_repeat('1234!', 8),
            'mixedCase' => strtolower($strong),
            'numbers' => str_replace('1', 'b', $strong),
            'symbols' => str_replace('!', 'b', $strong),
        ];

        foreach ($weakenings as $requirement => $candidate) {
            // `min` is always in force; the rest are conditional.
            $described = $requirement === 'min'
                ? true
                : (bool) $policy[$requirement];

            $this->assertSame(
                $described,
                ! $this->passes($candidate, $rule),
                $described
                    ? "The policy describes `{$requirement}` but does not enforce it."
                    : "The policy enforces `{$requirement}` but does not describe it."
            );
        }
    }

    public function test_the_registered_default_is_the_policy()
    {
        // Everything asking for Password::default() must get the same rule as the policy.
        $this->assertSame(
            (string) PasswordPolicy::rule()->toPasswordRulesString(),
            (string) Password::defaults()->toPasswordRulesString(),
        );
    }

    public function test_the_claim_screen_is_told_the_policy()
    {
        $response = $this->get(route('admin.claim.create'));

        $response->assertInertia(
            fn ($page) => $page
                ->where('passwordPolicy.min', PasswordPolicy::describe()['min'])
                ->has('passwordPolicy.symbols')
                ->has('passwordPolicy.uncompromised')
        );
    }

    public function test_the_security_screen_is_told_the_policy()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            // Route sits behind RequirePassword.
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertInertia(
            fn ($page) => $page->where(
                'passwordPolicy.min',
                PasswordPolicy::describe()['min']
            )
        );
    }

    /**
     * Regression: without lang/nl, the message fell back to English on an otherwise Dutch screen.
     */
    public function test_a_rejected_password_is_reported_in_the_visitor_s_language()
    {
        $expected = [
            'nl' => 'wachtwoord',
            'en' => 'password',
        ];

        foreach ($expected as $locale => $word) {
            $this->withCookie(Locale::COOKIE, $locale)
                ->from(route('admin.claim.create'))
                ->post(route('admin.claim.store'), [
                    'name' => 'Docent',
                    'email' => 'docent@example.test',
                    'password' => 'kort',
                    'password_confirmation' => 'kort',
                ])
                ->assertSessionHasErrors('password');

            $message = strtolower(session('errors')->first('password'));

            $this->assertStringContainsString(
                $word,
                $message,
                "The message was not in `{$locale}`: {$message}"
            );
        }
    }

    /**
     * Inertia's `errors` prop keeps only one message per field; `errorList` carries
     * the rest (see HandleInertiaRequests::allValidationErrors()).
     */
    public function test_all_password_failures_are_reported_at_once()
    {
        // Too short *and* mismatched confirmation — both rules are enforced in every
        // environment, unlike the policy's stricter-in-production rules.
        $response = $this->followingRedirects()
            ->from(route('admin.claim.create'))
            ->post(route('admin.claim.store'), [
                'name' => 'Docent',
                'email' => 'docent@example.test',
                'password' => 'aaa',
                'password_confirmation' => 'bbb',
            ]);

        // Asserted on the props the screen receives, not the session — only the first
        // message ever reached the browser was the actual bug.
        $response->assertInertia(
            fn ($page) => $page
                // Stays a single string so `errors.foo: string` screens keep working.
                ->where('errors.password', fn ($first) => is_string($first))
                ->has('errorList.password', 2)
        );
    }

    private function passes(string $password, Password $rule): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [$rule]],
        )->passes();
    }
}
