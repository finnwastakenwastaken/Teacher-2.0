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
 * The password policy, and the messages that report breaking it.
 *
 * The whole point of PasswordPolicy is that the checklist on screen and the
 * rule the server enforces come from one declaration. These tests pin that
 * they cannot drift apart, and that the resulting messages are Dutch rather
 * than the framework's English — which is what they were until lang/nl
 * existed, on a site whose interface is Dutch.
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
     * describe() is what the front end draws; rule() is what the server
     * enforces. If a requirement is described it must be enforced, and the
     * other way round — a checklist that ticks while the server refuses is
     * worse than no checklist at all.
     */
    public function test_every_described_requirement_is_actually_enforced()
    {
        $policy = PasswordPolicy::describe();
        $rule = PasswordPolicy::rule();

        // A password that satisfies everything the policy could ask for,
        // then one weakened in exactly one way per requirement.
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
        // Anything asking for Password::default() — both Form Requests and
        // both Artisan commands — has to get the same rule, or the checklist
        // describes something no screen actually applies.
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
            // The route sits behind RequirePassword.
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
     * The bug this was reported as: the message was English, on a screen that
     * is otherwise entirely Dutch, because no lang/nl existed and the
     * fallback locale is also `nl`.
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
     * Inertia's own `errors` prop keeps one message per field, which is how
     * the owner ended up meeting a five-part policy one submission at a time.
     * `errorList` carries the rest — see
     * HandleInertiaRequests::allValidationErrors().
     */
    public function test_all_password_failures_are_reported_at_once()
    {
        // Too short *and* not matching its confirmation. Deliberately not a
        // password that breaks several policy rules: the policy is weaker
        // outside production, so such a case would prove one message here
        // and four on the deployed site — which is the wrong way round for a
        // regression test. These two rules are in force in every environment.
        $response = $this->followingRedirects()
            ->from(route('admin.claim.create'))
            ->post(route('admin.claim.store'), [
                'name' => 'Docent',
                'email' => 'docent@example.test',
                'password' => 'aaa',
                'password_confirmation' => 'bbb',
            ]);

        // Asserted on the props the screen actually receives rather than on
        // the session, because that is the thing that was broken: every
        // message existed, and only the first ever reached the browser.
        $response->assertInertia(
            fn ($page) => $page
                // Inertia's own prop: still one string, so the twenty screens
                // typed against `errors.foo: string` keep working.
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
