<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Models\Image;
use App\Support\PageContent;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's own settings: branding, and the editable part of the homepage.
 *
 * Distinct from routes/settings.php, which is the *account* (profile,
 * password, 2FA). Everything here is about the site the visitors see.
 */
class SiteSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = SiteSettings::all();

        return Inertia::render('admin/site-settings/edit', [
            'settings' => [
                'site_title' => $settings['site_title'],
                'site_logo_image_id' => $settings['site_logo_image_id'],
                'site_favicon_image_id' => $settings['site_favicon_image_id'],
                'home_heading' => $settings['home_heading'],
                'home_subheading' => $settings['home_subheading'],
                'home_banner_image_id' => $settings['home_banner_image_id'],
                'home_content' => $settings['home_content'],
            ],
            // The whole image library, so the three pickers can render
            // thumbnails without a second round trip. It is one teacher's
            // library, not a public upload target — see the technical reference.
            'images' => Image::query()
                ->latest('id')
                ->get(['id', 'ulid', 'alt_text', 'original_filename'])
                ->map(fn (Image $image) => [
                    'id' => $image->id,
                    'alt' => $image->alt_text,
                    'filename' => $image->original_filename,
                    'url' => route('images.show', $image),
                ]),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $values = $request->validated();

        // validated() only returns keys that have rules, and `home_content`
        // has a rule on its shape rather than its contents — so the document
        // is read from the request and whitelisted here. Same trap as the
        // page editor; see the technical reference.
        $values['home_content'] = PageContent::sanitiseWithoutEmbeds(
            $request->input('home_content')
        );

        SiteSettings::put($values);

        return back()->with('status', 'Instellingen opgeslagen.');
    }
}
