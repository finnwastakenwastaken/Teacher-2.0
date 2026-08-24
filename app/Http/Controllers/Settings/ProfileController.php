<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.profile.updated')]);

        return to_route('profile.edit');
    }

    /*
     * There is deliberately no destroy() method.
     *
     * This application has exactly one admin account. Deleting it would leave
     * a live site that nobody can administer, with no recovery path short of
     * database surgery. The prohibition is enforced in three places: no route
     * (routes/settings.php), no controller action (here), and a guard on the
     * model itself. Do not add this back.
     */
}
