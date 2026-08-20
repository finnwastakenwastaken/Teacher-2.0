<?php

namespace App\Http\Middleware;

use App\Support\AdminAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the claim screen itself.
 *
 * There is exactly one path to creating the admin account, and it closes
 * permanently the instant that account exists. This middleware is what
 * closes it for a guest who navigates straight to the claim URL after setup
 * is already done. Combined with the `guest` middleware on the same routes
 * (which handles an already-logged-in admin revisiting the URL), every case
 * is covered — see the technical reference.
 */
class EnsureAdminNotClaimed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AdminAccount::exists()) {
            return redirect()->route('login')
                ->with('status', __('De installatie is al voltooid. Log hieronder in.'));
        }

        return $next($request);
    }
}
