<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Support\IconCatalogue;
use App\Support\SiteSettings;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The homepage: an editable introduction, then the category grid.
     *
     * The grid always renders and cannot be edited away — it is the site's
     * only entry point, and a homepage the owner can accidentally turn into
     * a dead end would be a support call nobody can answer from the browser
     * (the technical reference, "Homepage"). Everything above it is theirs.
     */
    public function show(): Response
    {
        $settings = SiteSettings::all();
        $images = SiteSettings::resolveImages([$settings['home_banner_image_id'] ?? null]);
        $banner = $images[$settings['home_banner_image_id'] ?? null] ?? null;

        $topics = Topic::query()
            ->whereNull('parent_id')
            ->visible()
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'icon', 'description'])
            ->map(fn (Topic $topic) => [...$topic->only(['id', 'title', 'slug', 'icon', 'description']), 'href' => '/'.$topic->slug]);

        return Inertia::render('welcome', [
            'home' => [
                'heading' => $settings['home_heading'],
                'subheading' => $settings['home_subheading'],
                'content' => $settings['home_content'],
                'banner' => $banner,
            ],
            'topics' => $topics,
            // Only the icons this page draws — see App\Support\IconCatalogue.
            'icons' => IconCatalogue::resolve($topics->pluck('icon')),
        ]);
    }
}
