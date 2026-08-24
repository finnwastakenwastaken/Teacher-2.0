<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            // Two different things, deliberately. `passwordRules` is the
            // Safari/iOS `passwordrules` attribute — a hint that shapes the
            // password a browser generates, and invisible to the owner.
            // `passwordPolicy` is what gets drawn on screen as a checklist.
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordPolicy' => PasswordPolicy::describe(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        $this->signOutEverywhereElse($request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('admin.security.password_changed'),
        ]);

        return back();
    }

    /**
     * A changed password has to end the sessions opened under the old one.
     *
     * The commonest reason to change a password is believing someone else
     * has it, and without this the new hash changed nothing for them: a
     * forgotten library machine, a borrowed laptop, an attacker's cookie all
     * kept working until SESSION_LIFETIME expired them — two hours of
     * inactivity, renewed by any activity at all.
     *
     * Done by clearing the rows rather than with Auth::logoutOtherDevices(),
     * which needs the AuthenticateSession middleware added to the whole web
     * group to reach database-backed sessions. This stack stores sessions in
     * Postgres and has exactly one account, so every row that is not this
     * request's belongs to a session that should now be over — one query,
     * and no new middleware on every request for the sake of one action.
     */
    private function signOutEverywhereElse(PasswordUpdateRequest $request): void
    {
        // Any other driver keeps sessions somewhere this cannot reach. The
        // shipped configuration is `database`; a deployment that changed it
        // has made that trade knowingly.
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        // And a fresh id for this one, so a session fixated before the change
        // does not survive it either.
        $request->session()->regenerate();
    }
}
