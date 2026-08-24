<?php

namespace App\Http\Controllers;

use App\Models\AccessPassword;
use App\Support\AccessControl;
use App\Support\ContentPathResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Entering a password to unlock protected content.
 *
 * The visitor submits the path they were trying to reach rather than a
 * password id, so the form cannot be used to probe which password guards
 * what, or to unlock something the visitor never navigated to.
 */
class UnlockController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'password.required' => __('content.unlock.password_required'),
        ]);

        $node = ContentPathResolver::resolve(trim($validated['path'], '/'));

        abort_if($node === null, HttpResponse::HTTP_NOT_FOUND);

        $passwordId = AccessControl::effectivePasswordId($node);

        if ($passwordId === null) {
            // Nothing to unlock — the owner removed the protection between
            // the prompt rendering and this submission.
            return back();
        }

        // Two buckets, because they bound different things.
        //
        // Per IP and per password: one class hammering their own password
        // must not lock a different class out of theirs. This is the one that
        // keeps the feature usable. It relies on trustProxies (the technical reference
        // invariant 5) and on nginx overwriting X-Forwarded-For, or a visitor
        // supplies their own address and the bucket count is unbounded.
        //
        // Per password, whoever is asking: the backstop. A per-IP limit
        // multiplies linearly with addresses, and addresses are cheap — so on
        // its own it bounds one attacker's rate rather than the total. This
        // one caps the search itself. It is set well above what a whole class
        // getting it wrong looks like, so the only thing it can realistically
        // stop is a machine.
        $perMinute = (int) config('access.attempts_per_minute');

        $keys = [
            'unlock:'.$request->ip().':'.$passwordId => $perMinute,
            'unlock:'.$passwordId => $perMinute * (int) config('access.global_attempt_multiplier'),
        ];

        foreach ($keys as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = RateLimiter::availableIn($key);

                return back()->withErrors([
                    'password' => __('content.unlock.throttled', ['seconds' => $seconds]),
                ]);
            }
        }

        $password = AccessPassword::query()->find($passwordId);

        if ($password === null || ! Hash::check($validated['password'], $password->password_hash)) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key);
            }

            return back()->withErrors(['password' => __('content.unlock.incorrect')]);
        }

        foreach (array_keys($keys) as $key) {
            RateLimiter::clear($key);
        }

        // Unlocks everything this password guards, not just this page. The
        // record is what gets shared with a class; making them re-enter it
        // per page is what teaches people to write it on the whiteboard.
        return back()->withCookie(AccessControl::unlockCookie($password));
    }
}
