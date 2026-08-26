<?php

namespace Tests\Feature\Auth;

use App\Exceptions\AdminAlreadyClaimedException;
use App\Models\User;
use App\Support\AdminAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClaimAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The 'claim' rate limiter is keyed by IP only, and every test here shares one
        // test-client IP; reset it per test or earlier tests trip later ones' 429s.
        Cache::flush();
    }

    public function test_claim_screen_can_be_rendered_when_unclaimed()
    {
        $response = $this->get(route('admin.claim.create'));

        $response->assertOk();
    }

    public function test_claim_screen_redirects_to_login_once_already_claimed()
    {
        User::factory()->create();

        $response = $this->get(route('admin.claim.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_claim_screen_redirects_authenticated_users_to_the_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.claim.create'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_claiming_creates_the_admin_account_and_logs_the_owner_in()
    {
        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame(1, User::query()->count());

        $user = User::query()->sole();
        $this->assertSame('De Docent', $user->name);
        $this->assertSame('docent@school.nl', $user->email);
    }

    /**
     * Claiming with a capital in the address used to lock the owner out for
     * good: `lowercase_usernames` canonicalises what is typed at login, so a
     * stored `Docent@school.nl` matched nothing, and `admin:reset-password`
     * could not help because the password was never what failed. Asserting the
     * login rather than only the stored value — the stored value being
     * lower-case is the mechanism, but being able to get back in is the point.
     */
    public function test_an_address_typed_with_capitals_is_stored_lowercase_and_can_log_in()
    {
        $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'Docent@School.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('docent@school.nl', User::query()->sole()->email);

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login'), [
            'email' => 'Docent@School.nl',
            'password' => 'a-strong-password',
        ]);

        $this->assertAuthenticated();
    }

    public function test_claiming_requires_name_email_and_password()
    {
        $response = $this->post(route('admin.claim.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_claiming_requires_the_password_confirmation_to_match()
    {
        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->count());
    }

    public function test_claiming_rejects_a_password_that_is_too_short()
    {
        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_second_claim_attempt_is_rejected_once_claimed()
    {
        User::factory()->create(['email' => 'first@school.nl']);

        $response = $this->post(route('admin.claim.store'), [
            'name' => 'Iemand Anders',
            'email' => 'second@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
        $this->assertSame('first@school.nl', User::query()->sole()->email);
    }

    public function test_claim_attempts_are_rate_limited()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.claim.store'), []);
        }

        $response = $this->post(route('admin.claim.store'), []);

        $response->assertStatus(429);
    }

    public function test_setup_token_is_required_when_configured()
    {
        config(['admin.setup_token' => 'correct-horse-battery-staple']);

        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('setup_token');
        $this->assertSame(0, User::query()->count());
    }

    public function test_setup_token_wrong_value_is_rejected()
    {
        config(['admin.setup_token' => 'correct-horse-battery-staple']);

        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'setup_token' => 'wrong-guess',
        ]);

        $response->assertSessionHasErrors('setup_token');
        $this->assertSame(0, User::query()->count());
    }

    public function test_setup_token_correct_value_succeeds()
    {
        config(['admin.setup_token' => 'correct-horse-battery-staple']);

        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'setup_token' => 'correct-horse-battery-staple',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(1, User::query()->count());
    }

    public function test_setup_token_is_not_required_when_not_configured()
    {
        $response = $this->post(route('admin.claim.store'), [
            'name' => 'De Docent',
            'email' => 'docent@school.nl',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_admin_account_claim_cannot_create_a_second_account()
    {
        AdminAccount::claim([
            'name' => 'First',
            'email' => 'first@school.nl',
            'password' => 'a-strong-password',
        ]);

        $this->expectException(AdminAlreadyClaimedException::class);

        AdminAccount::claim([
            'name' => 'Second',
            'email' => 'second@school.nl',
            'password' => 'a-strong-password',
        ]);
    }
}
