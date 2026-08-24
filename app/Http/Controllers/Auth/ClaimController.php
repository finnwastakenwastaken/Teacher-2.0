<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\AdminAlreadyClaimedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ClaimAdminRequest;
use App\Support\AdminAccount;
use App\Support\AdminSetupToken;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClaimController extends Controller
{
    /**
     * Show the first-run "create your account" screen.
     *
     * Reachable only while no admin account exists — see routes/auth.php,
     * which pairs this with the `guest` middleware and EnsureAdminNotClaimed.
     */
    public function create(): Response
    {
        return Inertia::render('auth/claim', [
            'setupTokenRequired' => AdminSetupToken::isConfigured(),
            // So the screen can list the requirements before they are broken,
            // rather than revealing them one failed submission at a time.
            'passwordPolicy' => PasswordPolicy::describe(),
        ]);
    }

    /**
     * Create the single admin account and log its owner in immediately.
     */
    public function store(ClaimAdminRequest $request): RedirectResponse
    {
        try {
            $user = AdminAccount::claim(
                $request->safe()->only(['name', 'email', 'password'])
            );
        } catch (AdminAlreadyClaimedException) {
            // Lost a race with another request between the middleware check
            // and this one — extremely unlikely given the advisory lock, but
            // fail into the same "already set up" redirect rather than a 500.
            return redirect()->route('login')
                ->with('status', __('auth.claim.already_completed'));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
