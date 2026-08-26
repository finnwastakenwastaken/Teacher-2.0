<?php

namespace App\Providers;

use App\Support\AdminAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        // Nothing to register. User creation and email-based password reset
        // are disabled in config/fortify.php — see the comment there before
        // adding anything back to this method.
    }

    private function configureViews(): void
    {
        // Sends the first visitor to the claim screen instead of a login form
        // for an account that doesn't exist yet — no bespoke /admin entry
        // route needed, since any authenticated route already redirects a
        // guest to `login` via Laravel's own `auth` middleware.
        Fortify::loginView(function (Request $request) {
            if (! AdminAccount::exists()) {
                return redirect()->route('admin.claim.create');
            }

            return Inertia::render('auth/login', [
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
