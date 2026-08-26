<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateThemePaletteRequest;
use App\Support\SiteSettings;
use App\Support\ThemePalette;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's colours.
 *
 * Separate from SiteSettingsController, which is the name, the logo and the
 * homepage copy, because this screen carries something none of the others do:
 * a contrast gate that measures twenty semantic pairs in both themes before it
 * will let the form submit. That measurement happens in the browser — see
 * resources/js/lib/palette-contrast.ts and the note on App\Support\ThemePalette
 * for why it cannot happen here — so this controller's job is the other half:
 * take a colour only if it is a colour, and store only what differs from the
 * palette the site ships with.
 */
class ThemeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/theme/edit', [
            'palette' => ThemePalette::forInertia(),
        ]);
    }

    public function update(UpdateThemePaletteRequest $request): RedirectResponse
    {
        /** @var array<string, string> $submitted */
        $submitted = $request->validated()['palette'] ?? [];

        $overrides = ThemePalette::onlyOverrides($submitted);

        // Resetting is a delete, not a row holding the shipped value. See
        // SiteSettings::forget().
        if ($overrides === []) {
            SiteSettings::forget(ThemePalette::SETTING);

            return back()->with('status', __('admin.theme.reset'));
        }

        SiteSettings::put([ThemePalette::SETTING => $overrides]);

        return back()->with('status', __('admin.theme.saved'));
    }
}
