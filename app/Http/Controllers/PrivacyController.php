<?php

namespace App\Http\Controllers;

use App\Support\SiteSettings;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyController extends Controller
{
    /**
     * What the site records, and what it does not.
     *
     * The statement itself is in lang/nl and lang/en rather than in the
     * database, because it describes what the *software* does — so it is the
     * application's own words and switches with the interface language, like
     * every other string the application writes (the technical reference). Only the
     * owner's optional addition is content, and that follows the usual rule:
     * stored once, never translated.
     *
     * Sends nothing but that addition; the rest is a translation lookup in the
     * front end, so this page costs no query on a site that has not set one.
     */
    public function show(): Response
    {
        return Inertia::render('content/privacy', [
            'ownerContent' => SiteSettings::all()['privacy_content'],
        ]);
    }
}
