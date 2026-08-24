<?php

namespace App\Providers;

use App\Support\PasswordPolicy;
use App\Support\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->shareBranding();
    }

    /**
     * The site's title and favicon, for the root Blade template.
     *
     * Inertia shares the same values with React, but the <title> and the
     * favicon have to be right in the server-rendered HTML — before any
     * JavaScript runs — or the tab flickers from the default to the real
     * name on every first load.
     *
     * A composer rather than View::share, so the query runs only when the
     * root template actually renders — never during migrations, console
     * commands or asset requests, where the table may not even exist yet.
     */
    protected function shareBranding(): void
    {
        View::composer('app', function (ViewContract $view): void {
            $view->with('siteBranding', SiteSettings::forInertia());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /*
         * The requirements themselves live in PasswordPolicy, because they
         * are also rendered on screen as a checklist and two copies of the
         * same five rules drift. See that class for why production is strict
         * and why development is not.
         */
        Password::defaults(fn (): Password => PasswordPolicy::rule());
    }

    /**
     * Rate limiters that aren't Fortify's own (those live in
     * FortifyServiceProvider, colocated with the feature they throttle).
     */
    protected function configureRateLimiting(): void
    {
        // No email/identity exists yet at this point, so this is IP-only —
        // the same reasoning as the plan's page-password limiter.
        RateLimiter::for('claim', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Chunked upload. Deliberately loose: one 2 GB video is already
        // around a hundred sequential chunk requests, and these routes sit
        // behind `auth` where the only possible caller is the site owner.
        // This exists to bound a runaway client loop, nothing more.
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });
    }
}
