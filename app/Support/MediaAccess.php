<?php

namespace App\Support;

use App\Models\Image;
use App\Models\MediaFile;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The single authorisation decision for every byte of uploaded media.
 *
 * The technical reference calls this the "no side door" guarantee: a file attached to a
 * hidden or password-protected page must not be fetchable by guessing its
 * URL. Every path that hands out media bytes goes through allows() first —
 * there is deliberately no second route, no public disk, and no signed-URL
 * escape hatch (see the comment on the `local` disk in config/filesystems.php
 * for why Laravel's own /storage/{path} routes are switched off).
 *
 * The default answer is NO.
 *
 * The failure directions are not symmetric. Fail-closed means a forgotten
 * wiring shows up as a student reporting a broken download — visible, and
 * fixed in minutes. Fail-open means material intended for one class is
 * quietly readable by anyone who guesses a URL, and nobody finds out.
 */
class MediaAccess
{
    public static function allows(Image|MediaFile $file, Request $request): bool
    {
        // Any authenticated user is the administrator. There is exactly one
        // account, registration does not exist at any layer, and the account
        // cannot be deleted (security invariants 1 and 2), so authentication
        // is sufficient here — the admin must be able to preview everything
        // in the media library, including files not yet placed on a page.
        if ($request->user() !== null) {
            return true;
        }

        return static::isPubliclyReachable($file, $request);
    }

    /**
     * Whether an anonymous visitor may see this file.
     *
     * A file is published exactly when some page shows it — its body embeds
     * it, or it is offered in its downloads section — *and* that page is one
     * this visitor may actually open. Until something points at it, a file is
     * private no matter what its URL is, which is what makes uploading to the
     * library a safe thing to do.
     *
     * The two ways a page can show a file are checked separately on purpose.
     * Page embeds live in `page_media_references`, derived data rebuilt
     * wholesale every time a body is saved; download attachments are authored
     * rows that must survive body edits. Folding downloads into the derived
     * table would mean the next save of the page body deleted them.
     *
     * ANY reachable page is enough. A worksheet used both in a protected
     * class page and on an open revision page is public — it is already
     * published, and refusing it via one URL while serving it via another
     * protects nothing. This is why passwords guard *pages*, not files.
     *
     * Hidden pages count as reachable. A hidden page is a draft or retired
     * page, removed from menus, the homepage grid and search, but it still
     * renders at its direct URL (the technical reference, "Hidden ≠ deleted"). Excluding
     * its media would mean the page still loads for anyone holding the link
     * but with every image broken and every download refused — a preview
     * that does not show what it will look like. Hidden controls
     * discoverability, not secrecy. Secrecy is what passwords are for.
     */
    private static function isPubliclyReachable(Image|MediaFile $file, Request $request): bool
    {
        // Branding — the logo, the favicon, the homepage banner. These are
        // the one category of media that is not reached through a page at
        // all: the logo renders in the header of every screen including the
        // login form, and the favicon is fetched by the browser with no
        // page context whatsoever. Refusing them would break the chrome of
        // the site for every anonymous visitor.
        //
        // This is a deliberate third case, not a loophole. It is as narrow
        // as the others: an image is public here only while a branding
        // setting actually points at it, and clearing that setting makes it
        // private again on the next request. Never widen it to "any image".
        if ($file instanceof Image && in_array($file->id, SiteSettings::brandingImageIds(), true)) {
            return true;
        }

        foreach (static::pagesShowing($file) as $page) {
            if (AccessControl::allows($page, $request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every page that puts this file in front of a visitor.
     *
     * Eager-loads the topic chain because resolving a page's effective
     * password walks up to its nearest protected ancestor.
     *
     * @return Collection<int, Page>
     */
    private static function pagesShowing(Image|MediaFile $file)
    {
        $pageIds = $file->pageReferences()->pluck('page_id');

        // Only media files can be offered as downloads; images are embedded.
        if ($file instanceof MediaFile) {
            $pageIds = $pageIds->concat($file->pageDownloads()->pluck('page_id'));
        }

        // A hero image is shown by exactly one page and is subject to that
        // page's password like anything else on it, so it resolves through
        // the same walk rather than getting a case of its own.
        if ($file instanceof Image) {
            $pageIds = $pageIds->concat(
                Page::query()->where('hero_image_id', $file->id)->pluck('id')
            );
        }

        if ($pageIds->isEmpty()) {
            return collect();
        }

        return Page::query()
            ->whereIn('id', $pageIds->unique()->all())
            ->with('topic.parent.parent')
            ->get();
    }
}
