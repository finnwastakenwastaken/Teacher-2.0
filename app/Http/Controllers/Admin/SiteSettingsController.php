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
                'content_language' => ContentLanguage::current(),
            ],
            'contentLanguages' => ContentLanguage::options(),
            // Only the images these three settings point at — at most three,
            // so that this screen does not grow with the library. The pickers
            // search App\Http\Controllers\Admin\MediaSearchController for
            // anything else, a capped page of matches at a time.
            //
            // whereIntegerInRaw over the ids rather than three separate
            // lookups, and array_filter first because an unset setting is
            // null: `whereIn('id', [null])` matches nothing but still asks.
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

        // validated() only returns keys that have rules, and `home_content`
        // has a rule on its shape rather than its contents — so the document
        // is read from the request and whitelisted here. Same trap as the
        // page editor; see the technical reference.
        //
        // Guarded on presence for the same reason the topic introduction is:
        // sanitising an absent key yields null, which would replace the
        // homepage introduction with nothing on any request that did not
        // carry it. Sending an explicit null still clears it.
        if ($request->has('home_content')) {
            $values['home_content'] = PageContent::sanitiseWithoutEmbeds(
                $request->input('home_content')
            );
        }

        $languageChanged = array_key_exists('content_language', $values)
            && $values['content_language'] !== ContentLanguage::current();

        SiteSettings::put($values);

        // The trigger only fires on a write, so every page already stored
        // keeps its old stemming until it is saved again. Without this the
        // setting appears to take effect and search quietly keeps missing
        // words — run it here rather than leaving the owner a command to
        // remember. There is no queue, and the corpus is tens to hundreds of
        // rows: one statement, synchronously.
        if ($languageChanged) {
            Artisan::call('search:reindex');

            return back()->with('status', __('admin.settings.saved_and_reindexed'));
        }

        return back()->with('status', __('admin.settings.saved'));
    }
}
