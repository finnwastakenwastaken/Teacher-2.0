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
            'password.required' => 'Vul het wachtwoord in.',
        ]);

        $node = ContentPathResolver::resolve(trim($validated['path'], '/'));

        abort_if($node === null, HttpResponse::HTTP_NOT_FOUND);

        $passwordId = AccessControl::effectivePasswordId($node);

        if ($passwordId === null) {
            // Nothing to unlock — the owner removed the protection between
            // the prompt rendering and this submission.
            return back();
        }

        // Per IP and per password: one class hammering their own password
        // must not lock a different class out of theirs. Relies on
        // trustProxies (the technical reference) or every visitor behind the
        // tunnel shares one bucket.
        $key = 'unlock:'.$request->ip().':'.$passwordId;
        $perMinute = (int) config('access.attempts_per_minute');

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'password' => "Te veel pogingen. Probeer het over {$seconds} seconden opnieuw.",
            ]);
        }

        $password = AccessPassword::query()->find($passwordId);

        if ($password === null || ! Hash::check($validated['password'], $password->password_hash)) {
            RateLimiter::hit($key);

            return back()->withErrors(['password' => 'Dit wachtwoord klopt niet.']);
        }

        RateLimiter::clear($key);

        // Unlocks everything this password guards, not just this page. The
        // record is what gets shared with a class; making them re-enter it
        // per page is what teaches people to write it on the whiteboard.
        return back()->withCookie(AccessControl::unlockCookie($password));
    }
}
