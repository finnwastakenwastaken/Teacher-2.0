<?php

namespace App\Http\Middleware;

use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Branding is on every screen, public and admin, including the
            // login form — so it is shared rather than passed by each
            // controller. A closure so the query only runs when a page
            // actually renders.
            'branding' => fn () => SiteSettings::forInertia(),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Shared globally, not per-controller: any redirect anywhere in
            // the app can ->with('status', ...) or ->with('error', ...) and
            // have it surface, without every controller remembering to pass
            // it through explicitly. Closures so Inertia only reads the
            // session (and consumes the flash) when a page actually renders.
            'status' => fn () => $request->session()->get('status'),
            'error' => fn () => $request->session()->get('error'),
            // Resolved eagerly, exactly as Inertia resolves its own `errors`
            // prop one line above in its middleware. A closure would be
            // evaluated after the controller has run, by which point a
            // controller that redirects has already aged the flashed bag
            // away and this would silently be empty.
            'errorList' => $this->allValidationErrors($request),
        ];
    }

    /**
     * Every validation message per field, not just the first.
     *
     * Inertia's own `errors` prop keeps one message per key — see
     * Middleware::resolveValidationErrors — which is right for a field with a
     * single rule and wrong for a password, where four requirements can fail
     * at once and the owner would otherwise meet them one submission at a
     * time.
     *
     * Shared alongside `errors` rather than by flipping Inertia's
     * `withAllErrors`, which would turn every `errors.title` in the front end
     * into an array and break the twenty-odd screens typed against a string.
     * Screens that want the full list opt in; nothing else changes.
     *
     * @return array<string, list<string>>
     */
    private function allValidationErrors(Request $request): array
    {
        if (! $request->hasSession() || ! $request->session()->has('errors')) {
            return [];
        }

        $bag = $request->session()->get('errors')->getBag('default');

        return array_map(array_values(...), $bag->messages());
    }
}
