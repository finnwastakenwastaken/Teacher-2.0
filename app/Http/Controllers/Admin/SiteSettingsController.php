<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingsRequest;
use App\Models\Image;
use App\Support\ContentLanguage;
use App\Support\PageContent;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
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
                'privacy_content' => $settings['privacy_content'],
                'content_language' => ContentLanguage::current(),
            ],
            'contentLanguages' => ContentLanguage::options(),
            // Only the images these three settings point at, so this screen
            // does not grow with the library; the pickers search
            // MediaSearchController for anything else. array_filter first
            // because an unset setting is null, and `whereIn('id', [null])`
            // would still run a pointless query.
            'selectedImages' => Image::query()
                ->whereIntegerInRaw('id', array_values(array_filter([
                    $settings['site_logo_image_id'],
                    $settings['site_favicon_image_id'],
                    $settings['home_banner_image_id'],
                ], fn ($id) => $id !== null)))
                ->get(['id', 'ulid', 'alt_text', 'original_filename'])
                ->map(fn (Image $image) => $image->toPickerOption())
                ->values(),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $values = $request->validated();

        // validated() only returns keys with rules, and `home_content` is
        // only validated by shape — so it's read from the request and
        // whitelisted here. Guarded on presence, or an absent key would
        // sanitise to null and erase the introduction on any request that
        // omitted it; an explicit null still clears it.
        if ($request->has('home_content')) {
            $values['home_content'] = PageContent::sanitiseWithoutEmbeds(
                $request->input('home_content')
            );
        }

        // The privacy page's owner-written addition, on the same terms and
        // for the same reason: embeds are stripped because a file is
        // published by walking from it to the *pages* showing it, and this is
        // not a page row — an embed here would render for the owner and 403
        // for every visitor.
        if ($request->has('privacy_content')) {
            $values['privacy_content'] = PageContent::sanitiseWithoutEmbeds(
                $request->input('privacy_content')
            );
        }

        $languageChanged = array_key_exists('content_language', $values)
            && $values['content_language'] !== ContentLanguage::current();

        SiteSettings::put($values);

        // The trigger only fires on a write, so already-stored pages keep
        // their old stemming until reindexed — run it here rather than
        // leaving the owner a command to remember. No queue needed; the
        // corpus is small enough to do synchronously.
        if ($languageChanged) {
            Artisan::call('search:reindex');

            return back()->with('status', __('admin.settings.saved_and_reindexed'));
        }

        return back()->with('status', __('admin.settings.saved'));
    }
}
